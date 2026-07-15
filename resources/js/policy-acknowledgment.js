// Alpine component powering the SOP/Job Description onboarding modal
// (resources/views/components/policy-acknowledgment-modal.blade.php).
window.policyAcknowledgmentModal = function (steps) {
    return {
        steps,
        currentIndex: 0,
        open: steps.length > 0,
        acknowledgedVersionIds: new Set(),

        init() {
            if (this.open) {
                this.markViewed(this.currentStep);
            }
        },

        get currentStep() {
            return this.steps[this.currentIndex];
        },

        get isLastStep() {
            return this.currentIndex === this.steps.length - 1;
        },

        get currentAcknowledged() {
            return this.acknowledgedVersionIds.has(this.currentStep.versionId);
        },

        get canFinish() {
            return this.steps.every((step) => step.allowSkip || this.acknowledgedVersionIds.has(step.versionId));
        },

        goTo(index) {
            if (index < 0 || index >= this.steps.length) {
                return;
            }

            this.currentIndex = index;
            this.markViewed(this.currentStep);
        },

        next() {
            if (this.isLastStep) {
                this.finish();
                return;
            }

            this.goTo(this.currentIndex + 1);
        },

        previous() {
            this.goTo(this.currentIndex - 1);
        },

        skip() {
            if (this.currentStep.allowSkip) {
                this.next();
            }
        },

        markViewed(step) {
            axios.post(`/policy-documents/${step.versionId}/view`);
        },

        acknowledge() {
            const step = this.currentStep;

            axios.post(`/policy-documents/${step.versionId}/acknowledge`).then(() => {
                this.acknowledgedVersionIds.add(step.versionId);
            });
        },

        finish() {
            if (this.canFinish) {
                this.open = false;
            }
        },
    };
};
