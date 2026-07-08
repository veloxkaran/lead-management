<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Sign in') - {{ config('app.name') }}</title>
    @vite(['resources/css/app.scss', 'resources/js/app.js'])
</head>
<body class="bg-light">
    <div class="d-flex align-items-center justify-content-center min-vh-100 py-4">
        <div class="w-100" style="max-width: 420px;">
            <div class="text-center mb-4">
                <div class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary text-white mb-2" style="width:56px;height:56px;">
                    <i class="bi bi-kanban fs-3"></i>
                </div>
                <h1 class="h4 fw-semibold mb-0">{{ config('app.name') }}</h1>
                <p class="text-muted small">Internal Business Development Management</p>
            </div>
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    @yield('content')
                </div>
            </div>
        </div>
    </div>
</body>
</html>
