@extends('layouts.app')

@section('title', 'Industries')

@section('content')
    <x-page-header title="Industries" icon="bi-buildings" subtitle="Industries users pick from when adding or editing a lead.">
        <x-slot:actions>
            <a href="{{ route('industries.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Industry</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Leads</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($industries as $industry)
                        <tr>
                            <td class="fw-semibold">{{ $industry->name }}</td>
                            <td>{{ $industry->leads_count }}</td>
                            <td class="text-end">
                                <a href="{{ route('industries.edit', $industry) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                @if ($industry->leads_count === 0)
                                    <form action="{{ route('industries.destroy', $industry) }}" method="POST" class="d-inline" data-confirm-delete data-confirm-title="Delete industry?" data-confirm-text="This action cannot be undone.">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                @else
                                    <button class="btn btn-sm btn-outline-danger" disabled title="Still used by leads"><i class="bi bi-trash"></i></button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">
                                <x-empty-state icon="bi-buildings" title="No industries yet" description="Add industries so users can pick one when adding or editing a lead." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
