@extends('layouts.app')

@section('title', 'Import Contacts')

@php
    $result = session('importResult');
@endphp

@section('content')
    <x-page-header title="Import Contacts" icon="bi-upload" subtitle="Add many contacts at once — paste rows from Excel or Google Sheets, or upload a file.">
        <x-slot:actions>
            <a href="{{ route('contacts.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Contacts</a>
        </x-slot:actions>
    </x-page-header>

    @if ($result)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-4 align-items-baseline">
                    <div><span class="fs-3 fw-semibold text-success">{{ number_format($result['imported']) }}</span> <span class="text-muted small">imported</span></div>
                    <div><span class="fs-3 fw-semibold {{ $result['skipped_count'] ? 'text-warning-emphasis' : 'text-muted' }}">{{ number_format($result['skipped_count']) }}</span> <span class="text-muted small">skipped</span></div>
                    <div class="text-muted small">of {{ number_format($result['total']) }} row(s)</div>
                    @if ($result['imported'])
                        <a href="{{ route('contacts.index') }}" class="btn btn-sm btn-primary ms-auto">View contacts</a>
                    @endif
                </div>
                @if ($result['skipped_count'])
                    <div class="table-responsive mt-3" style="max-height: 360px; overflow-y: auto;">
                        <table class="table table-sm small align-middle mb-0">
                            <thead class="table-light" style="position: sticky; top: 0;">
                                <tr><th>Row</th><th>Values</th><th>Why it was skipped</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($result['skipped'] as $skip)
                                    <tr>
                                        <td class="text-muted">{{ $skip['row'] }}</td>
                                        <td class="text-break">{{ $skip['value'] ?: '—' }}</td>
                                        <td class="text-danger">{{ $skip['reason'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($result['skipped_count'] > count($result['skipped']))
                        <div class="small text-muted mt-1">…and {{ number_format($result['skipped_count'] - count($result['skipped'])) }} more.</div>
                    @endif
                @endif
            </div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <form method="POST" action="{{ route('contacts.import.store') }}" class="card border-0 shadow-sm h-100"
                  x-data="{ rows: @js(old('rows', '')), get count() { return this.rows.split(/\r\n|\r|\n/).filter((l) => l.trim() !== '').length; } }">
                @csrf
                <div class="card-header bg-white fw-semibold"><i class="bi bi-clipboard"></i> Paste rows</div>
                <div class="card-body">
                    <p class="small text-muted mb-2">
                        Copy the cells in Excel or Google Sheets and paste them here — one contact per row.
                        Columns: <strong>Company</strong>, <strong>Name</strong>, <strong>Email</strong>, <strong>Phone</strong>.
                        A header row is recognised, so the columns can be in any order; without one, email and phone columns are spotted automatically.
                        Any cell can be left blank.
                    </p>
                    <textarea name="rows" x-model="rows" rows="12" class="form-control font-monospace small @error('rows') is-invalid @enderror" placeholder="Company&#9;Name&#9;Email&#9;Phone&#10;Acme Pvt. Ltd.&#9;Ram Sharma&#9;ram@acme.com.np&#9;9800000000&#10;&#9;Sita Thapa&#9;&#9;9811111111" spellcheck="false"></textarea>
                    @error('rows')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text" x-show="count" x-cloak><span x-text="count"></span> line(s) — up to {{ number_format($maxRows) }} at a time.</div>
                </div>
                <div class="card-footer bg-white">
                    <button type="submit" class="btn btn-primary" :disabled="!count"><i class="bi bi-upload"></i> Import pasted rows</button>
                </div>
            </form>
        </div>

        <div class="col-lg-5">
            <form method="POST" action="{{ route('contacts.import.store') }}" enctype="multipart/form-data" class="card border-0 shadow-sm mb-3">
                @csrf
                <div class="card-header bg-white fw-semibold"><i class="bi bi-file-earmark-spreadsheet"></i> Upload a file</div>
                <div class="card-body">
                    <p class="small text-muted mb-2">.xlsx, .xls or .csv with a header row (Company Name, Name, Email, Phone). Start from the template to be sure the columns line up.</p>
                    <a href="{{ route('contacts.import.template') }}" class="btn btn-outline-secondary btn-sm mb-3"><i class="bi bi-file-earmark-arrow-down"></i> Download template</a>
                    <input type="file" name="file" class="form-control @error('file') is-invalid @enderror" accept=".xlsx,.xls,.csv" required aria-label="Spreadsheet file">
                    @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="card-footer bg-white">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Upload &amp; import</button>
                </div>
            </form>

            <div class="card border-0 shadow-sm">
                <div class="card-body small">
                    <div class="fw-semibold mb-1">What gets skipped</div>
                    <ul class="mb-0 ps-3 text-muted">
                        <li>Rows with nothing in any of the four columns</li>
                        <li>Invalid emails or phone numbers</li>
                        <li>Emails already saved as a contact, or repeated in the same import</li>
                    </ul>
                    <div class="text-muted mt-2">Everything else is added — every skipped row is listed with the reason, so you can fix and re-import just those.</div>
                </div>
            </div>
        </div>
    </div>
@endsection
