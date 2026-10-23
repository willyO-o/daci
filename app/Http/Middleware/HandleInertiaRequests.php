<?php

namespace App\Http\Middleware;

use App\Models\Rol;
use App\Models\User;
use App\Support\Acceso\CatalogoPermisos;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $usuario = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $usuario ? $this->datosUsuario($usuario) : null,
                // Permisos del usuario (slug => true). Sólo deciden qué se MUESTRA: la autorización real la hace
                // cada ruta con el middleware `permission:` (ver resources/js/Composables/usePermisos.js).
                'can' => $usuario ? $this->permisos($usuario) : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'message' => fn () => $request->session()->get('message'),
            ],
            'aplicacion' => [
                'nombre' => config('app.aplicacion.nombre'),
                'sigla' => config('app.aplicacion.sigla'),
                'version' => config('app.aplicacion.version'),
                'empresa' => config('app.aplicacion.empresa'),
            ],
        ];
    }

    /**
     * @return array{id: int, name: string, email: string, rol: ?string, rol_descripcion: ?string}
     */
    private function datosUsuario(User $usuario): array
    {
        /** @var Rol|null $rol */
        $rol = $usuario->roles->first();

        return [
            'id' => $usuario->id,
            'name' => $usuario->name,
            'email' => $usuario->email,
            'rol' => $rol?->name,
            'rol_descripcion' => $rol?->descripcion ?: $rol?->name,
        ];
    }

    /**
     * @return array<string, true>
     */
    private function permisos(User $usuario): array
    {
        $permisos = $usuario->hasRole(CatalogoPermisos::ROL_SUPERADMIN)
            ? CatalogoPermisos::permisos()
            : $usuario->getAllPermissions()->pluck('name')->all();

        return array_fill_keys($permisos, true);
    }
}
