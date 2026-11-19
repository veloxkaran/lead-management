// Alpine component powering the Performance Snapshot dashboard widget
// (resources/views/components/performance-snapshot.blade.php). Switching
// Daily/Monthly/Lifetime, or stepping Monthly back/forward a BS month with
// the ‹ › arrows, re-fetches this widget's own data in place — no page
// navigation — so it can never jump the user's scroll position, unlike a
// GET-link-driven filter.
window.performanceSnapshot = function () {
    return {
        period: 'daily',
        monthOffset: 0,
        loading: true,
        data: {},
        request: 0,

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

            // Monthly always opens on the current month.
            this.load(period, 0);
        },

        shiftMonth(delta) {
            this.load('monthly', this.monthOffset + delta);
        },

        load(period, monthOffset = 0) {
            // Rapid ‹ clicks: only the latest response may update the widget.
            const request = ++this.request;
            this.loading = true;

            axios.get('/dashboard/performance-snapshot', { params: { snapshot_period: period, month_offset: monthOffset } })
                .then(({ data }) => {
                    if (request !== this.request) {
                        return;
                    }

                    this.data = data;
                    this.period = data.period;
                    this.monthOffset = data.monthOffset || 0;
                })
                .finally(() => {
                    if (request === this.request) {
                        this.loading = false;
                    }
                });
        },
    };
};
