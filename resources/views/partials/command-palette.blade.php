{{--
    Ctrl/⌘+K quick search (resources/js/command-palette.js).
    "Go to" pages are read from the sidebar at runtime, so they always match
    what this member is allowed to see. Quick actions below carry the same
    @can checks as the matching "Add/New" buttons on each index page, and are
    only shown when their data-section page is in this member's sidebar too.
--}}
<div class="modal fade" id="commandPalette" tabindex="-1" aria-label="Quick search" aria-hidden="true">
    <div class="modal-dialog command-palette-dialog">
        <div class="modal-content">
            <div class="command-palette-search">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input type="text" class="form-control" id="commandPaletteInput"
                    placeholder="Search, go to a page, or create…"
                    autocomplete="off" spellcheck="false"
                    role="combobox" aria-expanded="true" aria-controls="commandPaletteList" aria-autocomplete="list">
                <kbd class="d-none d-sm-inline">Esc</kbd>
            </div>
            <div class="command-palette-results" id="commandPaletteList" role="listbox" aria-label="Results"></div>
            <div class="command-palette-footer d-none d-sm-flex">
                <span><kbd>↑</kbd><kbd>↓</kbd> move</span>
                <span><kbd>Enter</kbd> open</span>
                <span><kbd>/</kbd> search this page</span>
            </div>
        </div>
    </div>

    <template id="commandPaletteActions">
        @can('create', App\Models\Lead::class)
            <a href="{{ route('leads.create') }}" data-section="/leads" data-icon="bi-diagram-3" data-keywords="new lead company client">Add lead</a>
        @endcan
        @can('create', App\Models\Task::class)
            <a href="{{ route('tasks.create') }}" data-section="/tasks" data-icon="bi-list-task" data-keywords="new task todo">New task</a>
        @endcan
        @can('create', App\Models\FollowUp::class)
            <a href="{{ route('follow-ups.create') }}" data-section="/follow-ups" data-icon="bi-bell" data-keywords="new follow up reminder schedule">Schedule follow-up</a>
        @endcan
        @can('create', App\Models\SupportTicket::class)
            <a href="{{ route('support-tickets.create') }}" data-section="/support-tickets" data-icon="bi-life-preserver" data-keywords="new support ticket raise issue">Raise support ticket</a>
        @endcan
        @can('create', App\Models\RawData::class)
            <a href="{{ route('raw-data.create') }}" data-section="/raw-data" data-icon="bi-inbox" data-keywords="new raw data entry prospect">Add raw data</a>
        @endcan
        @can('create', App\Models\KnowledgeBaseItem::class)
            <a href="{{ route('knowledge-base.create') }}" data-section="/knowledge-base" data-icon="bi-journal-richtext" data-keywords="new knowledge base article item">Add knowledge base item</a>
        @endcan
    </template>
</div>
