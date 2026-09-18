@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <x-page-header title="Finance Dashboard" icon="bi-speedometer2" subtitle="Activity overview." />

    <x-role-playbook :user="$user" :playbook="$playbook" :quote="$quote" />

    <x-resolution-time-banner :stats="[
        ['icon' => 'bi-life-preserver', 'label' => 'Avg. Support Ticket Solving Time', 'value' => $avgSupportTicketResolutionTime],
        ['icon' => 'bi-clipboard-check', 'label' => 'Avg. Requirement Solving Time', 'value' => $avgRequirementResolutionTime],
    ]" />

    <x-whats-new-today
        :filters="$whatsNewFilters"
        :new-leads-by-status="$newLeadsByStatus"
        :new-raw-data-count="$newRawDataCount"
        :converted-raw-data-count="$convertedRawDataCount"
        :tickets-raised-count="$ticketsRaisedCount"
        :tickets-solved-count="$ticketsSolvedCount"
        :new-requirements-count="$newRequirementsCount"
    />

    <x-performance-snapshot
        :snapshot-period="$snapshotPeriod"
        :link-filters="$whatsNewFilters"
        :tickets-created="$ticketsCreatedSnapshot"
        :tickets-solved="$ticketsSolvedSnapshot"
        :tickets-avg-solving-time="$ticketsAvgSolvingTimeSnapshot"
        :requirements-created="$requirementsCreatedSnapshot"
        :requirements-closed="$requirementsClosedSnapshot"
        :requirements-avg-closing-time="$requirementsAvgClosingTimeSnapshot"
        :leads-generated="$leadsGeneratedSnapshot"
        :leads-converted="$leadsConvertedSnapshot"
        :leads-conversion-ratio="$leadsConversionRatioSnapshot"
    />

    <div class="row g-3 mt-0">
        <div class="col-12">
            <x-activity-feed-widget />
        </div>
    </div>
@endsection
