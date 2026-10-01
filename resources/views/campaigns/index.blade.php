@extends('layouts.app')

@section('title', 'Campaigns')

@section('content')
    <x-page-header title="Campaigns" icon="bi-send" subtitle="Email and SMS campaigns to leads and extra contacts.">
        @can('create', App\Models\Campaign::class)
            <x-slot:actions>
                <a href="{{ route('campaigns.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> New Campaign
                </a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    @if ($awaitingCount && $currentStatus !== App\Enums\CampaignStatus::AwaitingApproval)
        <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2 small">
            <i class="bi bi-hourglass-split"></i>
            <strong>{{ $awaitingCount }} campaign(s) awaiting approval.</strong>
            @if (auth()->user()->isSuperAdmin())
                Review them — nothing is sent until you approve.
            @else
                A Super Admin will review them before anything is sent.
            @endif
            <a href="{{ route('campaigns.index', ['status' => App\Enums\CampaignStatus::AwaitingApproval->value]) }}" class="btn btn-sm btn-warning ms-auto">Show them</a>
        </div>
    @endif

    @if ($currentStatus)
        <div class="small mb-2">
            Showing <span class="badge {{ $currentStatus->badgeClass() }}">{{ $currentStatus->label() }}</span> only ·
            <a href="{{ route('campaigns.index') }}">Show all</a>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Campaign</th>
                        <th>Status</th>
                        <th class="text-center">Recipients</th>
                        <th>Delivery</th>
                        <th>Created By</th>
                        <th>When</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($campaigns as $campaign)
                        <tr>
                            <td class="small">
                                <a href="{{ route('campaigns.show', $campaign) }}" class="fw-semibold text-decoration-none">{{ $campaign->name }}</a>
                                <div class="text-muted"><i class="bi {{ $campaign->channel->icon() }}"></i> {{ $campaign->channel->label() }}</div>
                            </td>
                            <td>
                                <span class="badge {{ $campaign->status->badgeClass() }}">{{ $campaign->status->label() }}</span>
                                @if ($campaign->batch_count > 1 && $campaign->current_batch && in_array($campaign->status, [App\Enums\CampaignStatus::Sending, App\Enums\CampaignStatus::Paused], true))
                                    <div class="small text-muted text-nowrap">Batch {{ $campaign->current_batch }} of {{ $campaign->batch_count }}</div>
                                @endif
                            </td>
                            <td class="small text-center" style="min-width: 90px;">
                                {{ number_format($campaign->recipient_count) }}
                                @php $progress = $campaign->recipient_count ? ($campaign->sent_count + $campaign->delivered_count + $campaign->failed_count + $campaign->cancelled_count) / $campaign->recipient_count * 100 : 0; @endphp
                                <div class="progress mt-1" style="height: 4px;" role="progressbar" aria-label="Progress" aria-valuenow="{{ round($progress) }}" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar" style="width: {{ $progress }}%"></div>
                                </div>
                            </td>
                            <td class="small">
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach (App\Enums\CampaignRecipientStatus::cases() as $status)
                                        @if ($campaign->{$status->value.'_count'})
                                            <span class="badge {{ $status->badgeClass() }}">{{ number_format($campaign->{$status->value.'_count'}) }} {{ strtolower($status === App\Enums\CampaignRecipientStatus::Delivered && $campaign->isEmail() ? 'Opened' : $status->label()) }}</span>
                                        @endif
                                    @endforeach
                                </div>
                            </td>
                            <td class="small text-muted">{{ $campaign->creator?->name ?? '—' }}</td>
                            <td class="small text-muted text-nowrap">
                                @if ($campaign->scheduled_at && $campaign->status === App\Enums\CampaignStatus::Pending)
                                    <i class="bi bi-clock"></i> {{ $campaign->scheduled_at->format('M d, Y g:i A') }}
                                @else
                                    {{ $campaign->created_at->format('M d, Y g:i A') }}
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($campaign->isAwaitingApproval() && auth()->user()->isSuperAdmin())
                                    <a href="{{ route('campaigns.show', $campaign) }}" class="btn btn-sm btn-warning"><i class="bi bi-eye"></i> Review</a>
                                @else
                                    <a href="{{ route('campaigns.show', $campaign) }}" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-empty-state icon="bi-send" title="No campaigns yet" description="Campaigns you send will appear here with their delivery status." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($campaigns->hasPages())
            <div class="card-footer bg-white">{{ $campaigns->links() }}</div>
        @endif
    </div>
@endsection
