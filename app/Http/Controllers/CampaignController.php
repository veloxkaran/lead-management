<?php

namespace App\Http\Controllers;

use App\Enums\CampaignAudience;
use App\Enums\CampaignChannel;
use App\Enums\CampaignRecipientStatus;
use App\Http\Requests\Campaign\PreviewCampaignRecipientsRequest;
use App\Http\Requests\Campaign\StoreCampaignRequest;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Industry;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Policies\CampaignPolicy;
use App\Services\CampaignMailer;
use App\Services\CampaignService;
use App\Support\CampaignSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignController extends Controller
{
    public function __construct(protected CampaignService $campaigns)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Campaign::class);

        return view('campaigns.index', [
            'campaigns' => $this->visibleTo($request)
                ->with('creator')
                ->withCount(Campaign::statusCounts())
                ->latest()
                ->paginate(20),
        ]);
    }

    /**
     * The Contacts list links here with ?channel=, ?audience=none and either
     * ?contact_ids[]= (the ticked rows) or ?all_contacts=1 to pre-fill the form.
     */
    public function create(Request $request, CampaignSettings $settings, CampaignMailer $mailer): View
    {
        $this->authorize('create', Campaign::class);

        $prefill = [
            'channel' => CampaignChannel::tryFrom((string) $request->query('channel'))?->value ?? CampaignChannel::Email->value,
            'audience' => CampaignAudience::tryFrom((string) $request->query('audience'))?->value ?? CampaignAudience::AllLeads->value,
            'all_contacts' => $request->boolean('all_contacts'),
            'contact_ids' => array_values(array_filter(array_map('intval', (array) $request->query('contact_ids', [])))),
        ];

        return view('campaigns.create', [
            'audiences' => CampaignAudience::cases(),
            'channels' => CampaignChannel::cases(),
            'statuses' => LeadStatus::ordered()->get(),
            'industries' => Industry::ordered()->get(),
            'leads' => Lead::orderBy('company_name')->get(['id', 'company_name', 'contact_person', 'email', 'phone']),
            'contacts' => Contact::orderByRaw('LOWER(COALESCE(name, company_name, email, phone))')->get(['id', 'company_name', 'name', 'email', 'phone']),
            'canSeeContacts' => $request->user()->can('viewAny', Contact::class) && $request->user()->hasPermission('contacts'),
            'prefill' => $prefill,
            'smsConfigured' => $settings->smsConfigured(),
            'sendOptions' => collect(CampaignChannel::cases())->mapWithKeys(fn (CampaignChannel $c) => [$c->value => $settings->sendOptions($c)]),
            'sender' => $mailer->senderDescription(),
            'hasSignature' => $settings->signature() !== null,
        ]);
    }

    /**
     * Live "who will this reach" check behind the create form — same
     * builder the send uses, so duplicates and invalid entries shown here
     * are exactly what will be left out.
     */
    public function preview(PreviewCampaignRecipientsRequest $request): JsonResponse
    {
        return response()->json($this->campaigns->recipientList($request->validated())->summary());
    }

    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        $campaign = $this->campaigns->create($request->validated(), $request->user());

        $skipped = count($campaign->skipped ?? []);
        $message = $campaign->scheduled_at
            ? "Campaign scheduled for {$campaign->scheduled_at->format('M d, Y g:i A')} — {$campaign->recipient_count} recipient(s)."
            : "Campaign queued for {$campaign->recipient_count} recipient(s).";

        return redirect()->route('campaigns.show', $campaign)
            ->with('success', $message.($skipped ? " {$skipped} duplicate/invalid entr".($skipped === 1 ? 'y was' : 'ies were').' left out.' : ''));
    }

    public function show(Request $request, Campaign $campaign): View
    {
        $this->authorize('view', $campaign);

        $campaign->load('creator')->loadCount([
            ...Campaign::statusCounts(),
            'recipients as unsubscribed_count' => fn ($q) => $q->whereNotNull('unsubscribed_at'),
            'recipients as retryable_count' => fn ($q) => $q->where('status', CampaignRecipientStatus::Failed)->whereNull('sent_at'),
        ]);

        [$recipients, $filters] = $this->filteredRecipients($request, $campaign);

        return view('campaigns.show', [
            'campaign' => $campaign,
            'recipients' => $recipients->with('lead:id,company_name')->orderBy('id')->paginate(50)->withQueryString(),
            'currentStatus' => $filters['status'],
            'filters' => $filters,
            'batches' => $this->batchSummary($campaign),
            'stuckCount' => $this->stuckCount($campaign),
            'statusNames' => $campaign->audience === CampaignAudience::LeadStatuses
                ? LeadStatus::whereIn('id', $campaign->audience_filter ?? [])->pluck('name')
                : collect(),
        ]);
    }

    /**
     * The send log as CSV — every recipient (or the current filter) with
     * batch, status, error and timestamps.
     */
    public function export(Request $request, Campaign $campaign): StreamedResponse
    {
        $this->authorize('view', $campaign);

        [$recipients] = $this->filteredRecipients($request, $campaign);
        $recipients->with('lead:id,company_name')->orderBy('id');
        $opened = $campaign->isEmail() ? 'Opened at' : 'Delivered at';

        return response()->streamDownload(function () use ($recipients, $campaign, $opened) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Batch', $campaign->isEmail() ? 'Email' : 'Phone', 'Name', 'Company', 'Source', 'Status', 'Error', 'Queued at', 'Sent at', $opened, 'Unsubscribed at']);

            $recipients->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $r) {
                    fputcsv($out, [
                        $r->batch, $r->address, $r->name, $r->company_name,
                        $r->lead ? 'Lead: '.$r->lead->company_name : ($r->contact_id ? 'Contact' : 'Extra contact'),
                        $r->status->label(), $r->error,
                        $r->queued_at?->toDateTimeString(), $r->sent_at?->toDateTimeString(),
                        $r->delivered_at?->toDateTimeString(), $r->unsubscribed_at?->toDateTimeString(),
                    ]);
                }
            });

            fclose($out);
        }, Str::slug($campaign->name).'-send-log-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function pause(Campaign $campaign): RedirectResponse
    {
        $this->authorize('pause', $campaign);

        $this->campaigns->pause($campaign);

        return back()->with('success', 'Campaign paused — emails already on their way finish; the rest wait until you resume.');
    }

    public function resume(Campaign $campaign): RedirectResponse
    {
        $this->authorize('resume', $campaign);

        $this->campaigns->resume($campaign);

        return back()->with('success', 'Campaign resumed — the next batch is queued.');
    }

    public function retryFailed(Campaign $campaign): RedirectResponse
    {
        $this->authorize('retryFailed', $campaign);

        $count = $this->campaigns->retryFailed($campaign);

        return back()->with($count ? 'success' : 'error', $count
            ? "{$count} failed message(s) put back in line — they go out with the next batch."
            : 'Nothing to retry — only messages the server refused (never sent) can be retried.');
    }

    public function cancel(Campaign $campaign): RedirectResponse
    {
        $this->authorize('cancel', $campaign);

        $this->campaigns->cancel($campaign);

        return back()->with('success', 'Campaign cancelled — messages not yet sent will not go out.');
    }

    /**
     * @return array{0: HasMany, 1: array{status: ?CampaignRecipientStatus, batch: ?int, search: string}}
     */
    private function filteredRecipients(Request $request, Campaign $campaign): array
    {
        $filters = [
            'status' => CampaignRecipientStatus::tryFrom((string) $request->query('status')),
            'batch' => $request->integer('batch') ?: null,
            'search' => trim((string) $request->query('search')),
        ];

        $query = $campaign->recipients()
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('status') === 'unsubscribed', fn ($q) => $q->whereNotNull('unsubscribed_at'))
            ->when($filters['batch'], fn ($q, $batch) => $q->where('batch', $batch))
            ->when($filters['search'] !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('address', 'like', "%{$filters['search']}%")
                ->orWhere('name', 'like', "%{$filters['search']}%")
                ->orWhere('company_name', 'like', "%{$filters['search']}%")));

        return [$query, $filters];
    }

    /**
     * One row per batch: how many it holds and where each stands.
     */
    private function batchSummary(Campaign $campaign)
    {
        $sum = fn (string $status) => "SUM(CASE WHEN status = '{$status}' THEN 1 ELSE 0 END)";

        return $campaign->recipients()
            ->toBase()
            ->whereNotNull('batch')
            ->selectRaw('batch, COUNT(*) as total')
            ->selectRaw($sum('sent').' + '.$sum('delivered').' as sent')
            ->selectRaw($sum('delivered').' as delivered')
            ->selectRaw($sum('failed').' as failed')
            ->selectRaw($sum('pending').' as pending')
            ->selectRaw($sum('cancelled').' as cancelled')
            ->selectRaw('MIN(queued_at) as queued_at, MAX(sent_at) as last_sent_at')
            ->groupBy('batch')
            ->orderBy('batch')
            ->get();
    }

    /**
     * Messages queued well past when they should have gone out — almost
     * always a queue worker (cron) that isn't running.
     */
    private function stuckCount(Campaign $campaign): int
    {
        $spread = (int) ceil(max(1, (int) $campaign->sendOption('batch_size')) / max(1, (int) $campaign->sendOption('per_minute')));

        return $campaign->recipients()
            ->where('status', CampaignRecipientStatus::Pending)
            ->whereNotNull('queued_at')
            ->where('queued_at', '<', now()->subMinutes($spread + (int) config('campaigns.stuck_after_minutes')))
            ->count();
    }

    private function visibleTo(Request $request): Builder
    {
        $user = $request->user();

        return Campaign::query()->when(! CampaignPolicy::seesAllCampaigns($user), fn ($q) => $q->where('created_by', $user->id));
    }
}
