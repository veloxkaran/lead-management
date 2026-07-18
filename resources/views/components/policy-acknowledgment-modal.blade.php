@php
    $steps = $pendingPolicyDocuments->map(function ($document) {
        $version = $document->currentVersion;

        return [
            'documentId' => $document->id,
            'versionId' => $version->id,
            'title' => $document->title,
            'typeLabel' => $document->type->label(),
            'assignee' => $document->user?->name ?? '',
            'version' => $version->version,
            'effectiveDate' => $version->effective_date->format('M d, Y'),
            'updatedAt' => $version->published_at->format('M d, Y'),
            'readingMinutes' => $version->estimatedReadingMinutes(),
            'content' => $version->content,
            'allowSkip' => $document->allow_skip,
        ];
    })->values();
@endphp

@if ($steps->isNotEmpty())
    <div
        x-data="policyAcknowledgmentModal(@js($steps))"
        x-show="open"
        x-cloak
        @keydown.escape.window="if (canFinish) finish()"
        @keydown.arrow-right.window="next()"
        @keydown.arrow-left.window="previous()"
        class="policy-ack-overlay"
        role="dialog"
        aria-modal="true"
        aria-labelledby="policyAckStepTitle"
    >
        <div class="policy-ack-backdrop" x-show="open" x-transition.opacity.duration.300ms></div>

        <div
            class="policy-ack-card"
            x-show="open"
            x-transition:enter="policy-ack-enter"
            x-transition:enter-start="policy-ack-enter-start"
            x-transition:enter-end="policy-ack-enter-end"
        >
            <div class="policy-ack-card__header">
                <div class="policy-ack-progress">
                    <div class="policy-ack-progress__bar" :style="`width: ${((currentIndex + 1) / steps.length) * 100}%`"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <span class="badge bg-primary-subtle text-primary-emphasis" x-text="currentStep.typeLabel"></span>
                    <span class="small text-muted" x-text="`Step ${currentIndex + 1} of ${steps.length}`"></span>
                </div>
            </div>

            <template x-for="(step, index) in steps" :key="step.versionId">
                <div
                    x-show="currentIndex === index"
                    x-transition:enter="policy-ack-step-enter"
                    x-transition:enter-start="policy-ack-step-enter-start"
                    x-transition:enter-end="policy-ack-step-enter-end"
                    class="policy-ack-card__body"
                >
                    <h5 id="policyAckStepTitle" x-text="step.title" tabindex="-1"></h5>
                    <div class="small text-muted d-flex flex-wrap gap-3 mb-3">
                        <span x-show="step.assignee"><i class="bi bi-diagram-3"></i> <span x-text="step.assignee"></span></span>
                        <span><i class="bi bi-tag"></i> v<span x-text="step.version"></span></span>
                        <span><i class="bi bi-calendar-event"></i> Effective <span x-text="step.effectiveDate"></span></span>
                        <span><i class="bi bi-clock-history"></i> Updated <span x-text="step.updatedAt"></span></span>
                        <span><i class="bi bi-book"></i> <span x-text="step.readingMinutes"></span> min read</span>
                    </div>
                    <div x-html="step.content"></div>
                </div>
            </template>

            <div class="policy-ack-card__footer d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-secondary btn-sm" @click="previous()" x-show="currentIndex > 0" x-cloak>
                    <i class="bi bi-arrow-left"></i> Previous
                </button>
                <span x-show="currentIndex === 0"></span>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" @click="skip()" x-show="currentStep.allowSkip && !currentAcknowledged" x-cloak>
                        Skip
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" @click="acknowledge()" x-show="!currentAcknowledged" x-cloak>
                        <i class="bi bi-check2"></i> I Understand &amp; Acknowledge
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm" @click="next()" x-show="currentAcknowledged && !isLastStep" x-cloak>
                        Next <i class="bi bi-arrow-right"></i>
                    </button>
                    <button type="button" class="btn btn-success btn-sm" @click="finish()" x-show="isLastStep" :disabled="!canFinish" x-cloak>
                        <i class="bi bi-check2-all"></i> Finish
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
