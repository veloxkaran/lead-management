// Alpine component powering the "similar lead already exists" suggestion
// box on the lead create form (resources/views/leads/_form.blade.php).
// Debounced client-side lookup against LeadController::checkDuplicate —
// purely advisory; the hard block on submit still lives in
// App\Rules\NotDuplicateLeadName.
window.leadDuplicateCheck = function () {
    return {
        companyName: '',
        matches: [],
        timer: null,

        check() {
            clearTimeout(this.timer);

            const term = this.companyName.trim();
            if (term.length < 2) {
                this.matches = [];
                return;
            }

            this.timer = setTimeout(() => {
                axios.get('/leads/check-duplicate', { params: { company_name: term } })
                    .then(({ data }) => {
                        this.matches = data.matches;
                    });
            }, 350);
        },
    };
};

// Alpine component for the Raw Data create form's "similar leads already
// exist" box (resources/views/raw-data/create.blade.php). Looks up leads
// whose company name is >= 50% similar via RawDataController::similarLeads —
// the same SimilarLeadFinder match StoreRawDataRequest enforces on submit,
// where saving then needs the "Save anyway" confirmation. `initial` carries
// the matches the server found when a submit was held back.
window.similarLeadCheck = function (url, initialName = '', initial = []) {
    return {
        companyName: initialName,
        matches: initial,
        timer: null,
        request: 0,

        check() {
            clearTimeout(this.timer);

            const term = this.companyName.trim();
            if (term.length < 3) {
                this.matches = [];
                return;
            }

            this.timer = setTimeout(() => {
                // Ignore a slower earlier response landing after a newer one.
                const request = ++this.request;

                axios.get(url, { params: { company_name: term } })
                    .then(({ data }) => {
                        if (request === this.request) {
                            this.matches = data.matches;
                        }
                    });
            }, 350);
        },
    };
};
