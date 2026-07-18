@extends('layouts.app')

@section('title', 'Edit Email Account')

@section('content')
    <x-page-header title="Edit Email Account" icon="bi-envelope-at" :subtitle="$account->email_address" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('email-accounts.update', $account) }}">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Provider</label>
                        <input type="text" class="form-control" value="{{ $account->provider->label() }}" disabled>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Email Address</label>
                        <input type="email" name="email_address" class="form-control" value="{{ old('email_address', $account->email_address) }}" required>
                        @error('email_address')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Display Name</label>
                        <input type="text" name="display_name" class="form-control" value="{{ old('display_name', $account->display_name) }}">
                        @error('display_name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Signature</label>
                        <textarea name="signature" rows="1" class="form-control">{{ old('signature', $account->signature) }}</textarea>
                        @error('signature')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12"><hr><h6 class="fw-semibold small text-muted">SMTP (Outgoing)</h6></div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">SMTP Host</label>
                        <input type="text" name="smtp_host" class="form-control" value="{{ old('smtp_host', $account->smtp_host) }}" required>
                        @error('smtp_host')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">SMTP Port</label>
                        <input type="number" name="smtp_port" class="form-control" value="{{ old('smtp_port', $account->smtp_port) }}" required>
                        @error('smtp_port')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Encryption</label>
                        <select name="smtp_encryption" class="form-select">
                            <option value="tls" @selected(old('smtp_encryption', $account->smtp_encryption->value) === 'tls')>TLS</option>
                            <option value="ssl" @selected(old('smtp_encryption', $account->smtp_encryption->value) === 'ssl')>SSL</option>
                            <option value="none" @selected(old('smtp_encryption', $account->smtp_encryption->value) === 'none')>None</option>
                        </select>
                        @error('smtp_encryption')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12"><hr><h6 class="fw-semibold small text-muted">IMAP (Incoming)</h6></div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">IMAP Host</label>
                        <input type="text" name="imap_host" class="form-control" value="{{ old('imap_host', $account->imap_host) }}">
                        @error('imap_host')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">IMAP Port</label>
                        <input type="number" name="imap_port" class="form-control" value="{{ old('imap_port', $account->imap_port) }}">
                        @error('imap_port')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Encryption</label>
                        <select name="imap_encryption" class="form-select">
                            <option value="ssl" @selected(old('imap_encryption', $account->imap_encryption?->value) === 'ssl')>SSL</option>
                            <option value="tls" @selected(old('imap_encryption', $account->imap_encryption?->value) === 'tls')>TLS</option>
                            <option value="none" @selected(old('imap_encryption', $account->imap_encryption?->value) === 'none')>None</option>
                        </select>
                        @error('imap_encryption')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12"><hr><h6 class="fw-semibold small text-muted">Credentials</h6></div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Username</label>
                        <input type="text" name="username" class="form-control" value="{{ old('username', $account->username) }}" required>
                        @error('username')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Password</label>
                        <input type="password" name="password" class="form-control" autocomplete="new-password" placeholder="Leave blank to keep current password">
                        @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Account</button>
                    <a href="{{ route('email-accounts.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
