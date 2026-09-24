@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <x-page-header title="Finance Dashboard" icon="bi-speedometer2" subtitle="Activity overview." />

    <x-role-playbook :user="$user" :playbook="$playbook" :quote="$quote" />

    <x-resolution-time-banner :stats="[
        ['icon' => 'bi-life-preserver', 'label' => 'Avg. Support Ticket Solving Time', 'value' => $avgSupportTicketResolutionTime],
        ['icon' => 'bi-clipboard-check', 'label' => 'Avg. Requirement Solving Time', 'value' => $avgRequirementResolutionTime],
    ]" />

    <x-performance-snapshot />

    <x-announcements-widget :announcements="$announcements" />

@endsection
