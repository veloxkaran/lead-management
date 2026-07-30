// Alpine component backing the document preview modal on the Support Ticket
// show page (resources/views/support-tickets/show.blade.php). Renders images
// and PDFs inline via the authorized preview route; anything else falls back
// to an "open in new tab" link rather than attempting an unsupported embed.
window.attachmentPreview = function () {
    return {
        url: null,
        name: '',
        mimeType: '',

        get isImage() {
            return (this.mimeType || '').startsWith('image/');
        },

        get isPdf() {
            return this.mimeType === 'application/pdf';
        },

        open(url, name, mimeType) {
            this.url = url;
            this.name = name;
            this.mimeType = mimeType;
            bootstrap.Modal.getOrCreateInstance(this.$refs.previewModal).show();
        },
    };
};
