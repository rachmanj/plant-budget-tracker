<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <script>
            (function () {
                var key = 'pmb-theme';
                var stored;
                try {
                    stored = localStorage.getItem(key);
                } catch (e) {}
                if (stored === 'dark' || stored === 'light') {
                    document.documentElement.setAttribute('data-theme', stored);
                    return;
                }
                document.documentElement.setAttribute(
                    'data-theme',
                    window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'dark'
                );
            })();
        </script>
        <title inertia>{{ config('app.name', 'Plant Budget Tracker') }}</title>
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        @inertiaHead
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
