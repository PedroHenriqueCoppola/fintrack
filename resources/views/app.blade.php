<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Fintrack') }}</title>
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" href="{{ asset('brand/fintrack-favicon.svg') }}" type="image/svg+xml">
        <link rel="icon" href="{{ asset('brand/favicon-32.png') }}" type="image/png" sizes="32x32">
        <link rel="icon" href="{{ asset('brand/favicon-16.png') }}" type="image/png" sizes="16x16">
        <link rel="apple-touch-icon" href="{{ asset('brand/apple-touch-icon.png') }}">
        <script>
            (function () {
                var theme = 'dark';

                try {
                    var stored = localStorage.getItem('theme');

                    if (stored === 'light' || stored === 'dark') {
                        theme = stored;
                    }
                } catch (error) {
                    theme = 'dark';
                }

                document.documentElement.setAttribute('data-theme', theme);
            })();
        </script>
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
        <x-inertia::head />
    </head>
    <body>
        <x-inertia::app />
    </body>
</html>
