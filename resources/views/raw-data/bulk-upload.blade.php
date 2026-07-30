@extends('layouts.app')

@section('title', 'Bulk Upload')

@section('content')
    <x-page-header title="Bulk Upload Raw Data" icon="bi-upload" subtitle="Paste from a spreadsheet or upload a file to import multiple raw data contacts at once." />

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="paste-tab" data-bs-toggle="tab" data-bs-target="#paste-pane" type="button" role="tab" aria-controls="paste-pane" aria-selected="true">
                <i class="bi bi-clipboard-check"></i> Paste from Spreadsheet
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="file-tab" data-bs-toggle="tab" data-bs-target="#file-pane" type="button" role="tab" aria-controls="file-pane" aria-selected="false">
                <i class="bi bi-file-earmark-spreadsheet"></i> Upload File
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="paste-pane" role="tabpanel" aria-labelledby="paste-tab">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <p class="small text-muted mb-3">
                        Click a cell below, then paste (<kbd>Ctrl</kbd>/<kbd>Cmd</kbd>+<kbd>V</kbd>) a block of cells
                        copied directly from Excel or Google Sheets &mdash; rows and columns fill in automatically
                        starting from that cell. Required: <strong>Contact Person</strong>, <strong>Phone</strong>.
                        Optional: Company Name, Number of Employees, Email, Source, Notes. A row matching an existing entry (by phone or
                        contact person) fills in that entry's missing details instead of creating a duplicate.
                    </p>

                    <form method="POST" action="{{ route('raw-data.bulk-upload.store-paste') }}" x-data="rawDataPasteGrid(8)" @submit.prevent="onSubmit($event)">
                        @csrf
                        <input type="hidden" name="rows" x-ref="rowsInput">

                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <span class="badge bg-secondary-subtle text-secondary-emphasis" x-text="`${filledRows.length} row(s) with data`"></span>
                            <span class="badge bg-success-subtle text-success-emphasis" x-show="validRows.length" x-text="`${validRows.length} ready`"></span>
                            <span class="badge bg-danger-subtle text-danger-emphasis" x-show="invalidCount" x-text="`${invalidCount} need attention`"></span>
                            <div class="ms-auto d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" @click="addRows(5)"><i class="bi bi-plus-lg"></i> Add 5 rows</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" @click="clearAll()"><i class="bi bi-arrow-counterclockwise"></i> Clear all</button>
                            </div>
                        </div>

                        <div class="table-responsive mb-3" style="max-height: 480px;">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 2.5rem;">#</th>
                                        <th>Contact Person</th>
                                        <th>Company Name</th>
                                        <th>Employees</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                        <th>Source</th>
                                        <th>Notes</th>
                                        <th style="width: 2.5rem;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(row, index) in rows" :key="index">
                                        <tr :class="!isRowBlank(row) && Object.keys(rowErrors(row)).length ? 'table-danger' : ''">
                                            <td class="text-muted small text-center" x-text="index + 1"></td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm" x-model="row.contact_person"
                                                       @paste="handlePaste($event, index, 0)"
                                                       :class="rowErrors(row).contact_person ? 'is-invalid' : ''">
                                                <div class="invalid-feedback" x-show="rowErrors(row).contact_person" x-text="rowErrors(row).contact_person"></div>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm" x-model="row.company_name"
                                                       @paste="handlePaste($event, index, 1)">
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm" x-model="row.number_of_employees"
                                                       @paste="handlePaste($event, index, 2)"
                                                       :class="rowErrors(row).number_of_employees ? 'is-invalid' : ''">
                                                <div class="invalid-feedback" x-show="rowErrors(row).number_of_employees" x-text="rowErrors(row).number_of_employees"></div>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm" x-model="row.phone"
                                                       @paste="handlePaste($event, index, 3)"
                                                       :class="rowErrors(row).phone ? 'is-invalid' : ''">
                                                <div class="invalid-feedback" x-show="rowErrors(row).phone" x-text="rowErrors(row).phone"></div>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm" x-model="row.email"
                                                       @paste="handlePaste($event, index, 4)"
                                                       :class="rowErrors(row).email ? 'is-invalid' : ''">
                                                <div class="invalid-feedback" x-show="rowErrors(row).email" x-text="rowErrors(row).email"></div>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm" x-model="row.source"
                                                       @paste="handlePaste($event, index, 5)">
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm" x-model="row.notes"
                                                       @paste="handlePaste($event, index, 6)">
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-link text-danger p-0" @click="removeRow(index)" title="Remove row">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <button type="submit" class="btn btn-primary" :disabled="filledRows.length === 0">
                            <i class="bi bi-check2-circle"></i> Submit <span x-text="filledRows.length"></span> row(s)
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="file-pane" role="tabpanel" aria-labelledby="file-tab">
            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h6 class="fw-semibold mb-2"><i class="bi bi-1-circle"></i> Download the template</h6>
                            <p class="small text-muted">Start from the template so your columns line up exactly with what the importer expects.</p>
                            <a href="{{ route('raw-data.bulk-upload.template') }}" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-file-earmark-arrow-down"></i> Download Template
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h6 class="fw-semibold mb-2"><i class="bi bi-2-circle"></i> Upload your file</h6>
                            <p class="small text-muted mb-3">
                                Required columns: <strong>Contact Person</strong>, <strong>Phone</strong>.
                                Optional: Company Name, Number of Employees, Email, Source, Notes. Accepted formats: .xlsx, .xls, .csv.
                                A row matching an existing entry (by phone or contact person) won't create a
                                duplicate — it fills in that entry's missing Email/Source instead, without
                                overwriting anything already set.
                            </p>
                            <form method="POST" action="{{ route('raw-data.bulk-upload.store') }}" enctype="multipart/form-data">
                                @csrf
                                <input type="file" name="file" class="form-control mb-2" accept=".xlsx,.xls,.csv" required>
                                @error('file')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                                <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Upload &amp; Import</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (session('importFailures') && count(session('importFailures')))
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold text-danger">
                <i class="bi bi-exclamation-triangle"></i> {{ count(session('importFailures')) }} row(s) skipped
            </div>
            <div class="card-body" style="max-height: 420px; overflow-y: auto;">
                @foreach (session('importFailures') as $failure)
                    <div class="border-bottom pb-2 mb-2 small">
                        <span class="fw-semibold">Row {{ $failure->row() }}</span>
                        <span class="text-muted">({{ $failure->attribute() }})</span>
                        <ul class="mb-0 mt-1">
                            @foreach ($failure->errors() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endsection
