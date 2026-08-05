@props(['stats'])

{{-- Highlighted org-wide "how long does this take to resolve" stats,
     rendered identically on every role dashboard (see
     resources/views/dashboard/*.blade.php, right after x-role-playbook).
     Uses the same gradient treatment as the motivation panel inside
     x-role-playbook so it reads as "emphasized" without introducing a
     third visual language. Takes a list of stats so another
     resolution-time metric slots in as one more column instead of
     stacking another full-width banner. --}}
<div class="rounded p-3 text-white mb-3" style="background: linear-gradient(135deg,#2456a6,#1b2430);">
    <div class="row g-3">
        @foreach ($stats as $stat)
            <div class="col-md d-flex align-items-center gap-3">
                <span class="d-flex align-items-center justify-content-center rounded-circle bg-white bg-opacity-10 flex-shrink-0" style="width: 48px; height: 48px; font-size: 1.3rem;">
                    <i class="bi {{ $stat['icon'] }}"></i>
                </span>
                <div>
                    <div class="small text-uppercase fw-semibold opacity-75">{{ $stat['label'] }}</div>
                    <div class="fs-5 fw-semibold">{{ $stat['value'] }}</div>
                </div>
            </div>
        @endforeach
    </div>
</div>
