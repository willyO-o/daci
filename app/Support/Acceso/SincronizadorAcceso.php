<?php

namespace App\Support\Acceso;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Pone la base al día con {@see CatalogoPermisos} sin tocar nada más. Es la lógica común de `PermisosSeeder` y de
 * `php artisan acceso:sincronizar`; es idempotente y segura sobre la base de producción:
 *
 *  - SÓLO AGREGA: crea los permisos y roles base que falten y le da al superadmin todos los permisos.
 *  - Un permiso NUEVO (creado en esta corrida) se da además a los roles de {@see CatalogoPermisos::asignacionInicial()}.
 *    Los permisos que ya existían no se reasignan: lo que el administrador quitó en la pantalla de roles se respeta.
 *  - NUNCA borra permisos, roles ni asignaciones. Un permiso que está en la base pero ya no en el catálogo se informa
 *    como obsoleto y se deja como está.
 *  - Deja `users.role_id` en espejo del rol de Spatie (sólo usuarios con rol en Spatie).
 *
 * Todo corre en una transacción: si algo falla, la base queda como estaba.
 */
final class SincronizadorAcceso
{
    /**
     * @param  array<string, list<string>>|null  $asignacionInicial  permiso => roles; null = la del catálogo
     */
    public function __construct(
        private readonly PermissionRegistrar $registrar,
        private readonly ?array $asignacionInicial = null,
    ) {}

    /**
     * @return array{
     *     permisos_creados: list<string>,
     *     roles_creados: list<string>,
     *     asignaciones_iniciales: array<string, list<string>>,
     *     obsoletos: list<string>,
     *     usuarios_corregidos: int,
     * }
     */
    public function sincronizar(): array
    {
        $this->registrar->forgetCachedPermissions();

        try {
            $resultado = DB::transaction(function () {
                $permisosCreados = $this->crearPermisosFaltantes();
                $rolesCreados = $this->crearRolesBaseFaltantes();

                Rol::findByName(CatalogoPermisos::ROL_SUPERADMIN, CatalogoPermisos::GUARD)
                    ->givePermissionTo(CatalogoPermisos::permisos());

                return [
                    'permisos_creados' => $permisosCreados,
                    'roles_creados' => $rolesCreados,
                    'asignaciones_iniciales' => $this->asignarPermisosNuevos($permisosCreados),
                    'obsoletos' => $this->permisosObsoletos(),
                    'usuarios_corregidos' => $this->sincronizarRolLegado(),
                ];
            });
        } finally {
            // También si falló: la caché pudo haber guardado permisos que la transacción deshizo.
            $this->registrar->forgetCachedPermissions();
        }

        return $resultado;
    }

    /**
     * @return list<string>
     */
    private function crearPermisosFaltantes(): array
    {
        $existentes = Permission::query()
            ->where('guard_name', CatalogoPermisos::GUARD)
            ->pluck('name')
            ->all();

        $faltantes = array_values(array_diff(CatalogoPermisos::permisos(), $existentes));

        foreach ($faltantes as $nombre) {
            Permission::create(['name' => $nombre, 'guard_name' => CatalogoPermisos::GUARD]);
        }

        return $faltantes;
    }

    /**
     * @return list<string>
     */
    private function crearRolesBaseFaltantes(): array
    {
        $existentes = Rol::query()
            ->where('guard_name', CatalogoPermisos::GUARD)
            ->pluck('name')
            ->all();

        $faltantes = array_values(array_diff(CatalogoPermisos::rolesBase(), $existentes));

        foreach ($faltantes as $nombre) {
            Rol::create(['name' => $nombre, 'guard_name' => CatalogoPermisos::GUARD]);
        }

        return $faltantes;
    }

    /**
     * Da cada permiso recién creado a los roles declarados para él. Un rol que no existe se omite (no se crea).
     *
     * @param  list<string>  $permisosCreados
     * @return array<string, list<string>> rol => permisos que recibió
     */
    private function asignarPermisosNuevos(array $permisosCreados): array
    {
        $mapa = $this->asignacionInicial ?? CatalogoPermisos::asignacionInicial();
        $porRol = [];

        foreach ($permisosCreados as $permiso) {
            foreach ($mapa[$permiso] ?? [] as $nombreRol) {
                if ($nombreRol !== CatalogoPermisos::ROL_SUPERADMIN) {
                    $porRol[$nombreRol][] = $permiso;
                }
            }
        }

        $roles = Rol::query()
            ->where('guard_name', CatalogoPermisos::GUARD)
            ->whereIn('name', array_keys($porRol))
            ->get()
            ->keyBy('name');

        $asignados = [];

        foreach ($porRol as $nombreRol => $permisos) {
            if ($rol = $roles->get($nombreRol)) {
                $rol->givePermissionTo($permisos);
                $asignados[$nombreRol] = $permisos;
            }
        }

        return $asignados;
    }

    /**
     * Permisos en la base que ya no están en el catálogo. Se informan; no se borran.
     *
     * @return list<string>
     */
    private function permisosObsoletos(): array
    {
        return Permission::query()
            ->where('guard_name', CatalogoPermisos::GUARD)
            ->whereNotIn('name', CatalogoPermisos::permisos())
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /**
     * Deja `users.role_id` en espejo del rol de Spatie (id del mismo rol en `roles_legado`, o null si es un rol
     * creado en DACI). Incluye usuarios eliminados. Un usuario SIN rol en Spatie no se toca: su `role_id` es el
     * único dato de rol que tiene.
     */
    private function sincronizarRolLegado(): int
    {
        if (! Schema::hasTable('roles_legado')) {
            return 0;
        }

        $idLegadoPorNombre = DB::table('roles_legado')->pluck('id', 'nombre');
        $corregidos = 0;

        DB::table('users')
            ->join('model_has_roles', function ($join) {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', (new User)->getMorphClass());
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->orderBy('users.id')
            ->get(['users.id', 'users.role_id', 'roles.name'])
            ->each(function (object $fila) use ($idLegadoPorNombre, &$corregidos) {
                $esperado = isset($idLegadoPorNombre[$fila->name]) ? (int) $idLegadoPorNombre[$fila->name] : null;
                $actual = $fila->role_id === null ? null : (int) $fila->role_id;

                if ($actual === $esperado) {
                    return;
                }

                DB::table('users')->where('id', $fila->id)->update(['role_id' => $esperado]);
                $corregidos++;
            });

        return $corregidos;
    }
}
