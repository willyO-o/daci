# Plan de migración FELCC → DACI

Acordado con el usuario el 2026-10-22.

## Restricciones

1. **DACI reemplaza a FELCC.** Sólo funcionará el sistema nuevo; debe tener **las mismas funcionalidades** y luego se
   agregarán nuevas.
2. **La base de datos no se modifica**, excepto roles y permisos. Se usan las tablas estándar de Spatie; los roles
   actuales se migran sin perder ningún permiso (migración reversible, la tabla antigua queda como `roles_legado`).
3. **Ciudadano y sus tablas relacionadas se ignoran por completo** (`ciudadano`, `libro`, `notario`, `localidad`,
   `departamento`, `documento`).
4. **Archivos**: se copiará la carpeta de almacenamiento del sistema anterior con la misma estructura; se conservan
   las rutas (`storage/app/public/{personas,mandamientos,…}`).
5. **Apariencia**: plantilla Velzon (HTML en `felcc/material`), layout persistente y componentes reutilizables.
   Criterio de calidad de las skills impeccable / taste-skill aplicado a pantallas de trabajo.
6. Dependencias autorizadas siempre que no rompan la funcionalidad.
7. Convenciones de base de datos adaptadas de la skill de otro proyecto; convenciones de Vue y permisos tomadas de
   `tienda` (sin modificarlo).

## Fases

| Fase | Contenido | Estado |
|---|---|---|
| 0 | Base segura: `.env`, zona horaria, dependencias, migraciones heredadas como registro, migración de roles a Spatie, comando `acceso:sincronizar`, respaldo previo | ✅ |
| 1 | Skills del proyecto y bitácora | ✅ |
| 2 | Layout Velzon persistente, autenticación, componentes base, menú por permisos | ✅ |
| 3 | Accesos: usuarios, roles y permisos dinámicos, perfil | ✅ |
| 4 | Catálogos (países, divisiones, juzgados, delitos, tipos de mandamiento) | ✅ |
| 5 | Personas | ✅ |
| 6 | Mandamientos (+ multimedia, vista por código) | ✅ |
| 7 | Teléfonos, IMEIs, vehículos | ✅ |
| 8 | Registro criminal + ficha de registro + PDF | ✅ |
| 9 | Consultas + auditoría de consultas | ✅ |
| 10 | Importaciones, reportes, auditoría de cambios, mantenimiento | ✅ |
| 11 | Cierre: matriz de paridad completa, tests de autorización, guía de despliegue | ✅ |
