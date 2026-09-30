@extends('layouts.app')

@section('title', 'Permissions — '.$user->name)

@section('content')
    <x-page-header title="Permissions" icon="bi-shield-lock" :subtitle="$user->name.' · '.$user->role->label()">
        <x-slot:actions>
            @if ($user->hasRestrictedPermissions())
                <form action="{{ route('users.permissions.destroy', $user) }}" method="POST" class="d-inline" data-confirm-delete data-confirm-title="Restore full access?" data-confirm-text="{{ $user->name }} will be able to view, create, edit and delete in every component.">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-counterclockwise"></i> Reset to full access</button>
                </form>
            @endif
            <a href="{{ route('users.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to users</a>
        </x-slot:actions>
    </x-page-header>

    <div class="alert {{ $user->hasRestrictedPermissions() ? 'alert-warning' : 'alert-info' }} small d-flex align-items-start gap-2">
        <i class="bi {{ $user->hasRestrictedPermissions() ? 'bi-shield-exclamation' : 'bi-shield-check' }}"></i>
        <div>
            @if ($user->hasRestrictedPermissions())
                {{ $user->name }} has <strong>restricted access</strong>. Unchecked actions are blocked, and their buttons are hidden.
            @else
                {{ $user->name }} has <strong>full access</strong> (the default). Untick anything you want to take away.
            @endif
            These permissions only remove access. What someone can do inside a component still follows the usual rules, e.g. only the creator or assignee can edit a requirement.
        </div>
    </div>

    <form method="POST" action="{{ route('users.permissions.update', $user) }}" id="permission-matrix">
        @csrf
        @method('PUT')
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="fw-semibold small">Component access</span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-permission-bulk="all"><i class="bi bi-check2-all"></i> Allow all</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-permission-bulk="view"><i class="bi bi-eye"></i> View only</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-permission-bulk="none"><i class="bi bi-x-lg"></i> Revoke all</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Component</th>
                            @foreach (\App\Enums\PermissionAction::cases() as $action)
                                <th class="text-center" style="width: 7rem;">{{ $action->label() }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($modules as $module)
                            <tr data-permission-row>
                                <td><i class="bi {{ $module->icon() }} text-muted me-2"></i>{{ $module->label() }}</td>
                                @foreach (\App\Enums\PermissionAction::cases() as $action)
                                    <td class="text-center">
                                        @if ($module->supports($action))
                                            <input type="checkbox" class="form-check-input" name="permissions[{{ $module->value }}][]" value="{{ $action->value }}" id="perm-{{ $module->value }}-{{ $action->value }}" aria-label="{{ $action->label() }} {{ $module->label() }}" data-permission-action="{{ $action->value }}" @checked($user->hasPermission($module, $action))>
                                        @else
                                            <span class="text-muted small" title="Not applicable">—</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white">
                <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Permissions</button>
                <span class="text-muted small ms-2">Unticking View blocks the whole component.</span>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('permission-matrix');
            if (! form) return;

            // Without View the other actions can't work (the server clears
            // them too), so keep the row honest while editing.
            const syncRow = (row) => {
                const view = row.querySelector('[data-permission-action="view"]');
                row.querySelectorAll('[data-permission-action]:not([data-permission-action="view"])').forEach((box) => {
                    box.disabled = ! view.checked;
                    if (! view.checked) box.checked = false;
                });
            };

            const rows = form.querySelectorAll('[data-permission-row]');
            rows.forEach((row) => {
                syncRow(row);
                row.querySelector('[data-permission-action="view"]').addEventListener('change', () => syncRow(row));
            });

            form.querySelectorAll('[data-permission-bulk]').forEach((button) => {
                button.addEventListener('click', () => {
                    const mode = button.dataset.permissionBulk;
                    rows.forEach((row) => {
                        row.querySelectorAll('[data-permission-action]').forEach((box) => {
                            box.checked = mode === 'all' || (mode === 'view' && box.dataset.permissionAction === 'view');
                        });
                        syncRow(row);
                    });
                });
            });
        })();
    </script>
@endpush
