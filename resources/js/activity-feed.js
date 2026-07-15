// Alpine component powering the Activity Feed dashboard widget
// (resources/views/components/activity-feed-widget.blade.php). Unlike the
// WhatsApp inbox's cursor/append polling, this replaces the full ordered
// top-N on every tick: activities from different modules interleave by
// timestamp, so an append-only cursor would break cross-module ordering.
window.activityFeed = function (refreshSeconds) {
    return {
        items: [],
        page: 1,
        lastPage: 1,
        loading: true,

        init() {
            this.load();
            setInterval(() => {
                if (this.page === 1) {
                    this.load();
                }
            }, refreshSeconds * 1000);
        },

        load() {
            axios.get('/activity-feed', { params: { page: this.page } })
                .then(({ data }) => {
                    this.items = data.data;
                    this.lastPage = data.last_page;
                })
                .finally(() => {
                    this.loading = false;
                });
        },

        goToPage(page) {
            if (page < 1 || page > this.lastPage) {
                return;
            }

            this.page = page;
            this.loading = true;
            this.load();
        },
    };
};
