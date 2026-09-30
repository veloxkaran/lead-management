@extends('layouts.app')

@section('title', 'System Modules')

@section('content')
    <x-page-header title="System Modules" icon="bi-grid-3x3-gap" subtitle="System modules users pick from when adding or editing a requirement.">
        <x-slot:actions>
            <a href="{{ route('system-modules.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Module</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Requirements</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($modules as $module)
                        <tr>
                            <td class="fw-semibold">{{ $module->name }}</td>
                            <td>{{ $module->requirements_count }}</td>
                            <td class="text-end">
                                <a href="{{ route('system-modules.edit', $module) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                @if ($module->requirements_count === 0)
                                    <form action="{{ route('system-modules.destroy', $module) }}" method="POST" class="d-inline" data-confirm-delete data-confirm-title="Delete module?" data-confirm-text="This action cannot be undone.">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                @else
                                    <button class="btn btn-sm btn-outline-danger" disabled title="Still used by requirements"><i class="bi bi-trash"></i></button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">
                                <x-empty-state icon="bi-grid-3x3-gap" title="No system modules yet" description="Add modules so users can pick one when adding or editing a requirement." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
