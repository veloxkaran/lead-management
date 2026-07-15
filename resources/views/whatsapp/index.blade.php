@extends('layouts.app')

@section('title', 'WhatsApp')

@section('content')
    <x-page-header title="WhatsApp" icon="bi-whatsapp" subtitle="Chat with leads you've been given WhatsApp access to." />

    <div class="row g-3" style="height: 75vh;" x-data="whatsappInbox({{ $activeLead?->id ?? 'null' }})">
        <div class="col-lg-4 h-100">
            <div class="card border-0 shadow-sm h-100 d-flex flex-column">
                <div class="card-header bg-white">
                    <input type="search" class="form-control form-control-sm" placeholder="Search leads..." x-model="search">
                </div>
                <div class="list-group list-group-flush overflow-auto flex-grow-1">
                    @forelse ($leads as $lead)
                        <a href="{{ route('whatsapp.show', $lead) }}"
                           class="list-group-item list-group-item-action {{ $activeLead?->id === $lead->id ? 'active' : '' }}"
                           data-lead-name="{{ $lead->company_name }}"
                           x-show="matches($el.dataset.leadName)">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-semibold">{{ $lead->company_name }}</span>
                                @if ($lead->isWhatsappWindowOpen())
                                    <span class="badge bg-success-subtle text-success-emphasis">Open</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">Closed</span>
                                @endif
                            </div>
                            <div class="small text-muted">{{ $lead->whatsapp_number }}</div>
                        </a>
                    @empty
                        <div class="list-group-item text-muted small">No leads have been assigned to you for WhatsApp chat yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-8 h-100">
            <div class="card border-0 shadow-sm h-100 d-flex flex-column">
                @if ($activeLead)
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold">{{ $activeLead->company_name }}</div>
                            <div class="small text-muted">{{ $activeLead->whatsapp_number }}</div>
                        </div>
                        <span class="badge" :class="windowOpen ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis'" x-text="windowOpen ? 'Window open' : 'Window closed'"></span>
                    </div>

                    <div class="card-body overflow-auto flex-grow-1" id="whatsappMessages" x-ref="scroller">
                        <template x-for="message in messages" :key="message.id">
                            <div class="d-flex mb-2" :class="message.direction === 'outbound' ? 'justify-content-end' : 'justify-content-start'">
                                <div class="p-2 rounded-3 small" style="max-width: 75%;" :class="message.direction === 'outbound' ? 'bg-primary text-white' : 'bg-light'">
                                    <div x-show="message.type === 'template'" class="fst-italic" x-text="'Template: ' + message.template_name"></div>
                                    <div x-text="message.body"></div>
                                    <div class="small mt-1" :class="message.direction === 'outbound' ? 'text-white-50' : 'text-muted'" x-text="message.created_at"></div>
                                </div>
                            </div>
                        </template>
                        <template x-if="messages.length === 0">
                            <div class="text-muted small text-center mt-4">No messages yet.</div>
                        </template>
                    </div>

                    <div class="card-footer bg-white">
                        <template x-if="windowOpen">
                            <form @submit.prevent="sendMessage" class="d-flex gap-2">
                                <input type="text" class="form-control" placeholder="Type a message..." x-model="body" required>
                                <button class="btn btn-primary" type="submit"><i class="bi bi-send"></i></button>
                            </form>
                        </template>
                        <template x-if="!windowOpen">
                            <div>
                                <div class="small text-muted mb-2">
                                    The 24-hour window is closed — send an approved template to start a new conversation.
                                </div>
                                <form @submit.prevent="sendTemplate" class="d-flex gap-2">
                                    <select class="form-select" x-model="selectedTemplate" required>
                                        <option value="">Select a template...</option>
                                        <template x-for="template in templates" :key="template.name">
                                            <option :value="template.name" x-text="template.name + ' (' + template.language + ')'"></option>
                                        </template>
                                    </select>
                                    <button class="btn btn-outline-primary text-nowrap" type="submit"><i class="bi bi-send"></i> Send Template</button>
                                </form>
                            </div>
                        </template>
                    </div>
                @else
                    <div class="card-body d-flex align-items-center justify-content-center text-muted">
                        Select a lead on the left to start chatting.
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function whatsappInbox(activeLeadId) {
            return {
                activeLeadId,
                search: '',
                messages: @json($activeLead->whatsappMessages ?? []),
                windowOpen: @json($activeLead?->isWhatsappWindowOpen() ?? false),
                templates: [],
                selectedTemplate: '',
                body: '',
                pollTimer: null,

                init() {
                    if (this.activeLeadId) {
                        this.scrollToBottom();
                        this.pollTimer = setInterval(() => this.poll(), 4000);
                        this.loadTemplates();
                    }
                },

                matches(name) {
                    return this.search === '' || name.toLowerCase().includes(this.search.toLowerCase());
                },

                scrollToBottom() {
                    this.$nextTick(() => {
                        if (this.$refs.scroller) {
                            this.$refs.scroller.scrollTop = this.$refs.scroller.scrollHeight;
                        }
                    });
                },

                poll() {
                    const afterId = this.messages.length ? this.messages[this.messages.length - 1].id : null;

                    axios.get(`/whatsapp/${this.activeLeadId}/messages`, { params: { after_id: afterId } })
                        .then(({ data }) => {
                            if (data.messages.length) {
                                this.messages.push(...data.messages);
                                this.scrollToBottom();
                            }
                            this.windowOpen = data.window_open;
                        });
                },

                loadTemplates() {
                    axios.get('/whatsapp-templates').then(({ data }) => {
                        this.templates = data.templates;
                    });
                },

                sendMessage() {
                    const body = this.body;
                    this.body = '';

                    axios.post(`/whatsapp/${this.activeLeadId}/messages`, { body })
                        .then(({ data }) => {
                            this.messages.push(data.message);
                            this.scrollToBottom();
                        })
                        .catch((error) => {
                            this.body = body;
                            Swal.fire('Could not send message', error.response?.data?.message ?? 'Please try again.', 'error');
                        });
                },

                sendTemplate() {
                    const template = this.templates.find((t) => t.name === this.selectedTemplate);

                    axios.post(`/whatsapp/${this.activeLeadId}/templates`, {
                        template_name: this.selectedTemplate,
                        language: template?.language ?? 'en_US',
                        components: [],
                    }).then(({ data }) => {
                        this.messages.push(data.message);
                        this.scrollToBottom();
                    }).catch((error) => {
                        Swal.fire('Could not send template', error.response?.data?.message ?? 'Please try again.', 'error');
                    });
                },
            };
        }
    </script>
@endpush
