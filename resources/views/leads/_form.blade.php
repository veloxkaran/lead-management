@php $lead = $lead ?? null; @endphp
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label small fw-semibold">Company Name *</label>
        <input type="text" name="company_name" value="{{ old('company_name', $lead->company_name ?? '') }}" class="form-control" required>
    </div>
    <div class="col-md-6">
        <label class="form-label small fw-semibold">Contact Person *</label>
        <input type="text" name="contact_person" value="{{ old('contact_person', $lead->contact_person ?? '') }}" class="form-control" required>
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">Email</label>
        <input type="email" name="email" value="{{ old('email', $lead->email ?? '') }}" class="form-control">
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">Phone</label>
        <input type="text" name="phone" value="{{ old('phone', $lead->phone ?? '') }}" class="form-control">
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">Website</label>
        <input type="text" name="website" value="{{ old('website', $lead->website ?? '') }}" class="form-control">
    </div>
    <div class="col-md-8">
        <label class="form-label small fw-semibold">Address</label>
        <input type="text" name="address" value="{{ old('address', $lead->address ?? '') }}" class="form-control">
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">Industry</label>
        <input type="text" name="industry" value="{{ old('industry', $lead->industry ?? '') }}" class="form-control">
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">Number of Employees</label>
        <input type="number" min="0" name="number_of_employees" value="{{ old('number_of_employees', $lead->number_of_employees ?? '') }}" class="form-control">
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">Source</label>
        <input type="text" name="source" value="{{ old('source', $lead->source ?? '') }}" class="form-control" placeholder="Referral, Website, LinkedIn...">
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">Opportunity Cost</label>
        <div class="input-group">
            <span class="input-group-text">{{ \App\Support\Currency::SYMBOL }}</span>
            <input type="number" step="0.01" min="0" name="opportunity_cost" value="{{ old('opportunity_cost', $lead->opportunity_cost ?? '') }}" class="form-control">
        </div>
        <div class="form-text">Target / potential value of this lead.</div>
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">Achieved Cost</label>
        <div class="input-group">
            <span class="input-group-text">{{ \App\Support\Currency::SYMBOL }}</span>
            <input type="number" step="0.01" min="0" name="achieved_cost" value="{{ old('achieved_cost', $lead->achieved_cost ?? 0) }}" class="form-control">
        </div>
        <div class="form-text">Counts toward goal progress once the status is flagged as an achievement.</div>
    </div>
    @if (!$lead)
        <div class="col-md-6">
            <label class="form-label small fw-semibold">Assigned User</label>
            <select name="assigned_user_id" class="form-select" data-select2>
                <option value="">Unassigned</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}" @selected(old('assigned_user_id') == $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label small fw-semibold">Initial Status</label>
            <select name="lead_status_id" class="form-select" data-select2>
                <option value="">Default</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->id }}" @selected(old('lead_status_id') == $status->id)>{{ $status->name }}</option>
                @endforeach
            </select>
        </div>
    @else
        <div class="col-md-6">
            <label class="form-label small fw-semibold">Assigned User</label>
            <select name="assigned_user_id" class="form-select" data-select2>
                <option value="">Unassigned</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}" @selected(old('assigned_user_id', $lead->assigned_user_id) == $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div class="col-md-6">
        <label class="form-label small fw-semibold">Business Details</label>
        <textarea name="business_details" rows="3" class="form-control">{{ old('business_details', $lead->business_details ?? '') }}</textarea>
    </div>
    <div class="col-md-6">
        <label class="form-label small fw-semibold">About Client Business</label>
        <textarea name="about_client_business" rows="3" class="form-control">{{ old('about_client_business', $lead->about_client_business ?? '') }}</textarea>
    </div>
</div>
