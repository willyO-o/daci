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

        {{-- Escudo de la FELCC (generado de public/felcc/img/felcc.png: .ico con 16/32/48/64 px). --}}
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
        <link rel="icon" type="image/png" sizes="192x192" href="/favicon-192.png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{-- Aplica los ajustes de «Personalizar tema» antes de pintar (evita el parpadeo claro → oscuro). Misma
             lectura que resources/js/Composables/useTema.js (acepta el formato anterior { modo, sidebar }). --}}
        <script>
            (function () {
                try {
                    var tema = JSON.parse(localStorage.getItem('felcc.tema') || '{}');
                    var atributos = tema.atributos || {};
                    if (tema.modo && !atributos['data-layout-mode']) { atributos['data-layout-mode'] = tema.modo; }
                    if (tema.sidebar && !atributos['data-sidebar-size']) { atributos['data-sidebar-size'] = tema.sidebar; }
                    var html = document.documentElement;
                    Object.keys(atributos).forEach(function (atributo) {
                        if (atributo.indexOf('data-') !== 0) { return; }
                        if (atributo === 'data-sidebar-size' && window.innerWidth < 1025) { return; }
                        html.setAttribute(atributo, atributos[atributo]);
                    });
                    if (atributos['data-layout-mode']) { html.setAttribute('data-bs-theme', atributos['data-layout-mode']); }
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
        {{-- Pantalla de carga de la plantilla: sólo visible con data-preloader="enable" (Personalizar tema). --}}
        <div id="preloader">
            <div id="status">
                <div class="spinner-border text-primary avatar-sm" role="status">
                    <span class="visually-hidden">Cargando…</span>
                </div>
            </div>
        </div>

        @inertia
    </body>
</html>
