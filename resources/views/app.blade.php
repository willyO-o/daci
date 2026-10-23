<!DOCTYPE html>
<html lang="es" data-layout="vertical" data-topbar="light" data-sidebar="dark" data-sidebar-size="lg"
    data-sidebar-image="none" data-preloader="disable" data-layout-style="default" data-layout-mode="light"
    data-layout-width="fluid" data-layout-position="fixed" data-bs-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ config('app.aplicacion.nombre') }}">
        <meta name="author" content="{{ config('app.aplicacion.empresa') }}">

        <title inertia>{{ config('app.name') }}</title>

        <link rel="shortcut icon" href="/assets/images/favicon.ico">

        {{-- Aplica el tema guardado antes de pintar (evita el parpadeo claro → oscuro). --}}
        <script>
            (function () {
                try {
                    var tema = JSON.parse(localStorage.getItem('felcc.tema') || '{}');
                    var html = document.documentElement;
                    if (tema.modo === 'dark') {
                        html.setAttribute('data-layout-mode', 'dark');
                        html.setAttribute('data-bs-theme', 'dark');
                    }
                    if (tema.sidebar === 'sm' && window.innerWidth >= 1025) {
                        html.setAttribute('data-sidebar-size', 'sm');
                    }
                } catch (e) {}
            })();
        </script>

        {{-- Plantilla Velzon (CSS original de la carpeta material/assets). --}}
        <link href="/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css">
        <link href="/assets/css/icons.min.css" rel="stylesheet" type="text/css">
        <link href="/assets/css/app.min.css" rel="stylesheet" type="text/css">
        <link href="/assets/css/custom.min.css" rel="stylesheet" type="text/css">

        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
