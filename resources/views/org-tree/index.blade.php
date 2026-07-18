@extends('layouts.app')

@section('title', 'Organization Tree')

@section('content')
    <x-page-header title="Organization Tree" icon="bi-diagram-3" subtitle="Expand or collapse each node to explore the reporting hierarchy." />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @if (count($tree))
                <ul class="list-unstyled mb-0">
                    @foreach ($tree as $root)
                        <x-org-tree-node :node="$root" />
                    @endforeach
                </ul>
            @else
                <x-empty-state icon="bi-diagram-3" title="No reporting hierarchy to show yet" description="Assign reporting managers from the Users page to build the tree." />
            @endif
        </div>
    </div>
@endsection
