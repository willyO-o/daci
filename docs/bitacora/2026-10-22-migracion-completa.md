# 2026-10-22 · Migración completa FELCC → DACI

| Dato | Valor |
|---|---|
| Fecha | 2026-10-22 |
| Hora | 19:50 – 22:51 (Bolivia) |
| Rama | `main` de `daci` (sin commits; cambios en el árbol de trabajo) |
| Alcance | Fases 0 a 11 del plan |

## Qué se hizo

1. **Base segura.** `.env` corregido (`DB_CONNECTION=root` → `mysql`, idioma `es`), zona `America/La_Paz`,
   dependencias (laravel-auditing, OpenSpout 4, FPDF/FPDI, Intervention v3, Agent; Bootstrap 5, SweetAlert2, axios),
   Tailwind retirado. Las 36 migraciones del anterior copiadas como registro (idénticas, excluidas de Pint).
   Respaldo de `roles`, `users` y `migrations` en `storage/app/private/respaldos/`.
2. **Roles y permisos.** Migración `2026_10_22_120000_migrar_roles_a_spatie_permission`: probada en una base temporal
   (subir → verificar → revertir), luego aplicada en `felcc1`: 6 roles con sus ids, 49 permisos, 15/15 usuarios.
   Catálogo `App\Support\Acceso\CatalogoPermisos`, comando `acceso:sincronizar`, superadmin con `Gate::before`.
3. **Skills y bitácora.** `convenciones-base-datos` (adaptada), `felcc-frontend-vue`, `felcc-permisos`; esta carpeta.
4. **Frontend.** Layout Velzon persistente, componentes base (modal, paginación, buscador remoto, selector de
   catálogo con alta, subida de archivos, rango de fechas, visor de imágenes, lista infinita), directivas de
   escritura, composables (`usePermisos`, `useListado`, `useCargaInfinita`, `useFormularioJson`, `useNotificacion`).
5. **Módulos** (todos): ver `MATRIZ-PARIDAD.md`.
6. **Pruebas.** 86 pruebas (acceso, migración de roles, rutas con permiso, menú, cada pantalla, módulos,
   importaciones, reportes) sobre `felcc_pruebas`. Prueba de humo de solo lectura con los datos reales: 0 errores.

## Decisiones

D-001 a D-016 en `DECISIONES.md`. Las más importantes: tablas estándar de Spatie con `roles_legado` como respaldo;
traducción congelada de permisos; modelos en `App\Models` por la auditoría; pruebas en MariaDB; «eliminar
duplicados» como comando.

## Archivos principales

`app/Http/Controllers/*`, `app/Support/{Acceso/CatalogoPermisos,LectorTabular,AccesosConsultados,UbicacionIp}.php`,
`app/Console/Commands/{SincronizarAcceso,EliminarPersonasDuplicadas}.php`, `database/migrations/2026_10_22_120000_*`,
`routes/web.php`, `resources/js/**`, `tests/Feature/**`, `docs/bitacora/**`, `.claude/skills/{convenciones-base-datos,
felcc-frontend-vue,felcc-permisos}`.

## Pendiente

Prueba manual en navegador, decisiones abiertas de `ESTADO-ACTUAL.md` y despliegue (`DESPLIEGUE.md`).
