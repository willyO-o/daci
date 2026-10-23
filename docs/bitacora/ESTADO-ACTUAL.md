# Estado actual

> Foto del presente. Se reescribe al cerrar cada ronda. Última actualización: 2026-10-22 23:15 (Bolivia).

## Resumen

**Toda la funcionalidad de FELCC está migrada a DACI** (ver `MATRIZ-PARIDAD.md`). La base `felcc1` sólo cambió en
roles y permisos (migración reversible aplicada). 96 pruebas automáticas en verde y prueba de humo de solo lectura
contra los datos reales sin errores. Revisión de bugs del 2026-10-22: 10 correcciones (D-017); las fotos se ven en FsLightbox como antes.

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
5. Despliegue: seguir `DESPLIEGUE.md`.

## Pruebas

`php artisan test` corre sobre **`felcc_pruebas`** (MariaDB; un candado impide usar `felcc1`).

## Cómo retomar

Leer este archivo, `PLAN-MIGRACION.md`, `DECISIONES.md` y las skills `.claude/skills/{convenciones-base-datos,
felcc-frontend-vue,felcc-permisos}`.
