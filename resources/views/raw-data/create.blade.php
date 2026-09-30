@extends('layouts.app')

@section('title', 'Add Raw Data')

@section('content')
    <x-page-header title="Add Raw Data" icon="bi-inbox" subtitle="Quickly capture a contact person and phone number." />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('raw-data.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Contact Person (optional)</label>
                        <input type="text" name="contact_person" class="form-control" value="{{ old('contact_person') }}">
                        @error('contact_person')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6" x-data="similarLeadCheck(@js(route('raw-data.similar-leads')), @js(old('company_name', '')), @js($similarLeads))">
                        <label class="form-label small fw-semibold" for="rawCompanyName">Company Name (optional)</label>
                        <input type="text" id="rawCompanyName" name="company_name" class="form-control" x-model="companyName" @input="check()" autocomplete="off" aria-describedby="similarLeadsBox">
                        @error('company_name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        <div id="similarLeadsBox" x-show="matches.length > 0" x-cloak class="border border-warning-subtle rounded mt-2 small" aria-live="polite">
                            <div class="bg-warning-subtle text-warning-emphasis px-2 py-1 rounded-top">
                                <i class="bi bi-exclamation-triangle"></i> Similar lead(s) already exist:
                            </div>
                            <ul class="list-unstyled mb-0">
                                <template x-for="match in matches" :key="match.id">
                                    <li class="px-2 py-1 border-top d-flex flex-wrap align-items-baseline column-gap-2">
                                        <a :href="match.url" target="_blank" rel="noopener" class="fw-semibold text-break" x-text="match.company_name"></a>
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis" x-text="match.similarity + '% match'"></span>
                                        <span class="text-muted text-break">
                                            <span x-text="match.contact_person"></span><template x-if="match.status"><span> · <span x-text="match.status"></span></span></template> · added <span x-text="match.created_at"></span>
                                        </span>
                                    </li>
                                </template>
                            </ul>
                            <div class="form-check px-2 py-2 border-top mb-0 ms-4">
                                <input class="form-check-input" type="checkbox" name="confirm_similar_leads" value="1" id="confirmSimilarLeads" @checked(old('confirm_similar_leads'))>
                                <label class="form-check-label" for="confirmSimilarLeads">Save anyway — this is a different company</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Number of Employees (optional)</label>
                        <input type="number" min="0" name="number_of_employees" class="form-control" value="{{ old('number_of_employees') }}">
                        @error('number_of_employees')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Phone (optional)</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                        @error('phone')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Email (optional)</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email') }}">
                        @error('email')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Source (optional)</label>
                        <input type="text" name="source" class="form-control" maxlength="20" value="{{ old('source') }}">
                        @error('source')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Notes (optional)</label>
                        <textarea name="notes" rows="3" class="form-control" maxlength="2000">{{ old('notes') }}</textarea>
                        @error('notes')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save</button>
                    <a href="{{ route('raw-data.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
