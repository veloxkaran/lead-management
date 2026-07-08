@if (session()->has('impersonator_id'))
    <div class="d-flex align-items-center justify-content-center gap-2 bg-warning text-dark small py-1 px-2 text-center">
        <i class="bi bi-person-badge"></i>
        <span>You are viewing as <strong>{{ auth()->user()->name }}</strong>.</span>
        <form method="POST" action="{{ route('impersonate.stop') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm btn-dark py-0 px-2">Return to Admin</button>
        </form>
    </div>
@endif
