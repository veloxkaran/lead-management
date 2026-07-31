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
