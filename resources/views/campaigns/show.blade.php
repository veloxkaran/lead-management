@extends('layouts.app')

@section('title', $campaign->name)

@section('content')
    <x-page-header :title="$campaign->name" icon="bi-send" :subtitle="$campaign->channel->label().' campaign · created by '.($campaign->creator?->name ?? 'Unknown').' on '.$campaign->created_at->format('M d, Y g:i A')">
        <x-slot:actions>
            @can('pause', $campaign)
                <form method="POST" action="{{ route('campaigns.pause', $campaign) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pause-circle"></i> Pause</button>
                </form>
            @endcan
            @can('resume', $campaign)
                <form method="POST" action="{{ route('campaigns.resume', $campaign) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-play-circle"></i> Resume</button>
                </form>
            @endcan
            @if ($campaign->retryable_count)
                @can('retryFailed', $campaign)
                    <form method="POST" action="{{ route('campaigns.retry-failed', $campaign) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-warning btn-sm"><i class="bi bi-arrow-repeat"></i> Retry {{ $campaign->retryable_count }} failed</button>
                    </form>
                @endcan
            @endif
            <a href="{{ route('campaigns.export', [$campaign, ...array_filter(request()->only('status', 'batch', 'search'))]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download"></i> Export log</a>
            @can('cancel', $campaign)
                <form method="POST" action="{{ route('campaigns.cancel', $campaign) }}" class="d-inline" data-confirm-delete data-confirm-title="Cancel this campaign?" data-confirm-text="Messages not sent yet won't go out. Already-sent messages can't be recalled." data-confirm-button-text="Cancel Campaign">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle"></i> Cancel Campaign</button>
                </form>
            @endcan
            <a href="{{ route('campaigns.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
        </x-slot:actions>
    </x-page-header>

    @php
        $total = max(1, $campaign->recipient_count);
        $done = $campaign->sent_count + $campaign->delivered_count;
        $statuses = App\Enums\CampaignRecipientStatus::cases();
        $isLive = in_array($campaign->status, [App\Enums\CampaignStatus::Sending, App\Enums\CampaignStatus::Pending], true);
        $finishAt = $campaign->estimatedFinishAt();
        $openedLabel = $campaign->isEmail() ? 'Opened' : 'Delivered';
        $statusFilter = request('status');
    @endphp

    @if ($stuckCount)
        <div class="alert alert-danger small">
            <i class="bi bi-exclamation-octagon"></i>
            <strong>{{ $stuckCount }} message(s) have been waiting in the queue longer than they should.</strong>
            The queue worker probably isn't running — on the server, cron must run <code>php artisan schedule:run</code> every minute.
            They'll go out as soon as it runs.
        </div>
    @endif

    @if ($campaign->status === App\Enums\CampaignStatus::Paused)
        <div class="alert alert-secondary small">
            <i class="bi bi-pause-circle"></i> Paused {{ $campaign->paused_at?->diffForHumans() }}. Nothing more goes out until you <strong>Resume</strong>.
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3 col-xl">
            <div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
                <div class="small text-muted">Campaign</div>
                <span class="badge {{ $campaign->status->badgeClass() }} fs-6">{{ $campaign->status->label() }}</span>
                @if ($campaign->scheduled_at && $campaign->status === App\Enums\CampaignStatus::Pending)
                    <div class="small text-muted mt-1"><i class="bi bi-clock"></i> {{ $campaign->scheduled_at->format('M d, Y g:i A') }}</div>
                @endif
            </div></div>
        </div>
        @foreach ($statuses as $status)
            <div class="col-6 col-md-3 col-xl">
                <a href="{{ route('campaigns.show', [$campaign, 'status' => $currentStatus === $status ? null : $status->value]) }}" class="card border-0 shadow-sm h-100 text-decoration-none {{ $currentStatus === $status ? 'border border-primary' : '' }}">
                    <div class="card-body py-2">
                        <div class="small text-muted">{{ $status === App\Enums\CampaignRecipientStatus::Delivered ? $openedLabel : $status->label() }}</div>
                        <div class="fs-4 fw-semibold text-body">{{ number_format($campaign->{$status->value.'_count'}) }}</div>
                    </div>
                </a>
            </div>
        @endforeach
        @if ($campaign->isEmail())
            <div class="col-6 col-md-3 col-xl">
                <a href="{{ route('campaigns.show', [$campaign, 'status' => $statusFilter === 'unsubscribed' ? null : 'unsubscribed']) }}" class="card border-0 shadow-sm h-100 text-decoration-none {{ $statusFilter === 'unsubscribed' ? 'border border-primary' : '' }}">
                    <div class="card-body py-2">
                        <div class="small text-muted">Unsubscribed</div>
                        <div class="fs-4 fw-semibold text-body">{{ number_format($campaign->unsubscribed_count) }}</div>
                    </div>
                </a>
            </div>
        @endif
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between small mb-1 gap-2">
                <span><strong>{{ number_format($done) }}</strong> of {{ number_format($campaign->recipient_count) }} sent</span>
                <span class="text-muted">{{ number_format($campaign->delivered_count) }} {{ strtolower($openedLabel) }} · {{ number_format($campaign->failed_count) }} failed</span>
            </div>
            <div class="progress" role="progressbar" aria-label="Campaign progress" style="height: 10px;">
                <div class="progress-bar bg-success" style="width: {{ $campaign->delivered_count / $total * 100 }}%"></div>
                <div class="progress-bar bg-primary" style="width: {{ $campaign->sent_count / $total * 100 }}%"></div>
                <div class="progress-bar bg-danger" style="width: {{ $campaign->failed_count / $total * 100 }}%"></div>
            </div>

            <div class="d-flex flex-wrap gap-3 small mt-2">
                @if ($campaign->batch_count)
                    <span><i class="bi bi-stack text-muted"></i>
                        @if ($campaign->current_batch)
                            Batch <strong>{{ $campaign->current_batch }}</strong> of {{ $campaign->batch_count }}
                        @else
                            {{ $campaign->batch_count }} batch(es)
                        @endif
                    </span>
                @endif
                <span class="text-muted"><i class="bi bi-speedometer2"></i>
                    up to {{ $campaign->sendOption('batch_size') }} per batch · {{ $campaign->sendOption('per_minute') }}/min
                    @if ($campaign->sendOption('pause_minutes'))
                        · {{ $campaign->sendOption('pause_minutes') }} min pause between batches
                    @endif
                </span>
                @if ($campaign->status === App\Enums\CampaignStatus::Sending && $campaign->next_batch_at)
                    <span><i class="bi bi-hourglass-split text-muted"></i> Next batch {{ $campaign->next_batch_at->isPast() ? 'starting now' : 'at '.$campaign->next_batch_at->format('g:i A') }}</span>
                @endif
                @if ($finishAt)
                    <span><i class="bi bi-flag text-muted"></i> Finishes around {{ $finishAt->format($finishAt->isToday() ? 'g:i A' : 'M d, g:i A') }}</span>
                @elseif ($campaign->completed_at)
                    <span class="text-muted"><i class="bi bi-flag"></i> {{ $campaign->status->label() }} {{ $campaign->completed_at->format('M d, g:i A') }}</span>
                @endif
                @if ($campaign->isEmail())
                    <span class="text-muted"><i class="bi bi-pen"></i> Signature {{ $campaign->sendOption('include_signature') ? 'on' : 'off' }} · open tracking {{ $campaign->sendOption('track_opens') ? 'on' : 'off' }}</span>
                @endif
            </div>

            @if ($isLive)
                <div class="form-check form-switch small mt-2"
                     x-data="{ auto: (() => { try { return localStorage.getItem('campaignAutoRefresh') !== '0'; } catch (e) { return true; } })() }"
                     x-init="$watch('auto', v => { try { localStorage.setItem('campaignAutoRefresh', v ? '1' : '0'); } catch (e) {} }); setInterval(() => { if (auto && !document.hidden) location.reload(); }, 30000)">
                    <input class="form-check-input" type="checkbox" role="switch" id="autoRefresh" x-model="auto">
                    <label class="form-check-label text-muted" for="autoRefresh">Refresh every 30 seconds while sending</label>
                </div>
            @endif

            <div class="form-text">
                @if ($campaign->isEmail())
                    <strong>Sent</strong> = accepted by the mail server. <strong>Opened</strong> = the recipient's mail app loaded the email's images (needs open tracking); emails read with images blocked stay "Sent".
                @else
                    <strong>Sent</strong> = accepted by the SMS gateway. <strong>Delivered</strong> = confirmed by the gateway's delivery report (needs the delivery-report URL from Campaign Setup registered with your provider).
                @endif
            </div>
        </div>
    </div>

    @if ($batches->isNotEmpty())
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">Batches</div>
            <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light" style="position: sticky; top: 0;">
                        <tr>
                            <th>Batch</th>
                            <th class="text-end">Recipients</th>
                            <th class="text-end">Sent</th>
                            <th class="text-end">{{ $openedLabel }}</th>
                            <th class="text-end">Failed</th>
                            <th class="text-end">Waiting</th>
                            <th>Started</th>
                            <th>Last sent</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($batches as $batch)
                            @php
                                $state = match (true) {
                                    $batch->pending > 0 && $batch->queued_at !== null => ['Sending', 'bg-info-subtle text-info-emphasis'],
                                    $batch->pending > 0 => ['Waiting', 'bg-warning-subtle text-warning-emphasis'],
                                    $batch->cancelled > 0 && $batch->sent == 0 => ['Cancelled', 'bg-secondary-subtle text-secondary-emphasis'],
                                    default => ['Done', 'bg-success-subtle text-success-emphasis'],
                                };
                            @endphp
                            <tr @class(['table-active' => (int) $filters['batch'] === (int) $batch->batch])>
                                <td class="small text-nowrap">
                                    <a href="{{ route('campaigns.show', [$campaign, 'batch' => (int) $filters['batch'] === (int) $batch->batch ? null : $batch->batch]) }}" class="text-decoration-none fw-semibold">#{{ $batch->batch }}</a>
                                    <span class="badge {{ $state[1] }}">{{ $state[0] }}</span>
                                </td>
                                <td class="small text-end">{{ $batch->total }}</td>
                                <td class="small text-end">{{ $batch->sent }}</td>
                                <td class="small text-end">{{ $batch->delivered }}</td>
                                <td class="small text-end {{ $batch->failed ? 'text-danger fw-semibold' : '' }}">{{ $batch->failed }}</td>
                                <td class="small text-end">{{ $batch->pending }}</td>
                                <td class="small text-muted text-nowrap">{{ $batch->queued_at ? \Illuminate\Support\Carbon::parse($batch->queued_at)->format('M d, g:i A') : '—' }}</td>
                                <td class="small text-muted text-nowrap">{{ $batch->last_sent_at ? \Illuminate\Support\Carbon::parse($batch->last_sent_at)->format('M d, g:i A') : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Message</div>
                <div class="card-body small">
                    @if ($campaign->isEmail())
                        <div class="text-muted">Subject</div>
                        <p class="fw-semibold">{{ $campaign->subject }}</p>
                    @endif
                    <div class="text-break" style="white-space: pre-wrap;">{{ $campaign->message }}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Audience</div>
                <div class="card-body small">
                    <p class="mb-1">{{ $campaign->audience->label() }}</p>
                    @if ($statusNames->isNotEmpty())
                        <p class="text-muted mb-1">{{ $statusNames->implode(', ') }}</p>
                    @elseif ($campaign->audience === App\Enums\CampaignAudience::Industries)
                        <p class="text-muted mb-1">{{ implode(', ', $campaign->audience_filter ?? []) }}</p>
                    @endif
                    @if ($campaign->lead_ids)
                        <p class="mb-1">+ {{ count($campaign->lead_ids) }} hand-picked lead(s)</p>
                    @endif
                    @if ($campaign->all_contacts)
                        <p class="mb-1">+ all contacts</p>
                    @elseif ($campaign->contact_ids)
                        <p class="mb-1">+ {{ count($campaign->contact_ids) }} picked contact(s)</p>
                    @endif
                    @if ($campaign->skipped)
                        <details class="mt-2">
                            <summary class="text-warning-emphasis">{{ count($campaign->skipped) }} duplicate/invalid/unsubscribed entr{{ count($campaign->skipped) === 1 ? 'y' : 'ies' }} left out</summary>
                            <ul class="list-unstyled mt-2 mb-0" style="max-height: 240px; overflow-y: auto;">
                                @foreach ($campaign->skipped as $entry)
                                    @php
                                        [$label, $class] = match ($entry['type'] ?? '') {
                                            'duplicate' => ['Duplicate', 'bg-warning-subtle text-warning-emphasis'],
                                            'unsubscribed' => ['Unsubscribed', 'bg-secondary-subtle text-secondary-emphasis'],
                                            default => ['Invalid', 'bg-danger-subtle text-danger-emphasis'],
                                        };
                                    @endphp
                                    <li class="border-top py-1">
                                        <span class="badge {{ $class }}">{{ $label }}</span>
                                        <span class="fw-semibold text-break">{{ $entry['value'] }}</span>
                                        <div class="text-muted">{{ $entry['reason'] }}</div>
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <form method="GET" action="{{ route('campaigns.show', $campaign) }}" class="row g-2 align-items-center">
                <div class="col-12 col-md-auto fw-semibold me-md-auto">Send log</div>
                <div class="col-12 col-sm-5 col-md-3">
                    <input type="search" name="search" value="{{ $filters['search'] }}" class="form-control form-control-sm" placeholder="Search email, name, company…" aria-label="Search recipients">
                </div>
                <div class="col-6 col-sm-3 col-md-2">
                    <select name="status" class="form-select form-select-sm" aria-label="Status" onchange="this.form.submit()">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected($statusFilter === $status->value)>{{ $status === App\Enums\CampaignRecipientStatus::Delivered ? $openedLabel : $status->label() }}</option>
                        @endforeach
                        @if ($campaign->isEmail())
                            <option value="unsubscribed" @selected($statusFilter === 'unsubscribed')>Unsubscribed</option>
                        @endif
                    </select>
                </div>
                @if ($campaign->batch_count > 1)
                    <div class="col-6 col-sm-2 col-md-2">
                        <select name="batch" class="form-select form-select-sm" aria-label="Batch" onchange="this.form.submit()">
                            <option value="">All batches</option>
                            @for ($i = 1; $i <= $campaign->batch_count; $i++)
                                <option value="{{ $i }}" @selected((int) $filters['batch'] === $i)>Batch {{ $i }}</option>
                            @endfor
                        </select>
                    </div>
                @endif
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
                    @if ($statusFilter || $filters['batch'] || $filters['search'] !== '')
                        <a href="{{ route('campaigns.show', $campaign) }}" class="btn btn-sm btn-link">Clear</a>
                    @endif
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Batch</th>
                        <th>{{ $campaign->isEmail() ? 'Email' : 'Phone' }}</th>
                        <th>Name</th>
                        <th>Lead / Contact</th>
                        <th>Status</th>
                        <th>Queued</th>
                        <th>Sent</th>
                        <th>{{ $openedLabel }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recipients as $recipient)
                        <tr>
                            <td class="small text-muted">{{ $recipient->batch ? '#'.$recipient->batch : '—' }}</td>
                            <td class="small text-break">{{ $recipient->address }}</td>
                            <td class="small">{{ $recipient->name ?? '—' }}</td>
                            <td class="small">
                                @if ($recipient->lead)
                                    <a href="{{ route('leads.show', $recipient->lead) }}" class="text-decoration-none">{{ $recipient->lead->company_name }}</a>
                                @elseif ($recipient->contact_id)
                                    <span class="badge bg-info-subtle text-info-emphasis"><i class="bi bi-person-lines-fill"></i> Contact</span>
                                    @if ($recipient->company_name)<span class="text-muted">{{ $recipient->company_name }}</span>@endif
                                @else
                                    <span class="text-muted">Extra contact</span>
                                @endif
                            </td>
                            <td class="small">
                                <span class="badge {{ $recipient->status->badgeClass() }}">{{ $recipient->status === App\Enums\CampaignRecipientStatus::Delivered ? $openedLabel : $recipient->status->label() }}</span>
                                @if ($recipient->unsubscribed_at)
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis" title="{{ $recipient->unsubscribed_at->format('M d, Y g:i A') }}">Unsubscribed</span>
                                @endif
                                @if ($recipient->error)
                                    <div class="text-danger text-break" style="max-width: 320px;">{{ \Illuminate\Support\Str::limit($recipient->error, 160) }}</div>
                                @endif
                            </td>
                            <td class="small text-muted text-nowrap">{{ $recipient->queued_at?->format('M d, g:i A') ?? '—' }}</td>
                            <td class="small text-muted text-nowrap">{{ $recipient->sent_at?->format('M d, g:i A') ?? '—' }}</td>
                            <td class="small text-muted text-nowrap">{{ $recipient->delivered_at?->format('M d, g:i A') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted small py-4">No recipients match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white d-flex flex-wrap align-items-center gap-2">
            <span class="small text-muted">{{ number_format($recipients->total()) }} recipient(s)</span>
            @if ($recipients->hasPages())
                <div class="ms-auto">{{ $recipients->links() }}</div>
            @endif
        </div>
    </div>
@endsection
