# 2026-10-23 · Roles coherentes con Spatie y migración segura para producción

**Hora:** 10:45 – 11:05 (Bolivia) · **Pedido:** «analiza el proyecto, la bitácora y la situación actual y verifica si
queda algo pendiente»; luego: «producción tiene otra base con mucha más información, no se debe eliminar; lo que
podemos modificar son los roles, permisos y sus controles en el servidor, para que sea consistente con Spatie».

## Revisión

- Las 36 migraciones de FELCC están idénticas en DACI; la única nueva es la de roles.
- La traducción de permisos se volvió a cruzar con `felcc/config/acl.php`, los chequeos de cada controlador, el menú
  (`@canany` por nombre de rol) y los botones (`window.role`): coincide.
- En DACI ningún control (servidor ni Vue) depende del nombre del rol, salvo el superadmin (`Gate::before`).
- La copia local `felcc` (en esta máquina; no hay `felcc1`) tiene **34** migraciones: faltan
  `remove_unique_from_ci_in_persona_table` y `create_audits_table` (no existe la tabla `audits`). Producción es otra
  base y debe verificarse antes de desplegar (D-018).

## Qué se hizo (D-018)

1. `User::asignarRol()`: asigna el rol en Spatie y deja `users.role_id` en espejo (id del mismo rol en
   `roles_legado`, o null si el rol es nuevo). `UserController` lo usa al crear y editar.
2. `acceso:sincronizar` repara `users.role_id` de todos los usuarios con rol en Spatie (también eliminados); no toca
   a quien no tiene rol en Spatie.
3. Migración de roles: se detiene sin cambios si ya existe alguna tabla de Spatie o `roles_legado`; si falla a mitad,
   ejecuta `down()` y deja `roles` como estaba.
4. `DESPLIEGUE.md`: producción es otra base; qué hacer si hay migraciones de FELCC pendientes; alcance del rollback.
5. Skills `convenciones-base-datos` y `felcc-permisos` actualizadas (producción ≠ local; `asignarRol`).
6. Creada la base vacía `felcc_pruebas` en el Laragon local para correr las pruebas.

## Archivos

`app/Models/User.php` · `app/Http/Controllers/UserController.php` · `app/Console/Commands/SincronizarAcceso.php` ·
`database/migrations/2026_10_22_120000_migrar_roles_a_spatie_permission.php` ·
`tests/Feature/Acceso/{UsuariosTest,MigracionRolesTest}.php` · `docs/bitacora/*` · `.claude/skills/{convenciones-base-datos,felcc-permisos}/SKILL.md`.

## Resultado

100 pruebas / 578 aserciones en verde (4 nuevas) · Pint OK. La base `felcc` local no se modificó.

## Pendiente

- En producción: `php artisan migrate:status` y decidir con el usuario si hay migraciones de FELCC pendientes.
- `.env` local apunta a `felcc` (la bitácora anterior hablaba de `felcc1`); `APP_NAME=Laravel`.
- Páginas de error propias (FELCC tenía `errors/404`).
- Lo que ya figuraba en `ESTADO-ACTUAL.md`.
