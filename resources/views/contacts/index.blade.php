@extends('layouts.app')

@section('title', 'Contacts')

@php
    $formErrors = $errors->hasAny([...App\Models\Contact::FIELDS, 'contact']);
    $chips = [
        null => ['All', $counts['all']],
        'email' => ['With email', $counts['email']],
        'phone' => ['With phone', $counts['phone']],
        'mine' => ['Added by me', $counts['mine']],
    ];
    $isFiltered = $filters['search'] !== '' || $filters['filter'];
@endphp

@section('content')
    <div x-data="contactsPage({
            storeUrl: @js(route('contacts.store')),
            updateUrl: @js(route('contacts.update', '__ID__')),
            campaignUrl: @js(route('campaigns.create')),
            reopen: @js($formErrors || session('contactFormOpen')),
            old: @js($formErrors ? collect(App\Models\Contact::FIELDS)->mapWithKeys(fn ($f) => [$f => old($f, '')]) : null),
            oldId: @js($formErrors ? old('_contact_id') : null),
            pageIds: @js($contacts->getCollection()->modelKeys()),
         })">
        <x-page-header title="Contacts" icon="bi-person-lines-fill" subtitle="Your address book — companies and people you want to reach, whether or not they're leads.">
            <x-slot:actions>
                @can('create', App\Models\Contact::class)
                    @permitted('contacts', 'create')
                        <a href="{{ route('contacts.import') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-upload"></i> Import</a>
                    @endpermitted
                @endcan
                @if ($counts['all'])
                    <a href="{{ route('contacts.export', array_filter(['search' => $filters['search'], 'filter' => $filters['filter']])) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download"></i> Export{{ $isFiltered ? ' filtered' : '' }}</a>
                @endif
                @can('create', App\Models\Contact::class)
                    @permitted('contacts', 'create')
                        <button type="button" class="btn btn-primary btn-sm" @click="openCreate()"><i class="bi bi-plus-lg"></i> Add Contact</button>
                    @endpermitted
                @endcan
            </x-slot:actions>
        </x-page-header>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-2">
                <form method="GET" action="{{ route('contacts.index') }}" class="row g-2 align-items-center">
                    @if ($filters['filter'])
                        <input type="hidden" name="filter" value="{{ $filters['filter'] }}">
                    @endif
                    <div class="col-12 col-md">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="search" name="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Search company, name, email or phone…" aria-label="Search contacts">
                        </div>
                    </div>
                    <div class="col-8 col-md-auto">
                        <select name="sort" class="form-select form-select-sm" aria-label="Sort" onchange="this.form.submit()">
                            <option value="newest" @selected($filters['sort'] === 'newest')>Newest first</option>
                            <option value="company" @selected($filters['sort'] === 'company')>Company A–Z</option>
                            <option value="name" @selected($filters['sort'] === 'name')>Name A–Z</option>
                        </select>
                    </div>
                    <div class="col-4 col-md-auto">
                        <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Search</button>
                    </div>
                </form>
                <div class="d-flex flex-wrap gap-1 mt-2">
                    @foreach ($chips as $key => [$label, $count])
                        <a href="{{ route('contacts.index', array_filter(['filter' => $key ?: null, 'search' => $filters['search'], 'sort' => $filters['sort'] !== 'newest' ? $filters['sort'] : null])) }}"
                           class="btn btn-sm rounded-pill {{ $filters['filter'] === ($key ?: null) ? 'btn-primary' : 'btn-outline-secondary' }}">
                            {{ $label }} <span class="badge {{ $filters['filter'] === ($key ?: null) ? 'bg-light text-dark' : 'bg-secondary-subtle text-secondary-emphasis' }}">{{ number_format($count) }}</span>
                        </a>
                    @endforeach
                    @if ($isFiltered)
                        <a href="{{ route('contacts.index') }}" class="btn btn-sm btn-link">Clear</a>
                    @endif
                </div>
            </div>
        </div>

        {{-- Bulk actions for the ticked rows. --}}
        <div class="alert alert-primary d-flex flex-wrap align-items-center gap-2 py-2 small" x-show="selected.length" x-cloak>
            <strong x-text="`${selected.length} selected`"></strong>
            @if ($canCampaign)
                <a :href="campaignLink('email')" class="btn btn-sm btn-primary"><i class="bi bi-envelope"></i> Send email</a>
                <a :href="campaignLink('sms')" class="btn btn-sm btn-outline-primary"><i class="bi bi-chat-dots"></i> Send SMS</a>
            @endif
            @permitted('contacts', 'delete')
                <form method="POST" action="{{ route('contacts.bulk-destroy') }}" class="d-inline" data-confirm-delete data-confirm-title="Delete the selected contacts?" data-confirm-text="Contacts you added (or any, for Managers and Super Admins) are deleted." data-confirm-button-text="Delete">
                    @csrf
                    <template x-for="id in selected" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Delete</button>
                </form>
            @endpermitted
            <button type="button" class="btn btn-sm btn-link ms-auto" @click="selected = []">Clear selection</button>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 36px;">
                                <input type="checkbox" class="form-check-input" aria-label="Select all on this page" :checked="allOnPageSelected" @change="toggleAll()" @if ($contacts->isEmpty()) disabled @endif>
                            </th>
                            <th>Company</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th class="d-none d-lg-table-cell">Added</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($contacts as $contact)
                            <tr :class="selected.includes({{ $contact->id }}) && 'table-active'">
                                <td><input type="checkbox" class="form-check-input" value="{{ $contact->id }}" x-model.number="selected" aria-label="Select {{ $contact->displayName() }}"></td>
                                <td class="small fw-semibold">{{ $contact->company_name ?? '—' }}</td>
                                <td class="small">{{ $contact->name ?? '—' }}</td>
                                <td class="small text-break">
                                    @if ($contact->email)
                                        <a href="mailto:{{ $contact->email }}" class="text-decoration-none">{{ $contact->email }}</a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="small text-nowrap">
                                    @if ($contact->phone)
                                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact->phone) }}" class="text-decoration-none">{{ $contact->phone }}</a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="small text-muted d-none d-lg-table-cell text-nowrap" title="{{ $contact->created_at?->format('M d, Y g:i A') }}">
                                    {{ $contact->created_at?->format('M d, Y') }}
                                    <div>{{ $contact->creator?->name ?? '—' }}</div>
                                </td>
                                <td class="text-end text-nowrap">
                                    @can('update', $contact)
                                        @permitted('contacts', 'update')
                                            <button type="button" class="btn btn-sm btn-outline-secondary" title="Edit" aria-label="Edit {{ $contact->displayName() }}"
                                                    @click="openEdit(@js($contact->only(['id', 'company_name', 'name', 'email', 'phone'])))"><i class="bi bi-pencil"></i></button>
                                        @endpermitted
                                    @endcan
                                    @can('delete', $contact)
                                        @permitted('contacts', 'delete')
                                            <form action="{{ route('contacts.destroy', $contact) }}" method="POST" class="d-inline" data-confirm-delete data-confirm-title="Delete this contact?" data-confirm-text="{{ $contact->displayName() }} will be removed from Contacts.">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" title="Delete" aria-label="Delete {{ $contact->displayName() }}"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endpermitted
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    @if ($isFiltered)
                                        <x-empty-state icon="bi-search" title="No contacts match" description="Try a different search or filter." />
                                    @else
                                        <x-empty-state icon="bi-person-lines-fill" title="No contacts yet" description="Add contacts one at a time, or import a list from Excel or Google Sheets." />
                                        @if (auth()->user()->can('create', App\Models\Contact::class) && auth()->user()->hasPermission('contacts', 'create'))
                                            <div class="text-center pb-3 d-flex justify-content-center gap-2">
                                                <button type="button" class="btn btn-primary btn-sm" @click="openCreate()"><i class="bi bi-plus-lg"></i> Add Contact</button>
                                                <a href="{{ route('contacts.import') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-upload"></i> Import</a>
                                            </div>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white d-flex flex-wrap align-items-center gap-2">
                <span class="small text-muted">{{ number_format($contacts->total()) }} contact(s)</span>
                @if ($canCampaign && $counts['all'] && ! $isFiltered)
                    <span class="small text-muted">·
                        <a href="{{ route('campaigns.create', ['channel' => 'email', 'audience' => 'none', 'all_contacts' => 1]) }}" class="text-decoration-none">Email all contacts</a> ·
                        <a href="{{ route('campaigns.create', ['channel' => 'sms', 'audience' => 'none', 'all_contacts' => 1]) }}" class="text-decoration-none">SMS all contacts</a>
                    </span>
                @endif
                @if ($contacts->hasPages())
                    <div class="ms-auto">{{ $contacts->links() }}</div>
                @endif
            </div>
        </div>

        {{-- Add / edit — one modal for both. --}}
        <div class="modal fade" id="contactModal" tabindex="-1" aria-labelledby="contactModalTitle" aria-hidden="true" x-ref="modal">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" :action="action" class="modal-content" novalidate>
                    @csrf
                    <template x-if="editingId"><input type="hidden" name="_method" value="PUT"></template>
                    <input type="hidden" name="_contact_id" :value="editingId ?? ''">
                    @foreach (['search', 'filter', 'sort'] as $keep)
                        @if (! empty($filters[$keep]) && $filters[$keep] !== 'newest')
                            <input type="hidden" name="{{ $keep }}" value="{{ $filters[$keep] }}">
                        @endif
                    @endforeach

                    <div class="modal-header">
                        <h5 class="modal-title" id="contactModalTitle" x-text="editingId ? 'Edit Contact' : 'Add Contact'">Add Contact</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted mb-3">All fields are optional — fill in whatever you have.</p>
                        @error('contact')<div class="alert alert-danger small py-2">{{ $message }}</div>@enderror

                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="contactCompany">Company name</label>
                            <input type="text" id="contactCompany" name="company_name" x-model="form.company_name" x-ref="firstField" maxlength="255" class="form-control @error('company_name') is-invalid @enderror" placeholder="Acme Pvt. Ltd." autocomplete="organization">
                            @error('company_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="contactName">Person name</label>
                            <input type="text" id="contactName" name="name" x-model="form.name" maxlength="255" class="form-control @error('name') is-invalid @enderror" placeholder="Ram Sharma" autocomplete="name">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-7">
                                <label class="form-label small fw-semibold" for="contactEmail">Email</label>
                                <input type="email" id="contactEmail" name="email" x-model="form.email" maxlength="255" class="form-control @error('email') is-invalid @enderror" placeholder="ram@acme.com.np" autocomplete="email" inputmode="email">
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-5">
                                <label class="form-label small fw-semibold" for="contactPhone">Phone no.</label>
                                <input type="tel" id="contactPhone" name="phone" x-model="form.phone" maxlength="30" class="form-control @error('phone') is-invalid @enderror" placeholder="9800000000" autocomplete="tel" inputmode="tel">
                                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_another" value="1" class="btn btn-outline-primary" x-show="!editingId" :disabled="isEmpty">Save &amp; add another</button>
                        <button type="submit" class="btn btn-primary" :disabled="isEmpty" x-text="editingId ? 'Save changes' : 'Save contact'">Save contact</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
