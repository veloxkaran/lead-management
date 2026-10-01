@extends('layouts.app')

@section('title', 'Campaign Setup')

@php
    $smsFields = collect(\App\Support\CampaignSettings::SMS_KEYS)->mapWithKeys(fn ($key) => [$key => old($key, $values[$key])]);
    $smsFields['sms_method'] = $smsFields['sms_method'] ?: 'POST';
    $smsFields['sms_format'] = $smsFields['sms_format'] ?: 'form';
    $smsFields['sms_auth_mode'] = $smsFields['sms_auth_mode'] ?: 'param';
@endphp

@section('content')
    <x-page-header title="Campaign Setup" icon="bi-sliders" subtitle="Who campaign email comes from, its signature and sending speed — and your SMS gateway." />

    @if ($appUrlIsLocal)
        <div class="alert alert-warning small">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>APP_URL is still <code>{{ config('app.url') }}</code>.</strong>
            Set it in <code>.env</code> to this app's public address (e.g. <code>APP_URL=https://crm.yourcompany.com</code>) —
            otherwise email "Delivered" (open) tracking and SMS delivery reports can't reach the app.
        </div>
    @endif

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'email' ? 'active' : '' }}" href="{{ route('campaign-setup.edit') }}" @if ($tab === 'email') aria-current="page" @endif><i class="bi bi-envelope"></i> Email</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'sms' ? 'active' : '' }}" href="{{ route('campaign-setup.edit', ['tab' => 'sms']) }}" @if ($tab === 'sms') aria-current="page" @endif><i class="bi bi-chat-dots"></i> SMS</a>
        </li>
    </ul>

    @if ($tab === 'email')
        @php
            $emailFields = collect(\App\Support\CampaignSettings::EMAIL_KEYS)->mapWithKeys(fn ($key) => [$key => old($key, $email[$key])]);
        @endphp

        <form method="POST" action="{{ route('campaign-setup.update-email') }}"
              x-data="{
                  f: @js($emailFields),
                  sample: 2000,
                  get plan() { return campaignSendingPlan(this.sample, { batch_size: this.f.campaign_batch_size, per_minute: this.f.campaign_emails_per_minute, pause_minutes: this.f.campaign_batch_pause_minutes }); },
              }">
            @csrf
            @method('PUT')

            <div class="row g-3">
                <div class="col-xl-7">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-white fw-semibold"><i class="bi bi-person-badge"></i> Sender</div>
                        <div class="card-body">
                            <label class="form-label small fw-semibold d-block">Send campaign email through</label>
                            @foreach ($emailModes as $mode => $label)
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="campaign_email_mode" id="mode_{{ $mode }}" value="{{ $mode }}" x-model="f.campaign_email_mode">
                                    <label class="form-check-label small" for="mode_{{ $mode }}">{{ $label }}</label>
                                </div>
                            @endforeach
                            @error('campaign_email_mode')<div class="text-danger small">{{ $message }}</div>@enderror

                            <div class="mt-3" x-show="f.campaign_email_mode === 'account'" x-cloak>
                                <label class="form-label small fw-semibold" for="emailAccount">Email Account</label>
                                <select id="emailAccount" name="campaign_email_account_id" x-model="f.campaign_email_account_id" class="form-select @error('campaign_email_account_id') is-invalid @enderror">
                                    <option value="">Choose an account…</option>
                                    @foreach ($emailAccounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->email_address }}{{ $account->user ? ' — '.$account->user->name : '' }}</option>
                                    @endforeach
                                </select>
                                @error('campaign_email_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">Email Accounts are added under <a href="{{ route('email-accounts.index') }}">Account → Email Accounts</a>. Mail always goes out as the account's own address.</div>
                            </div>

                            <div class="row g-2 mt-1" x-show="f.campaign_email_mode === 'smtp'" x-cloak>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold" for="smtpHost">SMTP server</label>
                                    <input type="text" id="smtpHost" name="campaign_smtp_host" x-model="f.campaign_smtp_host" class="form-control @error('campaign_smtp_host') is-invalid @enderror" placeholder="mail.yourdomain.com">
                                    @error('campaign_smtp_host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label small fw-semibold" for="smtpPort">Port</label>
                                    <input type="number" id="smtpPort" name="campaign_smtp_port" x-model="f.campaign_smtp_port" class="form-control @error('campaign_smtp_port') is-invalid @enderror" placeholder="465">
                                    @error('campaign_smtp_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label small fw-semibold" for="smtpEnc">Encryption</label>
                                    <select id="smtpEnc" name="campaign_smtp_encryption" x-model="f.campaign_smtp_encryption" class="form-select @error('campaign_smtp_encryption') is-invalid @enderror">
                                        <option value="">Choose…</option>
                                        @foreach ($encryptions as $encryption)
                                            <option value="{{ $encryption->value }}">{{ $encryption->label() }}</option>
                                        @endforeach
                                    </select>
                                    @error('campaign_smtp_encryption')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold" for="smtpUser">Username</label>
                                    <input type="text" id="smtpUser" name="campaign_smtp_username" x-model="f.campaign_smtp_username" class="form-control" autocomplete="off" placeholder="campaigns@yourdomain.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold" for="smtpPass">Password</label>
                                    <input type="password" id="smtpPass" name="campaign_smtp_password" class="form-control" autocomplete="new-password" placeholder="{{ $hasSmtpPassword ? '•••••••• saved — leave blank to keep' : 'Mailbox password' }}">
                                    @if ($hasSmtpPassword)
                                        <div class="form-check mt-1">
                                            <input class="form-check-input" type="checkbox" name="clear_campaign_smtp_password" value="1" id="clearSmtpPass">
                                            <label class="form-check-label small" for="clearSmtpPass">Remove saved password</label>
                                        </div>
                                    @endif
                                </div>
                                <div class="col-12 form-text mt-0">Port 465 = SSL, 587 = TLS (STARTTLS). Stored encrypted; never shown again. Use a mailbox on your own domain made for campaigns, so a spam complaint never affects your everyday mailbox.</div>
                            </div>

                            <hr>

                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold" for="fromName">From name</label>
                                    <input type="text" id="fromName" name="campaign_from_name" x-model="f.campaign_from_name" maxlength="100" class="form-control @error('campaign_from_name') is-invalid @enderror" placeholder="{{ config('app.name') }}">
                                    @error('campaign_from_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <div class="form-text">A real person's name ("Ram from Acme") gets opened more and flagged less.</div>
                                </div>
                                <div class="col-md-6" x-show="f.campaign_email_mode !== 'account'">
                                    <label class="form-label small fw-semibold" for="fromAddress">From address</label>
                                    <input type="email" id="fromAddress" name="campaign_from_address" x-model="f.campaign_from_address" class="form-control @error('campaign_from_address') is-invalid @enderror"
                                           :placeholder="f.campaign_email_mode === 'smtp' ? (f.campaign_smtp_username || 'Same as the SMTP username') : @js(config('mail.from.address'))">
                                    @error('campaign_from_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <div class="form-text">Must be on the same domain as the mail server's login, or receivers fail it on SPF/DMARC.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold" for="replyTo">Reply-to address (optional)</label>
                                    <input type="email" id="replyTo" name="campaign_reply_to" x-model="f.campaign_reply_to" class="form-control @error('campaign_reply_to') is-invalid @enderror" placeholder="sales@yourdomain.com">
                                    @error('campaign_reply_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <div class="form-text">Where replies go, if not the From address.</div>
                                </div>
                            </div>

                            <div class="form-text mt-2">Currently saved: <strong>{{ $sender }}</strong></div>

                            <div x-show="f.campaign_email_mode === 'system'">
                                {{-- A Bootstrap collapse, not <details>: WebKit lets a closed <details>' unwrapped
                                     content widen the page on phones; display:none content never does. --}}
                                <button class="btn btn-link btn-sm px-0 mt-2 fw-semibold text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#envMailHelp" aria-expanded="false" aria-controls="envMailHelp">
                                    <i class="bi bi-chevron-down"></i> Using the system mail settings (.env)
                                </button>
                                <div class="collapse small" id="envMailHelp">
                                    <p class="mt-2 mb-1">Set these in <code>.env</code>, then run <code>php artisan config:clear</code>:</p>
<pre class="bg-body-tertiary border rounded p-2 small mb-0" style="white-space: pre-wrap; overflow-wrap: anywhere;">MAIL_MAILER=smtp
MAIL_HOST=mail.yourdomain.com
MAIL_PORT=465          # 465 = SSL, 587 = STARTTLS
MAIL_USERNAME=you@yourdomain.com
MAIL_PASSWORD="your-mailbox-password"
MAIL_FROM_ADDRESS="you@yourdomain.com"
MAIL_FROM_NAME="${APP_NAME}"</pre>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-white fw-semibold"><i class="bi bi-pen"></i> Signature &amp; footer</div>
                        <div class="card-body">
                            @if ($signatureMissingImages)
                                <div class="alert alert-warning small py-2">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    {{ count($signatureMissingImages) }} image(s) in the saved signature can't be found on the server (shown as a broken image below), so emails go out without {{ count($signatureMissingImages) === 1 ? 'it' : 'them' }}.
                                    Remove the broken image, insert it again, and save.
                                </div>
                            @endif
                            <x-rich-text-editor name="campaign_signature" label="Signature" :value="old('campaign_signature', $signature)" placeholder="Your name, title, phone and website"
                                                images :max-bytes="\App\Support\CampaignSignature::MAX_BYTES" :image-sizes="$signatureImageSizes" />
                            <div class="form-text mb-3">
                                Added under every campaign email (it can be left off per campaign). Up to <strong>50 KB including images</strong> —
                                add a logo or photo with the <i class="bi bi-image"></i> button, or paste/drop one in. PNG, JPG, GIF or WebP; images wider than 400px are scaled down to fit.
                                Images are sent inside the email, so they show without "load images". Keep it to a small logo and a link or two — big images look like spam.
                            </div>

                            <label class="form-label small fw-semibold" for="footer">Footer — company name &amp; address</label>
                            <textarea id="footer" name="campaign_footer" x-model="f.campaign_footer" rows="3" maxlength="1000" class="form-control @error('campaign_footer') is-invalid @enderror" placeholder="Acme Pvt. Ltd.&#10;Putalisadak, Kathmandu, Nepal"></textarea>
                            @error('campaign_footer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Shown in small print at the bottom with the unsubscribe link. Anti-spam rules (and Gmail) expect a real postal address in bulk email.</div>

                            <div class="form-check form-switch mt-3">
                                <input type="hidden" name="campaign_track_opens" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" name="campaign_track_opens" value="1" id="trackOpens" @checked($emailFields['campaign_track_opens'] === '1')>
                                <label class="form-check-label small fw-semibold" for="trackOpens">Track opens</label>
                            </div>
                            <div class="form-text">Adds an invisible 1×1 image that marks an email "Delivered" (opened) on the campaign page. Turn it off for the cleanest possible email — then emails stay "Sent".</div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-5">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-white fw-semibold"><i class="bi bi-speedometer2"></i> Sending speed</div>
                        <div class="card-body">
                            <div class="row g-2">
                                <div class="col-4">
                                    <label class="form-label small fw-semibold" for="batchSize">Batch size</label>
                                    <input type="number" id="batchSize" name="campaign_batch_size" x-model="f.campaign_batch_size" min="1" max="1000" class="form-control @error('campaign_batch_size') is-invalid @enderror" required>
                                </div>
                                <div class="col-4">
                                    <label class="form-label small fw-semibold" for="perMinute">Emails / minute</label>
                                    <input type="number" id="perMinute" name="campaign_emails_per_minute" x-model="f.campaign_emails_per_minute" min="1" max="120" class="form-control @error('campaign_emails_per_minute') is-invalid @enderror" required>
                                </div>
                                <div class="col-4">
                                    <label class="form-label small fw-semibold" for="pause">Pause (min)</label>
                                    <input type="number" id="pause" name="campaign_batch_pause_minutes" x-model="f.campaign_batch_pause_minutes" min="0" max="1440" class="form-control @error('campaign_batch_pause_minutes') is-invalid @enderror" required>
                                </div>
                            </div>
                            @foreach (['campaign_batch_size', 'campaign_emails_per_minute', 'campaign_batch_pause_minutes'] as $field)
                                @error($field)<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            @endforeach
                            <div class="form-text">Each batch is spread over a few minutes at the per-minute rate, then sending pauses before the next batch starts.</div>

                            <div class="bg-body-tertiary border rounded p-2 mt-3 small">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <label for="sampleCount" class="mb-0">For</label>
                                    <input type="number" id="sampleCount" x-model.number="sample" min="1" class="form-control form-control-sm" style="max-width: 100px;">
                                    <span>emails:</span>
                                </div>
                                <div><strong x-text="plan.batches"></strong> batch(es), finishing in about <strong x-text="plan.duration"></strong></div>
                                <div class="text-muted">≈ <span x-text="plan.perHour"></span> emails per hour</div>
                            </div>
                            <div class="form-text">Most shared hosts (cPanel) cap outgoing mail per hour — often 100–500. Keep the hourly figure under your host's limit, or emails past it bounce.</div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-3"
                         x-data="{ loading: false, result: null, error: null, check() { this.loading = true; this.error = null; axios.get(@js(route('campaign-setup.domain-check'))).then(({ data }) => this.result = data).catch(() => this.error = 'Could not run the check.').finally(() => this.loading = false); } }">
                        <div class="card-header bg-white fw-semibold d-flex align-items-center gap-2">
                            <span><i class="bi bi-shield-check"></i> Inbox, not spam</span>
                        </div>
                        <div class="card-body small">
                            <p class="mb-2">Every campaign email already includes:</p>
                            <ul class="list-unstyled mb-3">
                                <li><i class="bi bi-check-circle-fill text-success"></i> A plain-text version alongside the HTML</li>
                                <li><i class="bi bi-check-circle-fill text-success"></i> An unsubscribe link and one-click unsubscribe headers (Gmail &amp; Yahoo requirement)</li>
                                <li><i class="bi bi-check-circle-fill text-success"></i> Batched, throttled sending — never one big burst</li>
                                <li><i class="bi bi-check-circle-fill text-success"></i> Unsubscribed addresses removed automatically ({{ number_format($unsubscribeCount) }} so far)</li>
                            </ul>
                            <p class="mb-2">What only you can set up is your domain's DNS. Check <strong>{{ $fromAddress }}</strong> (the saved From address):</p>
                            <button type="button" class="btn btn-outline-primary btn-sm" @click="check()" :disabled="loading">
                                <span x-show="loading" class="spinner-border spinner-border-sm" x-cloak></span>
                                Check SPF, DKIM &amp; DMARC
                            </button>
                            <div class="text-danger mt-2" x-show="error" x-text="error" x-cloak></div>
                            <template x-if="result">
                                <ul class="list-unstyled mt-2 mb-0">
                                    <template x-for="item in result.checks" :key="item.key">
                                        <li class="border-top py-1">
                                            <i class="bi" :class="{ 'bi-check-circle-fill text-success': item.status === 'ok', 'bi-exclamation-triangle-fill text-warning': item.status === 'warn', 'bi-x-circle-fill text-danger': item.status === 'missing' }"></i>
                                            <strong x-text="item.label"></strong>
                                            <div class="text-muted text-break font-monospace" style="font-size: .8em;" x-text="item.detail"></div>
                                        </li>
                                    </template>
                                </ul>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Email Setup</button>
            </div>
        </form>

        <div class="row g-3">
            <div class="col-md-6">
                <form method="POST" action="{{ route('campaign-setup.test-email') }}" class="card border-0 shadow-sm h-100">
                    @csrf
                    <div class="card-header bg-white fw-semibold">Send a test email</div>
                    <div class="card-body">
                        <div class="form-text mb-2">Sent exactly as a campaign email — sender, signature, footer — using the <strong>saved</strong> setup. Save first if you changed it. Try a Gmail and an Outlook address to see if it lands in the inbox.</div>
                        <div class="input-group">
                            <input type="email" name="test_email" class="form-control @error('test_email') is-invalid @enderror" value="{{ old('test_email', auth()->user()->email) }}" required aria-label="Test email address">
                            <button type="submit" class="btn btn-outline-primary">Send test</button>
                        </div>
                        @error('test_email')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </form>
            </div>
        </div>
    @else
    <form method="POST" action="{{ route('campaign-setup.update') }}" x-data="{ f: @js($smsFields), presets: @js($presets), applyPreset(key) { if (this.presets[key]) Object.entries(this.presets[key]).forEach(([k, v]) => { if (k !== 'label') this.f[k] = v; }); } }">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <div class="col-12">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white fw-semibold d-flex flex-wrap align-items-center gap-2">
                        <span><i class="bi bi-chat-dots"></i> SMS gateway</span>
                        <div class="ms-auto d-flex flex-wrap gap-1">
                            @foreach ($presets as $key => $preset)
                                <button type="button" class="btn btn-outline-secondary btn-sm" @click="applyPreset('{{ $key }}')">Use {{ $preset['label'] }} defaults</button>
                            @endforeach
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small fw-semibold" for="smsEndpoint">API endpoint URL</label>
                                <input type="url" id="smsEndpoint" name="sms_endpoint" x-model="f.sms_endpoint" class="form-control @error('sms_endpoint') is-invalid @enderror" placeholder="https://api.provider.com/sms/send">
                                @error('sms_endpoint')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small fw-semibold" for="smsMethod">Method</label>
                                <select id="smsMethod" name="sms_method" x-model="f.sms_method" class="form-select">
                                    <option value="POST">POST</option>
                                    <option value="GET">GET</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small fw-semibold" for="smsFormat">Body format</label>
                                <select id="smsFormat" name="sms_format" x-model="f.sms_format" class="form-select" :disabled="f.sms_method === 'GET'">
                                    <option value="form">Form fields</option>
                                    <option value="json">JSON</option>
                                </select>
                                <input type="hidden" name="sms_format" x-model="f.sms_format" :disabled="f.sms_method !== 'GET'">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold" for="smsAuthMode">Send API key as</label>
                                <select id="smsAuthMode" name="sms_auth_mode" x-model="f.sms_auth_mode" class="form-select">
                                    <option value="param">A request parameter</option>
                                    <option value="header">A header</option>
                                    <option value="bearer">Bearer token (Authorization header)</option>
                                </select>
                            </div>
                            <div class="col-md-6" x-show="f.sms_auth_mode !== 'bearer'">
                                <label class="form-label small fw-semibold" for="smsAuthName" x-text="f.sms_auth_mode === 'header' ? 'Header name' : 'Key parameter name'"></label>
                                <input type="text" id="smsAuthName" name="sms_auth_name" x-model="f.sms_auth_name" class="form-control" :placeholder="f.sms_auth_mode === 'header' ? 'X-API-Key' : 'token'">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold" for="smsApiKey">API key / token</label>
                                <input type="password" id="smsApiKey" name="sms_api_key" class="form-control" autocomplete="new-password" placeholder="{{ $hasApiKey ? '•••••••• saved — leave blank to keep' : 'Paste your key' }}">
                                @if ($hasApiKey)
                                    <div class="form-check mt-1">
                                        <input class="form-check-input" type="checkbox" name="clear_sms_api_key" value="1" id="clearKey">
                                        <label class="form-check-label small" for="clearKey">Remove saved key</label>
                                    </div>
                                @endif
                                <div class="form-text">Stored encrypted; never shown again.</div>
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="form-label small fw-semibold" for="smsTo">Phone number parameter</label>
                                <input type="text" id="smsTo" name="sms_to_param" x-model="f.sms_to_param" class="form-control @error('sms_to_param') is-invalid @enderror" placeholder="to">
                                @error('sms_to_param')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="form-label small fw-semibold" for="smsText">Message parameter</label>
                                <input type="text" id="smsText" name="sms_message_param" x-model="f.sms_message_param" class="form-control @error('sms_message_param') is-invalid @enderror" placeholder="text">
                                @error('sms_message_param')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold" for="smsPrefix">Country code to add</label>
                                <input type="text" id="smsPrefix" name="sms_country_prefix" x-model="f.sms_country_prefix" class="form-control @error('sms_country_prefix') is-invalid @enderror" placeholder="blank = 98XXXXXXXX">
                                @error('sms_country_prefix')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="form-label small fw-semibold" for="smsSenderParam">Sender ID parameter</label>
                                <input type="text" id="smsSenderParam" name="sms_sender_param" x-model="f.sms_sender_param" class="form-control" placeholder="from">
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="form-label small fw-semibold" for="smsSenderId">Sender ID</label>
                                <input type="text" id="smsSenderId" name="sms_sender_id" x-model="f.sms_sender_id" class="form-control" placeholder="Approved sender name">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold" for="smsExtra">Extra fixed parameters</label>
                                <textarea id="smsExtra" name="sms_extra_params" x-model="f.sms_extra_params" rows="1" class="form-control font-monospace small" placeholder="key=value (one per line)"></textarea>
                            </div>
                            <div class="col-12"><hr class="my-1"><div class="small fw-semibold">Recognising success</div></div>
                            <div class="col-6 col-md-4">
                                <label class="form-label small" for="smsSuccessPath">Response field</label>
                                <input type="text" id="smsSuccessPath" name="sms_success_path" x-model="f.sms_success_path" class="form-control" placeholder="e.g. response_code">
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="form-label small" for="smsSuccessValue">…must equal</label>
                                <input type="text" id="smsSuccessValue" name="sms_success_value" x-model="f.sms_success_value" class="form-control" placeholder="e.g. 200">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small" for="smsIdPath">Message ID field</label>
                                <input type="text" id="smsIdPath" name="sms_message_id_path" x-model="f.sms_message_id_path" class="form-control" placeholder="e.g. data.message_id">
                            </div>
                            <div class="col-12 form-text mt-1">Leave the response field blank to treat any HTTP 2xx as success. Nested fields use dots (<code>data.0.id</code>). The message ID is needed to match delivery reports.</div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-check2-all"></i> SMS delivery reports (optional)</div>
                    <div class="card-body">
                        <label class="form-label small fw-semibold" for="dlrUrl">Delivery-report (callback) URL — give this to your SMS provider</label>
                        <div class="input-group input-group-sm">
                            <input type="text" id="dlrUrl" class="form-control font-monospace" value="{{ $dlrUrl }}" readonly onclick="this.select()">
                            <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard?.writeText(document.getElementById('dlrUrl').value)" title="Copy"><i class="bi bi-clipboard"></i></button>
                        </div>
                        <div class="form-text mb-2">Keep it private — the random part is what authorizes the callback.</div>
                        <div class="row g-2">
                            <div class="col-6 col-md-3">
                                <label class="form-label small" for="dlrId">Message ID parameter</label>
                                <input type="text" id="dlrId" name="sms_dlr_id_param" x-model="f.sms_dlr_id_param" class="form-control form-control-sm" placeholder="message_id">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small" for="dlrStatus">Status parameter</label>
                                <input type="text" id="dlrStatus" name="sms_dlr_status_param" x-model="f.sms_dlr_status_param" class="form-control form-control-sm" placeholder="status">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small" for="dlrDelivered">"Delivered" values</label>
                                <input type="text" id="dlrDelivered" name="sms_dlr_delivered_values" x-model="f.sms_dlr_delivered_values" class="form-control form-control-sm" placeholder="delivered,DELIVRD">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small" for="dlrFailed">"Failed" values</label>
                                <input type="text" id="dlrFailed" name="sms_dlr_failed_values" x-model="f.sms_dlr_failed_values" class="form-control form-control-sm" placeholder="failed,UNDELIV">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-4">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save SMS Setup</button>
        </div>
    </form>

    <div class="row g-3">
        <div class="col-md-6">
            <form method="POST" action="{{ route('campaign-setup.test-sms') }}" class="card border-0 shadow-sm h-100">
                @csrf
                <div class="card-header bg-white fw-semibold">Send a test SMS</div>
                <div class="card-body">
                    <div class="form-text mb-2">Uses the <strong>saved</strong> gateway settings — save first if you changed them.</div>
                    <div class="input-group mb-2">
                        <input type="text" name="test_phone" class="form-control @error('test_phone') is-invalid @enderror" value="{{ old('test_phone') }}" placeholder="98XXXXXXXX" required aria-label="Test phone number">
                        <button type="submit" class="btn btn-outline-primary">Send test</button>
                    </div>
                    @error('test_phone')<div class="text-danger small mb-1">{{ $message }}</div>@enderror
                    <input type="text" name="test_message" class="form-control form-control-sm" maxlength="160" value="{{ old('test_message') }}" placeholder="Message (optional)" aria-label="Test message">
                </div>
            </form>
        </div>
    </div>
    @endif
@endsection
