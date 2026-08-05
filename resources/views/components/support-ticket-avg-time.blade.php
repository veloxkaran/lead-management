@props(['time'])

{{-- Highlighted org-wide stat, rendered identically on every role dashboard
     (see resources/views/dashboard/*.blade.php, right after x-role-playbook).
     Uses the same gradient treatment as the motivation panel inside
     x-role-playbook so it reads as "emphasized" without introducing a
     third visual language. --}}
<div class="rounded p-3 text-white d-flex align-items-center gap-3 mb-3" style="background: linear-gradient(135deg,#2456a6,#1b2430);">
    <span class="d-flex align-items-center justify-content-center rounded-circle bg-white bg-opacity-10" style="width: 48px; height: 48px; font-size: 1.3rem;">
        <i class="bi bi-stopwatch"></i>
    </span>
    <div>
        <div class="small text-uppercase fw-semibold opacity-75">Average Support Ticket Solving Time</div>
        <div class="fs-5 fw-semibold">{{ $time }}</div>
    </div>
</div>
