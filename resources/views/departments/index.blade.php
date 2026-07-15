@extends('layouts.app')

@section('title', 'Departments')

@section('content')
    <x-page-header title="Departments" icon="bi-diagram-2" subtitle="Departments are used to assign SOPs and Job Descriptions to the right employees.">
        <x-slot:actions>
            <a href="{{ route('departments.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Department</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Users</th>
                        <th>Documents</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($departments as $department)
                        <tr>
                            <td class="fw-semibold">{{ $department->name }}</td>
                            <td class="text-muted small">{{ $department->description ?: '—' }}</td>
                            <td>{{ $department->users_count }}</td>
                            <td>{{ $department->policy_documents_count }}</td>
                            <td class="text-end">
                                <a href="{{ route('departments.edit', $department) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                @if ($department->users_count === 0 && $department->policy_documents_count === 0)
                                    <form action="{{ route('departments.destroy', $department) }}" method="POST" class="d-inline" data-confirm-delete data-confirm-title="Delete department?" data-confirm-text="This action cannot be undone.">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                @else
                                    <button class="btn btn-sm btn-outline-danger" disabled title="Department has users or documents"><i class="bi bi-trash"></i></button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-empty-state icon="bi-diagram-2" title="No departments yet" description="Create a department to start assigning SOPs and Job Descriptions." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
