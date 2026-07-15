@extends('layouts.immersive')

@section('title', $lead->company_name.' — Walkthrough')

@section('content')
    <div
        x-data="leadWalkthrough(@js($steps))"
        x-init="init()"
        @keydown.window="onKey($event)"
        class="wt-stage"
    >
        <div class="wt-topbar">
            <div class="small"><i class="bi bi-building me-1"></i> {{ $lead->company_name }}</div>
            <a href="{{ route('leads.show', $lead) }}" class="wt-close" aria-label="Close walkthrough">
                <i class="bi bi-x-lg"></i>
            </a>
        </div>

        <div class="wt-progress">
            @foreach ($steps as $i => $step)
                <div class="wt-segment" :class="{ 'is-done': current > {{ $i }}, 'is-active': current === {{ $i }} }"></div>
            @endforeach
        </div>

        <div class="wt-card-wrap">
            @foreach ($steps as $i => $step)
                @php
                    $accent = $step['accent'] ?? 'primary';
                    $accentStyle = 'background:'.(str_starts_with($accent, '#') ? $accent : "var(--bs-{$accent})").';';
                @endphp
                <div
                    x-show="current === {{ $i }}"
                    x-transition:enter="wt-transition-enter"
                    x-transition:enter-start="wt-in-{{ $step['type'] }}"
                    x-transition:enter-end="wt-settled"
                    x-transition:leave="wt-transition-leave"
                    x-transition:leave-start="wt-leave-settled"
                    x-transition:leave-end="wt-leave-out"
                    class="wt-card"
                >
                    <div class="wt-card-accent" style="{{ $accentStyle }}"></div>
                    <div class="wt-icon-badge" style="{{ $accentStyle }}">
                        <i class="bi {{ $step['icon'] }}"></i>
                    </div>

                    <h2>{{ $step['title'] }}</h2>

                    @if (! empty($step['subtitle']))
                        <div class="wt-subtitle">{{ $step['subtitle'] }}</div>
                    @endif

                    @if (! empty($step['body']))
                        <div class="wt-body">{{ $step['body'] }}</div>
                    @endif

                    @if (! empty($step['actor']) || ! empty($step['meta']))
                        <div class="wt-meta">
                            @if (! empty($step['actor']))<i class="bi bi-person-circle me-1"></i>{{ $step['actor'] }}@endif
                            @if (! empty($step['actor']) && ! empty($step['meta'])) &middot; @endif
                            {{ $step['meta'] }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="wt-controls">
            <button type="button" class="wt-btn" @click="prev()" :disabled="isFirst">
                <i class="bi bi-arrow-left"></i> Back
            </button>
            <button type="button" class="wt-btn" @click="togglePlay()">
                <i class="bi" :class="playing ? 'bi-pause-fill' : 'bi-play-fill'"></i>
                <span x-text="playing ? 'Pause' : 'Play'"></span>
            </button>
            <span class="wt-btn" style="pointer-events:none;" x-text="(current + 1) + ' / ' + steps.length"></span>
            <button type="button" class="wt-btn wt-btn-primary" @click="next()" x-show="!isLast">
                Next <i class="bi bi-arrow-right"></i>
            </button>
            <a href="{{ route('leads.show', $lead) }}" class="wt-btn wt-btn-primary" x-show="isLast">
                Back to Lead <i class="bi bi-check-lg"></i>
            </a>
        </div>

        <canvas id="wtConfetti" class="wt-confetti-canvas"></canvas>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('leadWalkthrough', (steps) => ({
                steps,
                current: 0,
                playing: false,
                timer: null,

                get isFirst() {
                    return this.current === 0;
                },
                get isLast() {
                    return this.current === this.steps.length - 1;
                },

                init() {
                    this.$watch('current', (value) => {
                        const step = this.steps[value];

                        if (step && step.type === 'closure' && step.outcome === 'won') {
                            this.launchConfetti();
                        }

                        if (this.isLast) {
                            this.stop();
                        }
                    });
                },

                next() {
                    if (! this.isLast) {
                        this.current++;
                    }
                },
                prev() {
                    if (! this.isFirst) {
                        this.current--;
                    }
                },
                togglePlay() {
                    this.playing ? this.stop() : this.play();
                },
                play() {
                    if (this.isLast) {
                        this.current = 0;
                    }
                    this.playing = true;
                    this.timer = setInterval(() => {
                        if (this.isLast) {
                            this.stop();
                            return;
                        }
                        this.current++;
                    }, 4500);
                },
                stop() {
                    this.playing = false;
                    clearInterval(this.timer);
                },
                onKey(event) {
                    if (event.key === 'ArrowRight') this.next();
                    if (event.key === 'ArrowLeft') this.prev();
                    if (event.key === 'Escape') window.location.href = @js(route('leads.show', $lead));
                },

                launchConfetti() {
                    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                        return;
                    }

                    const canvas = document.getElementById('wtConfetti');
                    const ctx = canvas.getContext('2d');
                    canvas.width = window.innerWidth;
                    canvas.height = window.innerHeight;

                    const colors = ['#2456a6', '#198754', '#f0ad4e', '#0dcaf0', '#dc3545'];
                    const pieces = Array.from({ length: 140 }, () => ({
                        x: Math.random() * canvas.width,
                        y: -20 - Math.random() * canvas.height * 0.5,
                        size: 4 + Math.random() * 5,
                        color: colors[Math.floor(Math.random() * colors.length)],
                        speedY: 2 + Math.random() * 3,
                        speedX: -1.5 + Math.random() * 3,
                        rotation: Math.random() * 360,
                        spin: -6 + Math.random() * 12,
                    }));

                    let frame = 0;
                    const maxFrames = 220;

                    const tick = () => {
                        frame++;
                        ctx.clearRect(0, 0, canvas.width, canvas.height);

                        pieces.forEach((p) => {
                            p.x += p.speedX;
                            p.y += p.speedY;
                            p.rotation += p.spin;
                            ctx.save();
                            ctx.translate(p.x, p.y);
                            ctx.rotate((p.rotation * Math.PI) / 180);
                            ctx.fillStyle = p.color;
                            ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 0.6);
                            ctx.restore();
                        });

                        if (frame < maxFrames) {
                            requestAnimationFrame(tick);
                        } else {
                            ctx.clearRect(0, 0, canvas.width, canvas.height);
                        }
                    };

                    requestAnimationFrame(tick);
                },
            }));
        });
    </script>
@endpush
