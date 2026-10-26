# 2026-10-26 · Seeder de permisos

**Hora:** 17:15 – 17:31 (Bolivia) · **Pedido:** «elabórame un seeder donde me actualice todos los permisos, tal que
cada vez que adicionemos alguno, al ejecutar el seeder debe volverse a insertar pero sin afectar el resto de la
información de la base de datos».

## Qué se hizo (D-020)

- `App\Support\Acceso\SincronizadorAcceso`: lógica única, en transacción; sólo agrega.
- `database/seeders/PermisosSeeder.php`: la ejecuta e informa lo creado, lo asignado y los permisos obsoletos.
- `CatalogoPermisos::asignacionInicial()`: qué roles reciben un permiso nuevo al crearse (hoy vacío).
- `acceso:sincronizar` ahora corre el seeder; `DatabaseSeeder` llama a `PermisosSeeder`.
- Skills `felcc-permisos` y `convenciones-base-datos`, `DESPLIEGUE.md`.

Uso: `php artisan db:seed --class=PermisosSeeder --force`.

## Resultado

109 pruebas en verde (6 nuevas) · Pint OK · corrida sobre la base local `felcc`: «49 en el catálogo, 0 nuevo(s)»,
tablas idénticas antes y después.
