<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
