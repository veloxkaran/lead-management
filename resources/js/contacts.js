// Alpine component for the Contacts list (resources/views/contacts/index.blade.php):
// row selection for bulk actions, and the add/edit modal — one modal for
// both, re-opened with the submitted values when validation fails.
const EMPTY = { company_name: '', name: '', email: '', phone: '' };

window.contactsPage = function ({ storeUrl, updateUrl, campaignUrl, reopen = false, old = null, oldId = null, pageIds = [] }) {
    return {
        selected: [],
        pageIds,
        editingId: null,
        form: { ...EMPTY },
        modal: null,

        init() {
            this.modal = window.bootstrap.Modal.getOrCreateInstance(this.$refs.modal);
            this.$refs.modal.addEventListener('shown.bs.modal', () => this.$refs.firstField?.focus());

            if (reopen) {
                this.editingId = oldId ? Number(oldId) : null;
                this.form = { ...EMPTY, ...(old || {}) };
                this.modal.show();
            }
        },

        get action() {
            return this.editingId ? updateUrl.replace('__ID__', this.editingId) : storeUrl;
        },

        get isEmpty() {
            return Object.values(this.form).every((value) => String(value ?? '').trim() === '');
        },

        openCreate() {
            this.editingId = null;
            this.form = { ...EMPTY };
            this.modal.show();
        },

        openEdit(contact) {
            this.editingId = contact.id;
            this.form = { ...EMPTY, ...Object.fromEntries(Object.keys(EMPTY).map((key) => [key, contact[key] ?? ''])) };
            this.modal.show();
        },

        get allOnPageSelected() {
            return this.pageIds.length > 0 && this.pageIds.every((id) => this.selected.includes(id));
        },

        toggleAll() {
            this.selected = this.allOnPageSelected ? [] : [...this.pageIds];
        },

        campaignLink(channel) {
            const params = new URLSearchParams({ channel, audience: 'none' });
            this.selected.forEach((id) => params.append('contact_ids[]', id));

            return `${campaignUrl}?${params}`;
        },
    };
};
