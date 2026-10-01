@extends('layouts.app')

@section('title', 'New Campaign')

@section('content')
    <x-page-header title="New Campaign" icon="bi-send" subtitle="Send an email or SMS to leads and any extra contacts.">
        <x-slot:actions>
            <a href="{{ route('campaigns.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Campaigns
            </a>
        </x-slot:actions>
    </x-page-header>

    @error('recipients')
        <div class="alert alert-danger small"><i class="bi bi-exclamation-triangle"></i> {{ $message }}</div>
    @enderror
    @error('preview_token')
        <div class="alert alert-warning small"><i class="bi bi-eye"></i> {{ $message }}</div>
    @enderror

    <form method="POST" action="{{ route('campaigns.store') }}" enctype="multipart/form-data"
          x-data="campaignComposer({
              channel: @js(old('channel', $prefill['channel'])),
              audience: @js(old('audience', $prefill['audience'])),
              subject: @js(old('subject', '')),
              message: @js(old('message', '')),
              messageHtml: @js(old('message_html', '')),
              scheduledAt: @js(old('scheduled_at', '')),
              previewUrl: @js(route('campaigns.preview')),
              composePreviewUrl: @js(route('campaigns.compose-preview')),
              sendOptions: @js($sendOptions),
              limits: @js(['files' => \App\Http\Requests\Campaign\StoreCampaignRequest::MAX_FILES, 'file' => \App\Http\Requests\Campaign\StoreCampaignRequest::maxFileBytes(), 'total' => \App\Http\Requests\Campaign\StoreCampaignRequest::maxTotalBytes()]),
          })"
          @input.debounce.600ms="if (['extra_contacts'].includes($event.target.name)) schedulePreview(0)"
          @change="if (! ['name', 'subject', 'message', 'message_html', 'scheduled_at', 'attachments[]'].includes($event.target.name)) schedulePreview()"
          @submit="onSubmit($event)">
        @csrf
        <input type="hidden" name="preview_token" :value="review ? review.token : ''">

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold d-block">Channel *</label>
                            <div class="btn-group" role="group" aria-label="Channel">
                                @foreach ($channels as $channel)
                                    <input type="radio" class="btn-check" name="channel" id="channel_{{ $channel->value }}" value="{{ $channel->value }}" x-model="channel" autocomplete="off">
                                    <label class="btn btn-outline-primary btn-sm" for="channel_{{ $channel->value }}"><i class="bi {{ $channel->icon() }}"></i> {{ $channel->label() }}</label>
                                @endforeach
                            </div>
                            @error('channel')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            @unless ($smsConfigured)
                                <div class="alert alert-warning small mt-2 mb-0 py-2" x-show="channel === 'sms'" x-cloak>
                                    <i class="bi bi-exclamation-triangle"></i> The SMS gateway isn't set up yet, so SMS messages will fail.
                                    @if (auth()->user()->isSuperAdmin())
                                        <a href="{{ route('campaign-setup.edit') }}">Set it up</a>.
                                    @else
                                        Ask a Super Admin to set it up.
                                    @endif
                                </div>
                            @endunless
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="campaignName">Campaign Name *</label>
                            <input type="text" id="campaignName" name="name" value="{{ old('name') }}" maxlength="150" class="form-control @error('name') is-invalid @enderror" required>
                            <div class="form-text">For your reference; recipients don't see it.</div>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="small text-muted mb-3" x-show="channel === 'email'">
                            <i class="bi bi-person-badge"></i> From <strong>{{ $sender }}</strong>
                            @if (auth()->user()->isSuperAdmin())
                                · <a href="{{ route('campaign-setup.edit') }}">Change</a>
                            @endif
                        </div>

                        <div class="mb-3" x-show="channel === 'email'">
                            <label class="form-label small fw-semibold" for="campaignSubject">Email Subject *</label>
                            <input type="text" id="campaignSubject" name="subject" x-model="subject" maxlength="200" class="form-control @error('subject') is-invalid @enderror" :required="channel === 'email'" :disabled="channel !== 'email'">
                            @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Email: rich-text editor (sent as HTML). SMS: plain text with the segment counter. --}}
                        <div class="mb-3" x-show="channel === 'email'" x-ref="bodyEditor">
                            <div class="d-flex flex-wrap align-items-end gap-2 mb-1">
                                <span class="form-label small fw-semibold mb-0">Message *</span>
                                <span class="small text-muted ms-auto">Insert:</span>
                                <button type="button" class="btn btn-outline-secondary btn-sm py-0" @click="insertTag('{{ '{{' }}name}}')">Name</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm py-0" @click="insertTag('{{ '{{' }}company_name}}')">Company</button>
                            </div>
                            <x-rich-text-editor name="message_html" toolbar :min-height="220" :value="old('message_html', '')" placeholder="Hi {{ '{{' }}name}}, …" />
                            <div class="form-text">
                                Bold, lists and links are kept. <code>@{{name}}</code> and <code>@{{company_name}}</code> are filled in for each recipient.
                                Write it like a personal email — plain, personal emails reach the inbox far more often than designed newsletters.
                            </div>
                            @error('message')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3" x-show="channel === 'sms'" x-cloak>
                            <label class="form-label small fw-semibold" for="campaignMessage">Message *</label>
                            <textarea id="campaignMessage" name="message" x-model="message" rows="8" maxlength="{{ \App\Http\Requests\Campaign\StoreCampaignRequest::SMS_MAX }}"
                                      class="form-control @error('message') is-invalid @enderror" :required="channel === 'sms'" :disabled="channel !== 'sms'"></textarea>
                            <div class="form-text">Personalize with <code>@{{name}}</code> and <code>@{{company_name}}</code>.</div>
                            <div class="form-text">
                                <span x-text="sms.length"></span> characters ·
                                <span x-text="sms.segments"></span> SMS
                                <span x-show="sms.segments > 1">per recipient</span>
                                <span x-show="sms.unicode" class="text-warning-emphasis">· Unicode (e.g. Nepali) — 70 characters per SMS</span>
                            </div>
                            @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="alert alert-warning small py-2 mb-3" x-show="spamWarnings.length" x-cloak>
                            <div class="fw-semibold"><i class="bi bi-shield-exclamation"></i> Might look like spam:</div>
                            <ul class="mb-0 ps-3">
                                <template x-for="warning in spamWarnings" :key="warning"><li x-text="warning"></li></template>
                            </ul>
                        </div>

                        <div class="mb-3" x-show="channel === 'email'">
                            <label class="form-label small fw-semibold" for="campaignFiles">Images &amp; PDFs (optional)</label>
                            <input type="file" id="campaignFiles" name="attachments[]" x-ref="files" multiple accept=".png,.jpg,.jpeg,.pdf,image/png,image/jpeg,application/pdf"
                                   class="form-control @if ($errors->has('attachments') || $errors->has('attachments.*')) is-invalid @endif" :disabled="channel !== 'email'" @change="pickFiles()">
                            @if ($errors->has('attachments') || $errors->has('attachments.*'))
                                <div class="invalid-feedback">{{ $errors->first('attachments') ?: $errors->first('attachments.*') }}</div>
                            @endif
                            <ul class="list-unstyled small mt-2 mb-1" x-show="files.length" x-cloak>
                                <template x-for="file in files" :key="file.name">
                                    <li class="d-flex align-items-center gap-2 border-bottom py-1">
                                        <i class="bi" :class="file.type === 'application/pdf' ? 'bi-file-earmark-pdf text-danger' : 'bi-image text-primary'"></i>
                                        <span class="text-break" x-text="file.name"></span>
                                        <span class="text-muted ms-auto text-nowrap" x-text="formatBytes(file.size)"></span>
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis" x-text="file.type === 'application/pdf' ? 'attached' : 'in the email'"></span>
                                    </li>
                                </template>
                            </ul>
                            <div class="d-flex flex-wrap gap-2 align-items-center" x-show="files.length" x-cloak>
                                <span class="small" :class="filesBytes > limits.total ? 'text-danger fw-semibold' : 'text-muted'" x-text="`${files.length} file(s), ${formatBytes(filesBytes)}`"></span>
                                <button type="button" class="btn btn-link btn-sm p-0" @click="clearFiles()">Remove all</button>
                            </div>
                            <div class="alert alert-warning small py-1 px-2 mt-1 mb-0" x-show="filesBytes > 1048576" x-cloak>
                                <i class="bi bi-exclamation-triangle"></i> Every recipient downloads these. Big emails are more likely to land in spam — keep the total under about 1 MB if you can (large photos are shrunk automatically).
                            </div>
                            <div class="form-text">PNG or JPG images appear inside the email under the message; PDFs are attached. Up to {{ \App\Http\Requests\Campaign\StoreCampaignRequest::MAX_FILES }} files, {{ \App\Http\Requests\Campaign\StoreCampaignRequest::megabytes(\App\Http\Requests\Campaign\StoreCampaignRequest::maxFileBytes()) }} each, {{ \App\Http\Requests\Campaign\StoreCampaignRequest::megabytes(\App\Http\Requests\Campaign\StoreCampaignRequest::maxTotalBytes()) }} in total.
                                @if ($errors->any()) <strong>Choose the files again</strong> — the browser can't keep them after an error.@endif
                            </div>
                        </div>

                        <div class="mb-3" x-show="channel === 'email'">
                            @if ($hasSignature)
                                <div class="form-check">
                                    <input type="hidden" name="include_signature" value="0" :disabled="channel !== 'email'">
                                    <input class="form-check-input" type="checkbox" name="include_signature" value="1" id="includeSignature" @checked(old('include_signature', '1') === '1') :disabled="channel !== 'email'">
                                    <label class="form-check-label small" for="includeSignature">Add the signature from Campaign Setup</label>
                                </div>
                            @else
                                <div class="form-text"><i class="bi bi-pen"></i> No signature set up yet{{ auth()->user()->isSuperAdmin() ? '' : ' — ask a Super Admin to add one in Campaign Setup' }}.
                                    @if (auth()->user()->isSuperAdmin())<a href="{{ route('campaign-setup.edit') }}">Add one</a>.@endif
                                </div>
                            @endif
                            <div class="form-text">Every email gets your footer and an unsubscribe link automatically.</div>
                        </div>

                        <div>
                            <label class="form-label small fw-semibold" for="campaignSchedule">Schedule (optional)</label>
                            <input type="datetime-local" id="campaignSchedule" name="scheduled_at" x-model="scheduledAt" class="form-control @error('scheduled_at') is-invalid @enderror" style="max-width: 280px;">
                            <div class="form-text">Leave blank to start sending now. A scheduled campaign stays <strong>Pending</strong> until then and can be cancelled.</div>
                            @error('scheduled_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white fw-semibold">Recipients</div>
                    <div class="card-body">
                        <label class="form-label small fw-semibold">Leads</label>
                        @foreach ($audiences as $audience)
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="radio" name="audience" id="audience_{{ $audience->value }}" value="{{ $audience->value }}" x-model="audience">
                                <label class="form-check-label small" for="audience_{{ $audience->value }}">{{ $audience->label() }}</label>
                            </div>
                        @endforeach
                        @error('audience')<div class="text-danger small">{{ $message }}</div>@enderror

                        <div class="mt-2 ps-4" x-show="audience === 'lead_statuses'" x-cloak>
                            @foreach ($statuses as $status)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="lead_status_ids[]" id="cstatus_{{ $status->id }}" value="{{ $status->id }}" @checked(in_array($status->id, old('lead_status_ids', [])))>
                                    <label class="form-check-label small" for="cstatus_{{ $status->id }}">{{ $status->name }}</label>
                                </div>
                            @endforeach
                            @error('lead_status_ids')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>

                        <div class="mt-2 ps-4" x-show="audience === 'industries'" x-cloak>
                            @forelse ($industries as $industry)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="industries[]" id="cindustry_{{ $industry->id }}" value="{{ $industry->name }}" @checked(in_array($industry->name, old('industries', [])))>
                                    <label class="form-check-label small" for="cindustry_{{ $industry->id }}">{{ $industry->name }}</label>
                                </div>
                            @empty
                                <div class="small text-muted">No industries set up yet.</div>
                            @endforelse
                            @error('industries')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>

                        <div class="mt-3">
                            <label class="form-label small fw-semibold" for="campaignLeads">Add specific leads (optional)</label>
                            <select id="campaignLeads" name="lead_ids[]" class="form-select" multiple data-select2-field data-placeholder="Search leads…">
                                @foreach ($leads as $lead)
                                    <option value="{{ $lead->id }}" @selected(in_array($lead->id, old('lead_ids', [])))>{{ $lead->company_name }} — {{ $lead->contact_person }}</option>
                                @endforeach
                            </select>
                            @error('lead_ids')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>

                        @if ($canSeeContacts)
                            @php
                                $pickedContacts = array_map('intval', old('contact_ids', $prefill['contact_ids']));
                            @endphp
                            <div class="mt-3 pt-3 border-top">
                                <label class="form-label small fw-semibold d-block">Contacts <a href="{{ route('contacts.index') }}" class="fw-normal small ms-1" target="_blank" rel="noopener">open Contacts <i class="bi bi-box-arrow-up-right"></i></a></label>
                                @if ($contacts->isEmpty())
                                    <div class="small text-muted">No contacts saved yet.</div>
                                @else
                                    <div class="form-check mb-2">
                                        <input type="hidden" name="all_contacts" value="0">
                                        <input class="form-check-input" type="checkbox" name="all_contacts" value="1" id="allContacts" @checked(old('all_contacts', $prefill['all_contacts'] ? '1' : '0') === '1')>
                                        <label class="form-check-label small" for="allContacts">All contacts ({{ number_format($contacts->count()) }})</label>
                                    </div>
                                    <label class="form-label small text-muted" for="campaignContacts">…or pick contacts</label>
                                    <select id="campaignContacts" name="contact_ids[]" class="form-select" multiple data-select2-field data-placeholder="Search contacts…">
                                        @foreach ($contacts as $contact)
                                            <option value="{{ $contact->id }}" @selected(in_array($contact->id, $pickedContacts, true))>{{ $contact->displayName() }}{{ $contact->company_name && $contact->name ? ' — '.$contact->company_name : '' }} · {{ $contact->email ?? $contact->phone ?? 'no email/phone' }}</option>
                                        @endforeach
                                    </select>
                                    @error('contact_ids')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                    @if ($pickedContacts && ! old('contact_ids'))
                                        <div class="form-text text-primary"><i class="bi bi-check2-circle"></i> {{ count($pickedContacts) }} contact(s) picked from the Contacts list.</div>
                                    @endif
                                @endif
                            </div>
                        @endif

                        <div class="mt-3">
                            <label class="form-label small fw-semibold" for="campaignExtra">
                                <span x-text="channel === 'email' ? 'Extra email addresses (optional)' : 'Extra phone numbers (optional)'"></span>
                            </label>
                            <textarea id="campaignExtra" name="extra_contacts" rows="5" class="form-control font-monospace small"
                                      :placeholder="channel === 'email' ? 'ram@example.com\nSita Sharma, sita@example.com' : '9800000000\nSita Sharma, 9811111111'">{{ old('extra_contacts') }}</textarea>
                            <div class="form-text">One per line (or separated by commas). Add a name with <code>Name, contact</code>. Contacts already on a lead are linked to it; duplicates are sent once.</div>
                            @error('extra_contacts')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm" aria-live="polite">
                    <div class="card-header bg-white fw-semibold d-flex align-items-center gap-2">
                        Recipient check
                        <span class="spinner-border spinner-border-sm text-secondary" x-show="loading" x-cloak role="status"></span>
                    </div>
                    <div class="card-body">
                        <div class="text-danger small" x-show="previewError" x-text="previewError" x-cloak></div>
                        <template x-if="preview">
                            <div>
                                <div class="d-flex align-items-baseline gap-2 mb-2">
                                    <span class="fs-3 fw-semibold" x-text="preview.total"></span>
                                    <span class="text-muted small">unique recipient(s) will get this <span x-text="channel === 'email' ? 'email' : 'SMS'"></span></span>
                                </div>
                                <ul class="list-unstyled small mb-2">
                                    <li><i class="bi bi-diagram-3 text-muted"></i> <span x-text="preview.from_leads"></span> from leads</li>
                                    <li x-show="preview.from_contacts"><i class="bi bi-person-lines-fill text-muted"></i> <span x-text="preview.from_contacts"></span> from contacts</li>
                                    <li><i class="bi bi-plus-circle text-muted"></i> <span x-text="preview.manual"></span> extra contact(s)<template x-if="preview.linked_to_leads"><span>, <span x-text="preview.linked_to_leads"></span> matched to existing leads</span></template></li>
                                    <li x-show="preview.duplicates" class="text-warning-emphasis"><i class="bi bi-files"></i> <span x-text="preview.duplicates"></span> duplicate(s) removed</li>
                                    <li x-show="preview.unsubscribed" class="text-secondary"><i class="bi bi-slash-circle"></i> <span x-text="preview.unsubscribed"></span> unsubscribed — left out</li>
                                    <li x-show="preview.invalid" class="text-danger"><i class="bi bi-x-circle"></i> <span x-text="preview.invalid"></span> invalid / missing contact(s) left out</li>
                                </ul>
                                <div class="bg-body-tertiary border rounded p-2 small mb-2" x-show="plan && plan.batches" x-cloak>
                                    <i class="bi bi-stack"></i>
                                    Goes out in <strong x-text="plan.batches"></strong> batch<span x-show="plan.batches !== 1">es</span> of up to <span x-text="plan.size"></span>,
                                    about <strong x-text="plan.duration"></strong> in all<span x-show="scheduledAt"> from the scheduled time</span>.
                                    <div class="text-muted" x-show="channel === 'email'">You can pause, resume or cancel it from the campaign page while it sends.</div>
                                </div>
                                <details x-show="preview.skipped_count" class="small">
                                    <summary class="text-muted">Show what was left out and why</summary>
                                    <ul class="list-unstyled mt-2 mb-0" style="max-height: 260px; overflow-y: auto;">
                                        <template x-for="(entry, index) in preview.skipped" :key="index">
                                            <li class="border-top py-1">
                                                <span class="badge" :class="{ 'bg-warning-subtle text-warning-emphasis': entry.type === 'duplicate', 'bg-secondary-subtle text-secondary-emphasis': entry.type === 'unsubscribed', 'bg-danger-subtle text-danger-emphasis': entry.type === 'invalid' }" x-text="{ duplicate: 'Duplicate', unsubscribed: 'Unsubscribed', invalid: 'Invalid' }[entry.type] || 'Invalid'"></span>
                                                <span class="fw-semibold text-break" x-text="entry.value"></span>
                                                <div class="text-muted" x-text="entry.reason"></div>
                                            </li>
                                        </template>
                                        <li class="text-muted pt-1" x-show="preview.skipped_count > preview.skipped.length">…and <span x-text="preview.skipped_count - preview.skipped.length"></span> more.</li>
                                    </ul>
                                </details>
                            </div>
                        </template>
                    </div>
                    <div class="card-footer bg-white">
                        @if (App\Models\Campaign::requiresApproval(auth()->user()))
                            <div class="small text-muted mb-2"><i class="bi bi-shield-check"></i> A Super Admin reviews it first — nothing is sent until it's approved.</div>
                        @endif
                        <div class="alert alert-danger small py-2" x-show="reviewErrors.length" x-cloak>
                            <div class="fw-semibold">Fix these before previewing:</div>
                            <ul class="mb-0 ps-3"><template x-for="error in reviewErrors" :key="error"><li x-text="error"></li></template></ul>
                        </div>
                        <button type="submit" class="btn btn-primary w-100" :disabled="submitting || reviewing || (preview && preview.total === 0)">
                            <span x-show="!reviewing && !submitting"><i class="bi bi-eye"></i>
                                {{ App\Models\Campaign::requiresApproval(auth()->user()) ? 'Preview & Submit for Approval' : 'Preview & Send' }}
                            </span>
                            <span x-show="reviewing" x-cloak><span class="spinner-border spinner-border-sm"></span> Building preview…</span>
                            <span x-show="submitting" x-cloak><span class="spinner-border spinner-border-sm"></span> Saving…</span>
                        </button>
                        <div class="form-text text-center">You'll see exactly what goes out, and to how many, before anything is saved.</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- The required preview. Only its confirm button submits the form. --}}
        <div class="modal fade" tabindex="-1" aria-labelledby="reviewTitle" aria-hidden="true" x-ref="reviewModal">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="reviewTitle"><i class="bi bi-eye"></i> Preview — check it before it goes out</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger mb-0" x-show="!reviewing && !review && reviewErrors.length" x-cloak>
                            <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle"></i> Fix these, then preview again:</div>
                            <ul class="mb-0 ps-3"><template x-for="error in reviewErrors" :key="error"><li x-text="error"></li></template></ul>
                        </div>
                        <div class="text-center text-muted py-5" x-show="reviewing && !review">
                            <div class="spinner-border mb-3" role="status"></div>
                            <div>Building the preview…</div>
                            <div class="small" x-show="files.some((f) => f.type !== 'application/pdf')">Resizing images for email</div>
                        </div>
                        <template x-if="review">
                            <div class="row g-3">
                                <div class="col-lg-8">
                                    <template x-if="review.channel === 'email'">
                                        <div>
                                            <div class="border rounded bg-body-tertiary p-2 small mb-2">
                                                <div><span class="text-muted">From:</span> <span x-text="review.from"></span></div>
                                                <div><span class="text-muted">To:</span> <span x-text="review.to"></span> <span class="text-muted" x-show="review.summary.total > 1" x-text="`and ${(review.summary.total - 1).toLocaleString()} more`"></span></div>
                                                <div class="text-break"><span class="text-muted">Subject:</span> <strong x-text="review.subject"></strong></div>
                                            </div>
                                            <div class="small mb-2" x-show="review.attachments && review.attachments.length">
                                                <template x-for="file in review.attachments" :key="file.name">
                                                    <span class="badge border text-body bg-body me-1 mb-1 fw-normal">
                                                        <i class="bi" :class="file.kind === 'document' ? 'bi-paperclip' : 'bi-image'"></i>
                                                        <span x-text="file.name"></span> · <span x-text="formatBytes(file.size)"></span>
                                                    </span>
                                                </template>
                                            </div>
                                            <iframe :srcdoc="review.html" sandbox title="Email preview" class="w-100 border rounded bg-white" style="height: 60vh;"></iframe>
                                            <div class="form-text">Exactly what the first recipient gets — each recipient sees their own name. The unsubscribe link is disabled here.</div>
                                        </div>
                                    </template>
                                    <template x-if="review.channel === 'sms'">
                                        <div class="border rounded bg-body-tertiary p-3">
                                            <div class="small text-muted mb-1">To <span x-text="review.to"></span></div>
                                            <div class="bg-white border rounded-3 p-2 small text-break" style="white-space: pre-wrap; max-width: 360px;" x-text="review.text"></div>
                                            <div class="form-text"><span x-text="sms.length"></span> characters · <span x-text="sms.segments"></span> SMS per recipient</div>
                                        </div>
                                    </template>
                                </div>
                                <div class="col-lg-4">
                                    <div class="small fw-semibold mb-2">Summary</div>
                                    <dl class="row small mb-0">
                                        <dt class="col-5 text-muted fw-normal">Recipients</dt>
                                        <dd class="col-7 mb-2">
                                            <strong class="fs-4" x-text="review.summary.total.toLocaleString()"></strong>
                                            <div class="text-muted">
                                                <span x-text="review.summary.from_leads"></span> lead(s) ·
                                                <span x-text="review.summary.from_contacts"></span> contact(s) ·
                                                <span x-text="review.summary.manual"></span> extra
                                            </div>
                                        </dd>
                                        <dt class="col-5 text-muted fw-normal">Left out</dt>
                                        <dd class="col-7 mb-2">
                                            <span x-show="!review.summary.skipped_count">None</span>
                                            <span x-show="review.summary.duplicates" x-text="`${review.summary.duplicates} duplicate(s)`" class="d-block"></span>
                                            <span x-show="review.summary.unsubscribed" x-text="`${review.summary.unsubscribed} unsubscribed`" class="d-block"></span>
                                            <span x-show="review.summary.invalid" x-text="`${review.summary.invalid} invalid / missing contact`" class="d-block text-danger"></span>
                                        </dd>
                                        <dt class="col-5 text-muted fw-normal">Sending</dt>
                                        <dd class="col-7 mb-2">
                                            <span x-text="`${review.summary.batches} batch(es) of up to ${review.summary.batch_size}, ${review.summary.per_minute}/min`"></span><span x-show="review.summary.pause_minutes" x-text="`, ${review.summary.pause_minutes} min pause`"></span>
                                            <div class="text-muted" x-text="`about ${review.summary.duration} in all`"></div>
                                        </dd>
                                        <dt class="col-5 text-muted fw-normal">When</dt>
                                        <dd class="col-7 mb-2" x-text="review.summary.when"></dd>
                                        <template x-if="review.channel === 'email'">
                                            <dt class="col-5 text-muted fw-normal">Email size</dt>
                                        </template>
                                        <template x-if="review.channel === 'email'">
                                            <dd class="col-7 mb-2">
                                                <span x-text="formatBytes(review.email_bytes)" :class="review.email_bytes > 1048576 && 'text-warning-emphasis fw-semibold'"></span>
                                                <div class="text-muted" x-show="review.email_bytes > 1048576">Large — more likely to land in spam</div>
                                            </dd>
                                        </template>
                                        <template x-if="review.channel === 'email'">
                                            <dt class="col-5 text-muted fw-normal">Signature</dt>
                                        </template>
                                        <template x-if="review.channel === 'email'">
                                            <dd class="col-7 mb-2" x-text="review.summary.include_signature ? 'Included' : 'Off'"></dd>
                                        </template>
                                    </dl>
                                    <div class="alert alert-warning small py-2 mt-2 mb-0" x-show="review.requires_approval">
                                        <i class="bi bi-shield-check"></i> This goes to a Super Admin for approval — nothing is sent until they approve it.
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="bi bi-pencil"></i> Back to edit</button>
                        <button type="button" class="btn btn-primary" @click="confirmSend()" :disabled="submitting || !review">
                            <span x-show="!submitting">
                                <template x-if="review && review.requires_approval"><span><i class="bi bi-send-check"></i> Submit for approval</span></template>
                                <template x-if="review && !review.requires_approval"><span><i class="bi bi-send"></i> <span x-text="scheduledAt ? 'Schedule campaign' : `Send to ${review.summary.total.toLocaleString()} recipient(s)`"></span></span></template>
                            </span>
                            <span x-show="submitting" x-cloak><span class="spinner-border spinner-border-sm"></span> Saving…</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
