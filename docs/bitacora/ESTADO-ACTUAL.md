# Estado actual

> Foto del presente. Se reescribe al cerrar cada ronda. Última actualización: 2026-10-27 14:12 (Bolivia).

## Resumen

**Toda la funcionalidad de FELCC está migrada a DACI** (ver `MATRIZ-PARIDAD.md`). La base `felcc1` sólo cambió en
roles y permisos (migración reversible aplicada). 96 pruebas automáticas en verde y prueba de humo de solo lectura
contra los datos reales sin errores. Revisión de bugs del 2026-10-22: 10 correcciones (D-017); las fotos se ven en FsLightbox como antes.
2026-10-23: `users.role_id` en espejo del rol de Spatie y migración de roles que se detiene o se deshace sola ante datos
inesperados (D-018). 100 pruebas en verde.
2026-10-24: segunda verificación de paridad; se agregaron el panel «Personalizar tema», «volver arriba», la pantalla de
carga y las páginas de error de la plantilla (D-019). 103 pruebas en verde.
2026-10-26: `PermisosSeeder` para actualizar permisos cada vez que se agregan, sin afectar el resto de la base (D-020).
2026-10-26: botón «Estado de ejecución» en Consultas › Mandamientos, permiso `mandamientos.estado` (D-021).
2026-10-26: páginas «Registrar Persona / Teléfono / IMEI / Vehículo» con el permiso «Registrar» y tarjetas de
registro rápido en el Dashboard (D-022).
2026-10-26: módulo **Faltas y Contravenciones**, igual a Registro Criminal, con sus propias tablas (sólo tablas
nuevas) y permisos (D-023).
2026-10-27: rol `pol_110` (consulta mandamientos y registra personas, teléfonos, IMEIs y vehículos sin ver los
listados) con `RolesSeeder` (D-024).
2026-10-27: rol `conciliacion_ciudadana`; Faltas y Contravenciones sólo para él y los administradores (D-025).
2026-10-27: al editar un registro se pueden actualizar estado civil y ocupación de la persona (D-026).
2026-10-27: al registrar se busca la persona por C.I. + complemento con COALESCE y se avisa si existe (D-027). 161 pruebas.

> **Producción es otra base** (más información que la local). No se borra nada; sólo se tocan roles, permisos y sus
> controles. Su estado de migraciones se verifica allí antes de desplegar.

## Módulos

| Módulo | Estado |
|---|---|
| Acceso (login, recuperar contraseña, usuario inactivo) | ✅ |
| Layout Velzon persistente, menú por permisos, modo oscuro | ✅ |
| Usuarios · Roles y permisos dinámicos (nuevo) · Perfil | ✅ |
| Dashboard | ✅ |
| Personas | ✅ |
| Mandamientos · Registrar · Consulta auditada · Vista por código | ✅ |
| Registro criminal · Consulta auditada · Vista por código · Ficha + PDF | ✅ |
| Faltas y Contravenciones (nuevo: mismo funcionamiento que Registro criminal) | ✅ (migrado en local; falta prueba manual y producción) |
| Teléfonos · IMEIs · Vehículos (carguíos, ITV) | ✅ |
| Importaciones (mandamientos, personas, teléfonos, IMEIs, vehículos, carguíos, ITV) | ✅ |
| Reportes CSV | ✅ |
| Auditoría de consultas · Auditoría de cambios | ✅ |
| Respaldo diario, verificación, eliminar duplicados (consola) | ✅ |

## Pendiente / a decidir con el usuario

1. **Prueba manual en el navegador** de cada pantalla (no se pudo abrir un navegador desde esta sesión). Arrancar con
   `composer run dev` o `php artisan serve` + `npm run dev` y entrar con un usuario superadmin.
2. Importación de teléfonos: ¿debe actualizar un número existente en lugar de crear otro? (D-015).
3. Reportes PDF y filtros: el anterior los anunciaba pero no existían. ¿Se implementan?
4. Validar con usuarios reales los permisos de los roles base (traducción en la migración de roles).
5. Despliegue: seguir `DESPLIEGUE.md`. **Antes**, `php artisan migrate:status` en producción: si hay migraciones de
   FELCC pendientes (la copia local no tenía `create_audits_table` ni `remove_unique_from_ci_in_persona_table`),
   decidir con el usuario. Sin la tabla `audits`, DACI no puede guardar.
6. `.env` local: apunta a `felcc` (no `felcc1`) y `APP_NAME=Laravel`.
7. ~~Páginas de error propias~~ — hechas el 2026-10-24 (D-019).
8. ¿Layouts horizontal y de dos columnas en «Personalizar tema»? (D-019).
9. Login «propuesta 5»: confirmar el nombre de la sigla DACI («Dirección de Análisis de Información Criminal» en la propuesta vs. «Departamento de Análisis Criminal e Inteligencia» en la ficha).

## Pruebas

`php artisan test` corre sobre **`felcc_pruebas`** (MariaDB; un candado impide usar `felcc1`).

## Cómo retomar

Leer este archivo, `PLAN-MIGRACION.md`, `DECISIONES.md` y las skills `.claude/skills/{convenciones-base-datos,
felcc-frontend-vue,felcc-permisos}`.
