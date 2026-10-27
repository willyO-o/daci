/**
 * Menú lateral del sistema. Es la ÚNICA fuente del sidebar: `AppSidebar` lo recorre y oculta lo que el rol no puede
 * usar. Conserva las secciones, los textos y los íconos del sistema anterior.
 *
 * Sección:  { titulo: string, items: Item[] }      — se oculta sola si ningún ítem es visible.
 *
 * Ítem:
 *   {
 *     etiqueta: string,
 *     icono?: string,               // clase de ícono de la plantilla (mdi-*, ri-*)
 *     ruta?: string,                // nombre de ruta Ziggy
 *     activo?: string|string[],     // patrones de `route().current()` que encienden el ítem (por defecto, `ruta`)
 *     permiso?: string|string[]|((puede) => boolean), // string = ese permiso; array = cualquiera; función = regla
 *     hijos?: Item[],               // submenú desplegable
 *   }
 *
 * Los `permiso` son los mismos slugs que exige la ruta en el servidor (`App\Support\Acceso\CatalogoPermisos`), así un
 * ítem visible nunca termina en 403. Lo comprueba `tests/Feature/NavegacionTest.php`.
 */
export const navegacion = [
    {
        titulo: 'Menú',
        items: [
            { etiqueta: 'Dashboard', icono: 'mdi mdi-speedometer', ruta: 'dashboard' },
            {
                etiqueta: 'Mandamientos',
                icono: 'mdi mdi-gavel',
                ruta: 'mandamientos.index',
                activo: ['mandamientos.index', 'mandamientos.show', 'tipos-mandamientos.*'],
                permiso: 'mandamientos.listar',
            },
            {
                // El técnico FELCC registra mandamientos pero no ve el listado (igual que en el sistema anterior).
                etiqueta: 'Registrar Mandamiento',
                icono: 'mdi mdi-plus',
                ruta: 'mandamientos.create',
                permiso: (puede) => puede('mandamientos.crear') && !puede('mandamientos.listar'),
            },
        ],
    },
    {
        titulo: 'Consultas',
        items: [
            {
                etiqueta: 'Consultar Mandamientos',
                icono: 'mdi mdi-clipboard-text-search-outline',
                ruta: 'consultas.mandamientos',
                activo: ['consultas.mandamientos', 'mandamientos.showByCodigo'],
                permiso: 'consultas.mandamientos',
            },
            {
                etiqueta: 'Consultar Registro Criminal',
                icono: 'mdi mdi-account-search-outline',
                ruta: 'consultas.registro-criminal',
                activo: ['consultas.registro-criminal', 'registro-criminal.showByCodigo'],
                permiso: 'consultas.registro_criminal',
            },
            {
                etiqueta: 'Consultar Faltas y Contravenciones',
                icono: 'mdi mdi-account-search-outline',
                ruta: 'consultas.faltas-contravenciones',
                activo: ['consultas.faltas-contravenciones', 'faltas-contravenciones.showByCodigo'],
                permiso: 'consultas.faltas_contravenciones',
            },
        ],
    },
    {
        titulo: 'EREBOR',
        // «Registrar …»: para quien registra pero no ve el listado (igual que «Registrar Mandamiento»).
        items: [
            {
                etiqueta: 'Registro Criminal',
                icono: 'mdi mdi-head',
                ruta: 'registro-criminal.index',
                activo: ['registro-criminal.index', 'registro-criminal.create', 'registro-criminal.edit'],
                permiso: 'registro_criminal.listar',
            },
            {
                etiqueta: 'Faltas y Contravenciones',
                icono: 'mdi mdi-alert-circle-outline',
                ruta: 'faltas-contravenciones.index',
                activo: ['faltas-contravenciones.index', 'faltas-contravenciones.create', 'faltas-contravenciones.edit'],
                permiso: 'faltas_contravenciones.listar',
            },
            {
                etiqueta: 'Personas',
                icono: 'mdi mdi-account-outline',
                ruta: 'personas.index',
                activo: ['personas.index', 'personas.show'],
                permiso: 'personas.listar',
            },
            {
                etiqueta: 'Registrar Persona',
                icono: 'mdi mdi-account-plus-outline',
                ruta: 'personas.create',
                permiso: (puede) => puede('personas.crear') && !puede('personas.listar'),
            },
            {
                etiqueta: 'Teléfonos',
                icono: 'mdi mdi-phone',
                ruta: 'telefonos.index',
                activo: ['telefonos.index', 'telefonos.show'],
                permiso: 'telefonos.listar',
            },
            {
                etiqueta: 'Registrar Teléfono',
                icono: 'mdi mdi-phone-plus',
                ruta: 'telefonos.create',
                permiso: (puede) => puede('telefonos.crear') && !puede('telefonos.listar'),
            },
            {
                etiqueta: 'IMEIs',
                icono: 'mdi mdi-numeric',
                ruta: 'imeis.index',
                activo: ['imeis.index', 'imeis.show'],
                permiso: 'imeis.listar',
            },
            {
                etiqueta: 'Registrar IMEI',
                icono: 'mdi mdi-plus-box-outline',
                ruta: 'imeis.create',
                permiso: (puede) => puede('imeis.crear') && !puede('imeis.listar'),
            },
            {
                etiqueta: 'Vehículos',
                icono: 'mdi mdi-car',
                ruta: 'vehiculos.index',
                activo: ['vehiculos.index', 'vehiculos.show'],
                permiso: 'vehiculos.listar',
            },
            {
                etiqueta: 'Registrar Vehículo',
                icono: 'mdi mdi-car-arrow-right',
                ruta: 'vehiculos.create',
                permiso: (puede) => puede('vehiculos.crear') && !puede('vehiculos.listar'),
            },
        ],
    },
    {
        titulo: 'Importar',
        items: [
            {
                etiqueta: 'Importar',
                icono: 'mdi mdi-upload',
                hijos: [
                    { etiqueta: 'Importar Mandamientos', ruta: 'importar.mandamientos.index', permiso: 'importar.mandamientos' },
                    { etiqueta: 'Importar Telefonos', ruta: 'telefonos.importar.index', permiso: 'importar.telefonos' },
                    { etiqueta: 'Importar Vehículos', ruta: 'vehiculos.importar.index', permiso: 'importar.vehiculos' },
                ],
            },
        ],
    },
    {
        titulo: 'Reportes',
        items: [
            {
                etiqueta: 'Reportes',
                icono: 'mdi mdi-file-chart-outline',
                ruta: 'reportes.index',
                activo: 'reportes.*',
                permiso: 'reportes.exportar',
            },
        ],
    },
    {
        titulo: 'Seguridad',
        items: [
            { etiqueta: 'Usuarios', icono: 'mdi mdi-account-group-outline', ruta: 'usuarios.index', activo: 'usuarios.*', permiso: 'usuarios.listar' },
            { etiqueta: 'Roles y Permisos', icono: 'mdi mdi-shield-key-outline', ruta: 'roles.index', activo: 'roles.*', permiso: 'roles.listar' },
            {
                etiqueta: 'Auditoría de Consultas',
                icono: 'mdi mdi-history',
                ruta: 'auditar-consultas.index',
                activo: 'auditar-consultas.*',
                permiso: 'auditoria.consultas',
            },
            {
                etiqueta: 'Auditoría de Cambios',
                icono: 'mdi mdi-shield-check-outline',
                ruta: 'auditorias.index',
                activo: 'auditorias.*',
                permiso: 'auditoria.cambios',
            },
        ],
    },
];
