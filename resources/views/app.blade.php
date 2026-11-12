<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Fintrack') }}</title>
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
