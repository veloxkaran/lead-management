// Alpine component for the campaign create form
// (resources/views/campaigns/create.blade.php): channel switch, SMS
// length/segment counter, spam-wording hints, the batch sending plan, and
// a live recipient preview — the form is posted to
// CampaignController::preview, which runs the same builder as the real
// send, so duplicates/invalid entries shown here are exactly what will be
// left out.
//
// GSM-7 basic characters; anything else (e.g. Nepali/Devanagari, emoji)
// switches an SMS to Unicode, which fits far fewer characters per segment.
const GSM7 = /^[@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&'()*+,\-./0-9:;<=>?¡A-ZÄÖÑÜ§¿a-zäöñüà^{}\\[~\]|€]*$/;
const GSM7_EXTENDED = /[\^{}\\[~\]|€]/g;

// Words and patterns spam filters weigh heavily. Only hints — nothing is blocked.
const SPAM_PHRASES = ['100% free', 'free!', 'act now', 'limited time', 'click here', 'buy now', 'risk-free', 'risk free', 'winner', 'congratulations', 'cash bonus', '$$$', 'urgent', 'guaranteed', 'no cost', 'earn money', 'make money', 'cheap', 'order now', 'special promotion'];
const SHORTENERS = /\b(bit\.ly|tinyurl\.com|goo\.gl|t\.co|ow\.ly|is\.gd|cutt\.ly|rb\.gy)\//i;

function formatMinutes(minutes) {
    if (minutes < 1) return 'under a minute';
    if (minutes < 60) return `${minutes} min`;
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;
    return rest ? `${hours}h ${rest}m` : `${hours}h`;
}

/**
 * How long `count` emails take at the given batch settings — the same
 * arithmetic as Campaign::estimatedFinishAt(): each batch is spread over
 * ceil(size / perMinute) minutes, then the pause, then the next batch.
 * Used by the composer and the Campaign Setup speed card.
 */
window.campaignSendingPlan = function (count, { batch_size, per_minute, pause_minutes }) {
    const size = Math.max(1, parseInt(batch_size, 10) || 1);
    const perMinute = Math.max(1, parseInt(per_minute, 10) || 1);
    const pause = Math.max(0, parseInt(pause_minutes, 10) || 0);
    const total = Math.max(0, parseInt(count, 10) || 0);

    if (total === 0) {
        return { batches: 0, size, minutes: 0, duration: '—', perHour: 0 };
    }

    const batches = Math.ceil(total / size);
    const spread = Math.ceil(size / perMinute);
    const last = total - (batches - 1) * size;
    const minutes = (batches - 1) * (spread + pause) + Math.ceil(last / perMinute);
    const perHour = batches > 1 ? Math.round((size * 60) / (spread + pause)) : Math.min(total, perMinute * 60);

    return { batches, size, minutes, duration: formatMinutes(minutes), perHour: perHour.toLocaleString() };
};

window.campaignComposer = function ({ channel, audience, subject = '', message = '', scheduledAt = '', previewUrl, composePreviewUrl, sendOptions = {} }) {
    return {
        channel,
        audience,
        subject,
        message,
        scheduledAt,
        sendOptions,
        preview: null,
        previewError: null,
        loading: false,
        timer: null,
        request: 0,
        submitting: false,
        // The required preview step: the server's rendering of the campaign
        // and the token proving it was seen (see CampaignPreviewToken).
        review: null,
        reviewing: false,
        reviewErrors: [],
        confirmed: false,

        init() {
            // Any edit after previewing means previewing again.
            this.$el.addEventListener('input', (event) => {
                if (!event.target.closest('.modal')) this.review = null;
            });
            // select2 fires jQuery events, which native/Alpine listeners don't see.
            if (window.jQuery) {
                window.jQuery(this.$el).on('change', 'select[data-select2-field]', () => this.schedulePreview());
            }

            this.schedulePreview(0);
        },

        get sms() {
            const text = this.message;
            const unicode = !GSM7.test(text);
            // Extended GSM characters take two slots.
            const length = unicode ? [...text].length : text.length + (text.match(GSM7_EXTENDED) || []).length;
            const single = unicode ? 70 : 160;
            const multi = unicode ? 67 : 153;
            const segments = length === 0 ? 0 : (length <= single ? 1 : Math.ceil(length / multi));

            return { length, unicode, segments };
        },

        get plan() {
            const options = this.sendOptions[this.channel];

            return options && this.preview ? window.campaignSendingPlan(this.preview.total, options) : null;
        },

        get spamWarnings() {
            if (this.channel !== 'email') return [];

            const warnings = [];
            const subject = this.subject || '';
            const text = `${subject}\n${this.message}`.toLowerCase();
            const letters = subject.replace(/[^A-Za-z]/g, '');

            if (letters.length >= 6 && letters.replace(/[^A-Z]/g, '').length / letters.length > 0.7) {
                warnings.push('The subject is mostly CAPITAL letters.');
            }
            if ((subject.match(/!/g) || []).length > 1) {
                warnings.push('More than one "!" in the subject.');
            }
            if (subject.length > 80) {
                warnings.push('Long subject — most inboxes cut it off around 60–80 characters.');
            }

            const phrase = SPAM_PHRASES.find((p) => text.includes(p));
            if (phrase) {
                warnings.push(`Phrases like "${phrase}" are common in spam — consider rewording.`);
            }
            if (SHORTENERS.test(text)) {
                warnings.push('Link shorteners (bit.ly etc.) are a strong spam signal — use the full link.');
            }
            if ((this.message.match(/https?:\/\//g) || []).length > 3) {
                warnings.push('Many links — keep it to two or three.');
            }

            return warnings;
        },

        /**
         * The form never submits straight from the main button: it opens
         * the preview, and only "Send" / "Submit for approval" in there
         * actually submits.
         */
        onSubmit(event) {
            if (!this.confirmed) {
                event.preventDefault();
                this.openPreview();

                return;
            }

            this.submitting = true;
        },

        openPreview() {
            const data = new FormData(this.$el);
            data.delete('preview_token');

            this.reviewing = true;
            this.reviewErrors = [];

            axios.post(composePreviewUrl, data)
                .then(({ data: review }) => {
                    this.review = review;
                    window.bootstrap.Modal.getOrCreateInstance(this.$refs.reviewModal).show();
                })
                .catch((error) => {
                    const errors = error.response?.data?.errors;
                    this.reviewErrors = errors ? Object.values(errors).flat() : ['Could not build the preview — try again.'];
                })
                .finally(() => {
                    this.reviewing = false;
                });
        },

        confirmSend() {
            this.confirmed = true;
            this.$nextTick(() => this.$el.requestSubmit());
        },

        schedulePreview(delay = 500) {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.loadPreview(), delay);
        },

        loadPreview() {
            const request = ++this.request;
            const data = new FormData(this.$el);

            // The preview only needs the recipient fields.
            ['name', 'subject', 'message', 'scheduled_at'].forEach((field) => data.delete(field));

            this.loading = true;

            axios.post(previewUrl, data)
                .then(({ data: summary }) => {
                    if (request !== this.request) return;
                    this.preview = summary;
                    this.previewError = null;
                })
                .catch((error) => {
                    if (request !== this.request) return;
                    const errors = error.response?.data?.errors;
                    this.preview = null;
                    this.previewError = errors ? Object.values(errors)[0][0] : 'Could not load the recipient preview.';
                })
                .finally(() => {
                    if (request === this.request) this.loading = false;
                });
        },
    };
};
