<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migra el control de acceso del sistema FELCC (roles en la tabla `roles` + permisos fijos en
 * `config/acl.php`) a spatie/laravel-permission.
 *
 * Es la ÚNICA migración que toca el esquema de producción y sólo afecta a roles y permisos:
 *  1. La tabla `roles` del sistema anterior (`nombre`, `descripcion`) se RENOMBRA a `roles_legado`; no se borra.
 *     La FK `users.role_id` la sigue apuntando y la columna `users.role_id` no se modifica.
 *  2. Se crean las tablas estándar de Spatie (`permissions`, `roles`, `model_has_permissions`, `model_has_roles`,
 *     `role_has_permissions`). `roles` lleva además `descripcion` para conservar la del rol antiguo.
 *  3. Se crean los permisos y cada rol antiguo con el MISMO nombre y los permisos equivalentes a los que tenía
 *     (traducción fija de abajo: cada permiso antiguo concedía exactamente los nuevos que se listan).
 *  4. Cada usuario recibe en `model_has_roles` el rol que tenía en `users.role_id`.
 *
 * La traducción queda congelada aquí a propósito: es el registro histórico de lo que tenía cada rol el día de la
 * migración. Los permisos que se agreguen después los crea `php artisan acceso:sincronizar`.
 *
 * `down()` deja la base exactamente como estaba: borra las tablas de Spatie y devuelve `roles_legado` a `roles`.
 */
