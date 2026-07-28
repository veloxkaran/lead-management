<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        if (localStorage.getItem('sidebar-collapsed') === '1') {
            document.documentElement.classList.add('sidebar-collapsed');
        }
    </script>
    <title>@yield('title', 'Dashboard') - {{ config('app.name') }}</title>
    @vite(['resources/css/app.scss', 'resources/js/app.js'])
    @stack('styles')
</head>
<body>
    <div class="app-shell">
        @include('partials.sidebar')

        <div class="app-main">
            @include('partials.impersonation-banner')
            @include('partials.topbar')

            <main class="app-content">
                <x-flash />

                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
