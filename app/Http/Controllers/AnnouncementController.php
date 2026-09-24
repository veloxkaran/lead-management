<?php

namespace App\Http\Controllers;

use App\Enums\AnnouncementAudience;
use App\Enums\EmailLogStatus;
use App\Http\Requests\Announcement\StoreAnnouncementRequest;
use App\Models\Announcement;
use App\Models\EmailTemplate;
use App\Models\LeadStatus;
use App\Models\Setting;
use App\Services\AnnouncementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function __construct(protected AnnouncementService $announcements)
    {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Announcement::class);

        return view('announcements.index', [
            'announcements' => Announcement::with('creator')
                ->withCount($this->statusCounts())
                ->latest()
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Announcement::class);

        $statuses = LeadStatus::ordered()->get();

        return view('announcements.create', [
            'audiences' => AnnouncementAudience::cases(),
            'statuses' => $statuses,
            // Shown next to each option so the sender knows the reach
            // before hitting submit.
            'audienceCounts' => [
                AnnouncementAudience::AllLeads->value => $this->announcements->recipients(AnnouncementAudience::AllLeads)->count(),
                AnnouncementAudience::Customers->value => $this->announcements->recipients(AnnouncementAudience::Customers)->count(),
            ],
            'statusCounts' => $statuses->mapWithKeys(fn (LeadStatus $status) => [
                $status->id => $this->announcements->recipients(AnnouncementAudience::LeadStatuses, [$status->id])->count(),
            ]),
            'perMinute' => config('mail.announcement_per_minute'),
            // Raw template, merged client-side for the pre-send preview.
            'template' => EmailTemplate::where('key', AnnouncementService::TEMPLATE_KEY)->first(['subject', 'body'])
                ?? ['subject' => '{{title}}', 'body' => "Hi {{contact_person}},\n\n{{content}}\n\nThanks,\n{{app_name}}"],
            'appName' => config('app.name'),
            'companyName' => Setting::get('company_name') ?: config('app.name'),
        ]);
    }

    public function store(StoreAnnouncementRequest $request): RedirectResponse
    {
        $announcement = $this->announcements->send(
            $request->safe()->except('images', 'documents'),
            $request->file('images', []),
            $request->user(),
            $request->file('documents', []),
        );

        return redirect()->route('announcements.show', $announcement)
            ->with('success', "Announcement queued for {$announcement->recipient_count} recipient(s).");
    }

    public function show(Request $request, Announcement $announcement): View
    {
        $this->authorize('view', $announcement);

        $announcement->load('images', 'documents', 'creator');

        if (! $request->user()->can('viewDelivery', Announcement::class)) {
            return view('announcements.show', ['announcement' => $announcement, 'logs' => null]);
        }

        $announcement->loadCount($this->statusCounts());

        $logs = $announcement->emailLogs()->latest('id');

        if ($status = EmailLogStatus::tryFrom((string) $request->query('status'))) {
            $logs->where('status', $status);
        }

        return view('announcements.show', [
            'announcement' => $announcement,
            'logs' => $logs->paginate(25)->withQueryString(),
            'statusNames' => $announcement->lead_status_ids
                ? LeadStatus::whereIn('id', $announcement->lead_status_ids)->pluck('name')
                : collect(),
            'preview' => $this->announcements->render($announcement),
        ]);
    }

    /**
     * @return array<string, \Closure>
     */
    private function statusCounts(): array
    {
        return collect(EmailLogStatus::cases())->mapWithKeys(fn (EmailLogStatus $status) => [
            "emailLogs as {$status->value}_count" => fn ($q) => $q->where('status', $status),
        ])->all();
    }
}
