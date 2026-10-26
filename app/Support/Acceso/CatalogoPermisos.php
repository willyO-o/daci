<?php

namespace App\Support\Acceso;

/**
 * Catálogo único de PERMISOS del sistema (guard `web`). Los permisos son fijos: los define el código y cada
 * controlador los exige con el middleware `permission:` de Spatie. Los ROLES son dinámicos: se crean y editan
 * en Seguridad › Roles y permisos.
 *
 * El mismo slug lo usan:
 *  - las rutas (`->middleware('permission:personas.crear')`),
 *  - el menú lateral (`resources/js/Navegacion/navegacion.js`),
 *  - los botones de cada pantalla (`usePermisos().puede('personas.crear')`).
 *
 * Para añadir un permiso: sumarlo a {@see self::modulos()}, declarar en {@see self::asignacionInicial()} qué roles
 * lo reciben al crearse, protegerlo en su ruta y correr `php artisan db:seed --class=PermisosSeeder` (o
 * `php artisan acceso:sincronizar`, que hace lo mismo): crea el permiso, se lo da al superadmin y a esos roles.
 */
final class CatalogoPermisos
{
    public const GUARD = 'web';

    public const ROL_SUPERADMIN = 'superadmin';

    public const ROL_ADMINISTRADOR = 'administrador';

    public const ROL_TECNICO_FELCC = 'tecnico_felcc';

    public const ROL_CONSULTOR_FELCC = 'consultor_felcc';

    public const ROL_TECNICO_DACI = 'tecnico_daci';

    public const ROL_CONSULTOR_DACI = 'consultor_daci';

    /**
     * Módulos con sus permisos y la etiqueta que muestra la pantalla de roles.
     *
     * @return array<string, array{etiqueta: string, permisos: array<string, string>}>
     */
    public static function modulos(): array
    {
        return [
            'mandamientos' => [
                'etiqueta' => 'Mandamientos',
                'permisos' => [
                    'mandamientos.listar' => 'Ver el listado',
                    'mandamientos.ver' => 'Ver el detalle',
                    'mandamientos.crear' => 'Registrar',
                    'mandamientos.editar' => 'Editar',
                    'mandamientos.eliminar' => 'Eliminar',
                ],
            ],
            'consultas' => [
                'etiqueta' => 'Consultas',
                'permisos' => [
                    'consultas.mandamientos' => 'Consultar mandamientos',
                    'consultas.registro_criminal' => 'Consultar registro criminal',
                ],
            ],
            'registro_criminal' => [
                'etiqueta' => 'Registro criminal',
                'permisos' => [
                    'registro_criminal.listar' => 'Ver el listado',
                    'registro_criminal.ver' => 'Ver el detalle',
                    'registro_criminal.crear' => 'Registrar',
                    'registro_criminal.editar' => 'Editar',
                    'registro_criminal.eliminar' => 'Eliminar',
                    'registro_criminal.ficha' => 'Imprimir ficha de registro',
                ],
            ],
            'personas' => [
                'etiqueta' => 'Personas',
                'permisos' => [
                    'personas.listar' => 'Ver el listado',
                    'personas.ver' => 'Ver el detalle',
                    'personas.crear' => 'Registrar',
                    'personas.editar' => 'Editar',
                    'personas.eliminar' => 'Eliminar o migrar datos',
                    'personas.restaurar' => 'Ver eliminados y restaurar',
                ],
            ],
            'telefonos' => [
                'etiqueta' => 'Teléfonos',
                'permisos' => [
                    'telefonos.listar' => 'Ver el listado',
                    'telefonos.ver' => 'Ver el detalle',
                    'telefonos.crear' => 'Registrar',
                    'telefonos.editar' => 'Editar y vincular persona',
                    'telefonos.eliminar' => 'Eliminar',
                ],
            ],
            'imeis' => [
                'etiqueta' => 'IMEIs',
                'permisos' => [
                    'imeis.listar' => 'Ver el listado',
                    'imeis.ver' => 'Ver el detalle',
                    'imeis.crear' => 'Registrar y vincular teléfono',
                    'imeis.editar' => 'Editar',
                    'imeis.eliminar' => 'Eliminar',
                ],
            ],
            'vehiculos' => [
                'etiqueta' => 'Vehículos',
                'permisos' => [
                    'vehiculos.listar' => 'Ver el listado',
                    'vehiculos.ver' => 'Ver el detalle',
                    'vehiculos.crear' => 'Registrar',
                    'vehiculos.editar' => 'Editar',
                    'vehiculos.eliminar' => 'Eliminar',
                    'vehiculos.vincular' => 'Vincular y desvincular personas',
                ],
            ],
            'importar' => [
                'etiqueta' => 'Importación',
                'permisos' => [
                    'importar.mandamientos' => 'Importar mandamientos',
                    'importar.personas' => 'Importar personas',
                    'importar.telefonos' => 'Importar teléfonos e IMEIs',
                    'importar.vehiculos' => 'Importar vehículos, carguíos e ITV',
                ],
            ],
            'reportes' => [
                'etiqueta' => 'Reportes',
                'permisos' => [
                    'reportes.exportar' => 'Ver y exportar reportes',
                ],
            ],
            'usuarios' => [
                'etiqueta' => 'Usuarios',
                'permisos' => [
                    'usuarios.listar' => 'Ver el listado',
                    'usuarios.crear' => 'Registrar',
                    'usuarios.editar' => 'Editar',
                    'usuarios.eliminar' => 'Eliminar',
                    'usuarios.activar' => 'Activar y desactivar',
                ],
            ],
            'roles' => [
                'etiqueta' => 'Roles y permisos',
                'permisos' => [
                    'roles.listar' => 'Ver roles',
                    'roles.gestionar' => 'Crear, editar y eliminar roles',
                ],
            ],
            'auditoria' => [
                'etiqueta' => 'Auditoría',
                'permisos' => [
                    'auditoria.consultas' => 'Auditoría de consultas',
                    'auditoria.cambios' => 'Auditoría de cambios',
                ],
            ],
        ];
    }

    /**
     * Todos los slugs del catálogo.
     *
     * @return list<string>
     */
    public static function permisos(): array
    {
        $permisos = [];

        foreach (self::modulos() as $modulo) {
            array_push($permisos, ...array_keys($modulo['permisos']));
        }

        return $permisos;
    }

    /**
     * Roles que reciben un permiso NUEVO la primera vez que se crea en la base (`PermisosSeeder` /
     * `acceso:sincronizar`). Sólo se aplica en esa corrida: después, el permiso se administra desde la pantalla de
     * roles y volver a sembrar no lo reasigna. El superadmin no se lista: recibe todos siempre.
     *
     * Al agregar un permiso al catálogo, declarar aquí quién debe tenerlo desde el inicio, por ejemplo:
     *
     *     'reportes.pdf' => [self::ROL_ADMINISTRADOR],
     *
     * @return array<string, list<string>>
     */
    public static function asignacionInicial(): array
    {
        return [
            // Los 49 permisos originales se asignaron en la migración de roles (2026_10_22_120000).
        ];
    }

    /**
     * Roles base del sistema (los 6 heredados de FELCC). No se pueden eliminar desde la pantalla de roles.
     *
     * @return list<string>
     */
    public static function rolesBase(): array
    {
        return [
            self::ROL_SUPERADMIN,
            self::ROL_ADMINISTRADOR,
            self::ROL_TECNICO_FELCC,
            self::ROL_CONSULTOR_FELCC,
            self::ROL_TECNICO_DACI,
            self::ROL_CONSULTOR_DACI,
        ];
    }
}
