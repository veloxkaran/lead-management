@extends('layouts.app')

@section('title', 'Add Email Account')

@section('content')
    <x-page-header title="Add Email Account" icon="bi-envelope-at" subtitle="Configure a new email account for sending and receiving." />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('email-accounts.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Provider</label>
                        <select name="provider" id="provider" class="form-select" required>
                            @foreach ($providers as $provider)
                                <option
                                    value="{{ $provider->value }}"
                                    data-smtp-host="{{ $provider->defaultSmtpHost() }}"
                                    data-smtp-port="{{ $provider->defaultSmtpPort() }}"
                                    data-smtp-encryption="{{ $provider->defaultSmtpEncryption()?->value }}"
                                    data-imap-host="{{ $provider->defaultImapHost() }}"
                                    data-imap-port="{{ $provider->defaultImapPort() }}"
                                    data-imap-encryption="{{ $provider->defaultImapEncryption()?->value }}"
                                    @selected(old('provider') === $provider->value)
                                >{{ $provider->label() }}</option>
                            @endforeach
                        </select>
                        @error('provider')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Email Address</label>
                        <input type="email" name="email_address" class="form-control" value="{{ old('email_address') }}" required>
                        @error('email_address')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Display Name</label>
                        <input type="text" name="display_name" class="form-control" value="{{ old('display_name') }}">
                        @error('display_name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Signature</label>
                        <textarea name="signature" rows="1" class="form-control">{{ old('signature') }}</textarea>
                        @error('signature')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12"><hr><h6 class="fw-semibold small text-muted">SMTP (Outgoing)</h6></div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">SMTP Host</label>
                        <input type="text" name="smtp_host" id="smtp_host" class="form-control" value="{{ old('smtp_host') }}" required>
                        @error('smtp_host')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">SMTP Port</label>
                        <input type="number" name="smtp_port" id="smtp_port" class="form-control" value="{{ old('smtp_port', 587) }}" required>
                        @error('smtp_port')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Encryption</label>
                        <select name="smtp_encryption" id="smtp_encryption" class="form-select">
                            <option value="tls" @selected(old('smtp_encryption') === 'tls')>TLS</option>
                            <option value="ssl" @selected(old('smtp_encryption') === 'ssl')>SSL</option>
                            <option value="none" @selected(old('smtp_encryption') === 'none')>None</option>
                        </select>
                        @error('smtp_encryption')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12"><hr><h6 class="fw-semibold small text-muted">IMAP (Incoming)</h6></div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">IMAP Host</label>
                        <input type="text" name="imap_host" id="imap_host" class="form-control" value="{{ old('imap_host') }}">
                        @error('imap_host')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">IMAP Port</label>
                        <input type="number" name="imap_port" id="imap_port" class="form-control" value="{{ old('imap_port', 993) }}">
                        @error('imap_port')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Encryption</label>
                        <select name="imap_encryption" id="imap_encryption" class="form-select">
                            <option value="ssl" @selected(old('imap_encryption') === 'ssl')>SSL</option>
                            <option value="tls" @selected(old('imap_encryption') === 'tls')>TLS</option>
                            <option value="none" @selected(old('imap_encryption') === 'none')>None</option>
                        </select>
                        @error('imap_encryption')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12"><hr><h6 class="fw-semibold small text-muted">Credentials</h6></div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Username</label>
                        <input type="text" name="username" class="form-control" value="{{ old('username') }}" required>
                        @error('username')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Password</label>
                        <input type="password" name="password" class="form-control" autocomplete="new-password" required>
                        <div class="form-text small">Stored encrypted. For Gmail/Microsoft 365, use an app-specific password.</div>
                        @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input type="checkbox" name="is_default" value="1" class="form-check-input" id="is_default" @checked(old('is_default'))>
                            <label class="form-check-label small fw-semibold" for="is_default">Set as default account</label>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Add Account</button>
                    <a href="{{ route('email-accounts.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.getElementById('provider')?.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        const setIfPresent = (id, value) => { if (value) document.getElementById(id).value = value; };
        setIfPresent('smtp_host', opt.dataset.smtpHost);
        setIfPresent('smtp_port', opt.dataset.smtpPort);
        setIfPresent('smtp_encryption', opt.dataset.smtpEncryption);
        setIfPresent('imap_host', opt.dataset.imapHost);
        setIfPresent('imap_port', opt.dataset.imapPort);
        setIfPresent('imap_encryption', opt.dataset.imapEncryption);
    });
</script>
@endpush
