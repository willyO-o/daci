<?php

namespace Tests\Feature\Acceso;

use App\Support\Acceso\CatalogoPermisos;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Rutas;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Ninguna ruta del sistema queda abierta por olvido: toda ruta con sesión declara `permission:` salvo las que sólo
 * necesitan sesión por diseño (lista de abajo), y todo permiso declarado existe en el catálogo.
 */
class AutorizacionRutasTest extends TestCase
{
    /**
     * Rutas que sólo exigen sesión (catálogos de lectura para los formularios, perfil, buscadores, cierre de sesión).
     *
     * @var list<string>
     */
    private const SOLO_SESION = [
        'home', 'dashboard', 'perfil', 'perfil.password', 'logout', 'password.confirm',
        'juzgados.index', 'delitos.index', 'tipos-mandamientos.index', 'divisiones.index', 'paises.index',
        'personas.search', 'personas.check-ci',
    ];

    /**
     * @return list<Route>
     */
    private function rutasConSesion(): array
    {
        return array_values(array_filter(
            Rutas::getRoutes()->getRoutes(),
            fn (Route $ruta) => in_array('auth', $ruta->gatherMiddleware(), true) && $ruta->getName() !== null,
        ));
    }

    /**
     * @return list<string>
     */
    private function permisosDe(Route $ruta): array
    {
        return collect($ruta->gatherMiddleware())
            ->filter(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'permission:'))
            ->flatMap(fn (string $middleware) => explode('|', Str::after($middleware, 'permission:')))
            ->values()
            ->all();
    }

    public function test_toda_ruta_con_sesion_declara_su_permiso(): void
    {
        $abiertas = collect($this->rutasConSesion())
            ->reject(fn (Route $ruta) => in_array($ruta->getName(), self::SOLO_SESION, true))
            ->filter(fn (Route $ruta) => $this->permisosDe($ruta) === [])
            ->map(fn (Route $ruta) => $ruta->getName())
            ->values()
            ->all();

        $this->assertSame([], $abiertas, 'Rutas sin permiso: '.implode(', ', $abiertas));
    }

    public function test_todo_permiso_declarado_existe_en_el_catalogo(): void
    {
        $catalogo = CatalogoPermisos::permisos();

        $desconocidos = collect($this->rutasConSesion())
            ->flatMap(fn (Route $ruta) => $this->permisosDe($ruta))
            ->unique()
            ->reject(fn (string $permiso) => in_array($permiso, $catalogo, true))
            ->values()
            ->all();

        $this->assertSame([], $desconocidos, 'Permisos fuera del catálogo: '.implode(', ', $desconocidos));
    }
}
