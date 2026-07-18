@props(['node'])

<li class="org-tree-node">
    <div class="d-flex align-items-center gap-2 py-1">
        @if (count($node['children']))
            <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none org-tree-toggle" data-bs-toggle="collapse" data-bs-target="#org-tree-node-{{ $node['user']->id }}" aria-expanded="true">
                <i class="bi bi-caret-down-fill small"></i>
            </button>
        @else
            <span class="d-inline-block" style="width: 1rem;"></span>
        @endif
        <span class="fw-semibold small">{{ $node['user']->name }}</span>
        @if ($node['user']->designation)
            <span class="text-muted small">{{ $node['user']->designation }}</span>
        @endif
        <span class="badge bg-primary-subtle text-primary-emphasis small">{{ $node['user']->role->label() }}</span>
    </div>

    @if (count($node['children']))
        <ul id="org-tree-node-{{ $node['user']->id }}" class="collapse show list-unstyled ps-4 border-start ms-2">
            @foreach ($node['children'] as $child)
                <x-org-tree-node :node="$child" />
            @endforeach
        </ul>
    @endif
</li>
