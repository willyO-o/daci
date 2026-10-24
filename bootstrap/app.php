<?php

use App\Http\Middleware\AsegurarUsuarioActivo;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            AsegurarUsuarioActivo::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Por defecto Laravel no registra el 413 (archivo demasiado grande); se necesita para diagnosticar subidas.
        $exceptions->stopIgnoring(PostTooLargeException::class);

        // Páginas de error con el diseño de la plantilla (el anterior tenía su 404 propia). Los 500/503 conservan la
        // página de depuración en local y en pruebas; las peticiones JSON siguen recibiendo JSON.
        $exceptions->respond(function (SymfonyResponse $respuesta, Throwable $excepcion, Request $request) {
            $estado = $respuesta->getStatusCode();
            $conDepuracion = app()->environment(['local', 'testing']) && in_array($estado, [500, 503], true);

            if ($respuesta instanceof JsonResponse || $request->expectsJson() || $conDepuracion
                || ! in_array($estado, [403, 404, 419, 500, 503], true)) {
                return $respuesta;
            }

            return Inertia::render('Errores/Error', ['estado' => $estado])
                ->toResponse($request)
                ->setStatusCode($estado);
        });

        // Toda excepción registrada incluye quién y desde dónde ocurrió.
        $exceptions->context(fn () => [
            'usuario_id' => auth()->id(),
            'url' => request()->fullUrl(),
            'metodo' => request()->method(),
            'ip' => request()->ip(),
        ]);
    })->create();
