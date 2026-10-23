# Bitácora — migración FELCC → DACI

Registro de avances con fecha y hora de la migración del sistema FELCC (Laravel 12 + Blade + jQuery, en producción)
al sistema DACI (Laravel 13 + Breeze + Inertia v2 + Vue 3 + spatie/laravel-permission). Existe para que cualquier
persona o sesión pueda retomar el trabajo sin releer los dos proyectos y sin perder el porqué de cada decisión.

## Archivos

| Archivo | Qué es | Cuándo se toca |
|---|---|---|
| `ESTADO-ACTUAL.md` | **Foto del presente**: qué módulo está migrado, cuál falta, cuál es el siguiente paso. Se reescribe. | Al cerrar cada ronda de trabajo. |
| `PLAN-MIGRACION.md` | El plan por fases acordado con el usuario y las restricciones que lo gobiernan. | Si cambia el plan. |
| `MATRIZ-PARIDAD.md` | Cada ruta/funcionalidad del sistema anterior con su equivalente nuevo y su estado. Es el control de «migrar todo tal cual». | Al migrar cada módulo. |
| `DECISIONES.md` | Decisiones no obvias con su motivo y la alternativa descartada; bugs heredados corregidos o conservados. | Cuando se decide algo no obvio. |
| `DESPLIEGUE.md` | Pasos para poner DACI en producción sobre la base de FELCC. | Si cambia el procedimiento. |
| `AAAA-MM-DD-<tema>.md` | **Entrada**: lo que se hizo en una sesión, con hora, archivos y pendientes. Inmutable. | Una por sesión/tema. |

## Reglas

1. Zona horaria: **Bolivia (UTC−4)**. Las horas se leen con `date`, no se inventan.
2. Toda modificación queda registrada dentro de la misma tarea: entrada fechada + `ESTADO-ACTUAL.md` + `MATRIZ-PARIDAD.md`
   (+ `DECISIONES.md` si hubo una decisión).
3. Se documenta lo esencial: qué se construyó, qué se decidió y por qué, qué quedó pendiente.
4. Las **reglas permanentes** no van aquí sino en las skills del proyecto (`.claude/skills/`):
   `convenciones-base-datos`, `felcc-frontend-vue`, `felcc-permisos`.

## Proyectos

- Sistema anterior (referencia, NO se modifica): `../felcc` · plantilla Velzon HTML: `../material`.
- Convenciones de referencia (NO se modifica): `C:\Users\marco\Desktop\combustible\tienda`.
- Base de datos local: `felcc` / `felcc1` (MariaDB, Laragon) — **copias parciales**. Producción es otra base, con mucha
  más información: nunca se borra nada y su estado se verifica allí antes de desplegar.
