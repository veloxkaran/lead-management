// Alpine component powering the Performance Snapshot dashboard widget
// (resources/views/components/performance-snapshot.blade.php). Switching
// Daily/Monthly/Lifetime re-fetches this widget's own data in place —
// no page navigation — so the switch can never jump the user's scroll
// position, unlike a GET-link-driven filter.
window.performanceSnapshot = function () {
    return {
        period: 'daily',
        loading: true,
        data: {},

        init() {
            this.load('daily');
        },

        ratioLabel() {
            const ratio = this.data.leads ? this.data.leads.ratio : null;

            return (ratio === null || ratio === undefined) ? '—' : ratio + '%';
        },

        switchPeriod(period) {
            if (period === this.period) {
                return;
            }

            this.load(period);
        },

        load(period) {
            this.loading = true;
            axios.get('/dashboard/performance-snapshot', { params: { snapshot_period: period } })
                .then(({ data }) => {
                    this.data = data;
                    this.period = data.period;
                })
                .finally(() => {
                    this.loading = false;
                });
        },
    };
};