return new class extends Migration
{
    /**
     * Permisos del sistema anterior → permisos nuevos que concedía.
     *
     * @var array<string, list<string>>
     */
    private const TRADUCCION = [
        'users_all' => ['usuarios.listar', 'usuarios.crear', 'usuarios.editar', 'usuarios.eliminar', 'usuarios.activar', 'roles.listar', 'roles.gestionar'],

        'mandamientos_all' => ['mandamientos.listar', 'mandamientos.ver', 'mandamientos.crear', 'mandamientos.editar', 'mandamientos.eliminar', 'consultas.mandamientos', 'personas.ver'],
        'mandamientos_listar' => ['mandamientos.listar', 'mandamientos.ver', 'consultas.mandamientos'],
        'mandamientos_crear' => ['mandamientos.crear'],
        'mandamientos_edit' => ['mandamientos.editar'],
        'consulta_mandamientos' => ['consultas.mandamientos', 'mandamientos.ver', 'personas.ver'],

        'personas_all' => ['personas.listar', 'personas.ver', 'personas.crear', 'personas.editar', 'personas.eliminar', 'personas.restaurar'],
        'personas_listar' => ['personas.listar', 'personas.ver'],
        'personas_crear' => ['personas.crear'],
        'personas_editar' => ['personas.editar'],
        'personas_eliminar' => ['personas.eliminar'],
        'consulta_personas' => ['personas.ver'],

        'registro-criminal_all' => ['registro_criminal.listar', 'registro_criminal.ver', 'registro_criminal.crear', 'registro_criminal.editar', 'registro_criminal.eliminar', 'registro_criminal.ficha', 'consultas.registro_criminal'],
        'registro-criminal_listar' => ['registro_criminal.listar', 'registro_criminal.ver', 'consultas.registro_criminal'],
        'registro-criminal_crear' => ['registro_criminal.crear'],
        'registro-criminal_editar' => ['registro_criminal.editar'],
        'consulta_registro-criminal' => ['consultas.registro_criminal', 'registro_criminal.ver'],

        'telefonos_all' => ['telefonos.listar', 'telefonos.ver', 'telefonos.crear', 'telefonos.editar', 'telefonos.eliminar'],
        'telefonos_listar' => ['telefonos.listar', 'telefonos.ver'],
        'telefonos_crear' => ['telefonos.crear'],
        'telefonos_editar' => ['telefonos.editar'],
        'telefonos_eliminar' => ['telefonos.eliminar'],
        'consulta_telefonos' => ['telefonos.ver'],

        'imeis_all' => ['imeis.listar', 'imeis.ver', 'imeis.crear', 'imeis.editar', 'imeis.eliminar'],
        'imeis_listar' => ['imeis.listar', 'imeis.ver'],
        'imeis_crear' => ['imeis.crear'],
        'imeis_editar' => ['imeis.editar'],
        'imeis_eliminar' => ['imeis.eliminar'],

        'vehiculos_all' => ['vehiculos.listar', 'vehiculos.ver', 'vehiculos.crear', 'vehiculos.editar', 'vehiculos.eliminar', 'vehiculos.vincular'],
        'vehiculos_listar' => ['vehiculos.listar', 'vehiculos.ver'],
        'vehiculos_crear' => ['vehiculos.crear'],
        'vehiculos_editar' => ['vehiculos.editar'],
        'vehiculos_eliminar' => ['vehiculos.eliminar'],
        'vehiculos_vincular' => ['vehiculos.vincular'],
        'consulta_vehiculos' => ['vehiculos.ver'],

        'importar_all' => ['importar.mandamientos', 'importar.personas', 'importar.telefonos', 'importar.vehiculos'],
        'consulta_all' => ['consultas.mandamientos', 'consultas.registro_criminal', 'mandamientos.ver', 'registro_criminal.ver', 'personas.ver'],
        'reporte_all' => ['reportes.exportar'],
    ];

    /**
     * Permisos de cada rol en `config/acl.php` del sistema anterior (copia fiel al 2026-10-22).
     *
     * @var array<string, list<string>>
     */
    private const ACL_ANTERIOR = [
        'superadmin' => ['users_all', 'mandamientos_all', 'personas_all', 'vehiculos_all', 'telefonos_all', 'imeis_all', 'importar_all', 'consulta_all', 'registro-criminal_all', 'reporte_all'],
        'administrador' => ['users_all', 'mandamientos_all', 'personas_all', 'vehiculos_all', 'telefonos_all', 'imeis_all', 'importar_all', 'consulta_all', 'registro-criminal_all', 'reporte_all'],
        'tecnico_felcc' => ['consulta_mandamientos', 'personas_crear', 'mandamientos_crear', 'mandamientos_edit'],
        'tecnico_daci' => [
            'registro-criminal_crear', 'registro-criminal_listar', 'registro-criminal_editar',
            'personas_crear', 'personas_listar', 'personas_editar',
            'mandamientos_listar', 'mandamientos_crear', 'mandamientos_edit',
            'vehiculos_listar', 'vehiculos_crear', 'vehiculos_vincular', 'vehiculos_editar',
            'telefonos_listar', 'telefonos_crear', 'telefonos_editar',
            'imeis_listar', 'imeis_crear', 'imeis_editar',
            'consulta_mandamientos', 'consulta_registro-criminal',
        ],
        'consultor_felcc' => ['consulta_mandamientos'],
        'consultor_daci' => ['consulta_mandamientos', 'consulta_personas', 'consulta_registro-criminal', 'consulta_vehiculos', 'consulta_telefonos'],
    ];

    /**
     * Accesos que el sistema anterior daba por NOMBRE DE ROL (menú lateral `@canany` y botones `window.role`), no por
     * permiso. Se convierten en permisos para que el rol conserve exactamente lo que veía.
     *
     * @var array<string, list<string>>
     */
    private const ACCESOS_POR_ROL = [
        'superadmin' => ['auditoria.consultas', 'auditoria.cambios'],
        'administrador' => ['auditoria.consultas', 'auditoria.cambios'],
        'tecnico_daci' => ['registro_criminal.ficha', 'importar.mandamientos', 'importar.telefonos', 'importar.vehiculos'],
        'tecnico_felcc' => ['importar.mandamientos'],
    ];

    private const GUARD = 'web';

    public function up(): void
    {
        $tablaAnteriorExiste = Schema::hasTable('roles') && Schema::hasColumn('roles', 'nombre');

        // Nunca pisar ni borrar tablas que no creó esta migración.
        $ocupadas = collect(['permissions', 'model_has_permissions', 'model_has_roles', 'role_has_permissions', 'roles_legado'])
            ->when(! $tablaAnteriorExiste, fn ($tablas) => $tablas->push('roles'))
            ->filter(fn (string $tabla) => Schema::hasTable($tabla));

        if ($ocupadas->isNotEmpty()) {
            throw new RuntimeException('Migración de roles detenida sin cambios: ya existen las tablas '.$ocupadas->implode(', ').'.');
        }

        if ($tablaAnteriorExiste) {
            Schema::rename('roles', 'roles_legado');
        }

        // Si algo falla a mitad (dato inesperado en producción), la base vuelve a como estaba: la migración no
        // queda registrada y `migrate:rollback` no podría deshacerla.
        try {
            $this->crearTablasSpatie();
            $this->migrarRolesYUsuarios($tablaAnteriorExiste);
        } catch (Throwable $error) {
            $this->down();

            throw $error;
        }

        rescue(fn () => app('cache')->forget(config('permission.cache.key', 'spatie.permission.cache')), report: false);
    }

    /**
     * Crea los permisos, los roles (mismos ids que en la tabla anterior) con sus permisos traducidos y asigna a
     * cada usuario el rol que tenía en `users.role_id`.
     */
    private function migrarRolesYUsuarios(bool $tablaAnteriorExiste): void
    {
        $ahora = now();

        $permisos = collect(self::TRADUCCION)->flatten()
            ->merge(collect(self::ACCESOS_POR_ROL)->flatten())
            ->unique()
            ->sort()
            ->values();

        DB::table('permissions')->insert($permisos->map(fn (string $nombre) => [
            'name' => $nombre,
            'guard_name' => self::GUARD,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ])->all());

        $idPermiso = DB::table('permissions')->pluck('id', 'name');

        $rolesAnteriores = $tablaAnteriorExiste
            ? DB::table('roles_legado')->orderBy('id')->get(['id', 'nombre', 'descripcion'])
            : collect();

        $nombresDeRoles = $rolesAnteriores->pluck('nombre')->merge(array_keys(self::ACL_ANTERIOR))->unique();
        $anteriorPorNombre = $rolesAnteriores->keyBy('nombre');

        foreach ($nombresDeRoles as $nombreRol) {
            $anterior = $anteriorPorNombre->get($nombreRol);

            // Mismo id que en la tabla anterior: las auditorías y cualquier referencia histórica siguen cuadrando.
            $idRol = DB::table('roles')->insertGetId(array_filter([
                'id' => $anterior?->id,
                'name' => $nombreRol,
                'guard_name' => self::GUARD,
                'descripcion' => $anterior?->descripcion,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ], fn ($valor) => $valor !== null));

            $permisosDelRol = collect(self::ACL_ANTERIOR[$nombreRol] ?? [])
                ->flatMap(fn (string $anterior) => self::TRADUCCION[$anterior] ?? [])
                ->merge(self::ACCESOS_POR_ROL[$nombreRol] ?? [])
                ->unique();

            DB::table('role_has_permissions')->insert($permisosDelRol->map(fn (string $permiso) => [
                'permission_id' => $idPermiso[$permiso],
                'role_id' => $idRol,
            ])->values()->all());
        }

        if ($tablaAnteriorExiste) {
            $idRolNuevo = DB::table('roles')->pluck('id', 'name');

            DB::table('users')
                ->join('roles_legado', 'roles_legado.id', '=', 'users.role_id')
                ->orderBy('users.id')
                ->get(['users.id', 'roles_legado.nombre'])
                ->each(fn (object $usuario) => DB::table('model_has_roles')->insert([
                    'role_id' => $idRolNuevo[$usuario->nombre],
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $usuario->id,
                ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');

        if (Schema::hasTable('roles_legado')) {
            Schema::rename('roles_legado', 'roles');
        }

        rescue(fn () => app('cache')->forget(config('permission.cache.key', 'spatie.permission.cache')), report: false);
    }

    /**
     * Tablas de spatie/laravel-permission v8 (stub oficial, sin equipos), con `roles.descripcion`.
     */
    private function crearTablasSpatie(): void
    {
        Schema::create('permissions', static function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', static function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->string('descripcion')->nullable();
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', static function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');

            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create('model_has_roles', static function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');

            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::create('role_has_permissions', static function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');

            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });
    }
};
