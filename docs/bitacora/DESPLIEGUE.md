# Guía de despliegue — DACI reemplaza a FELCC

Pasos para poner DACI en producción sobre la **misma base** que usa hoy FELCC. Sólo cambia el esquema de roles y
permisos (migración reversible). Hacerlo en una ventana sin usuarios conectados.

## 0. Antes

1. Respaldo completo de la base de producción: `mysqldump` (o `php artisan db:backup` desde FELCC).
2. Copia de la carpeta de archivos de FELCC: `storage/app/public/` (personas, mandamientos, mandamientos-pdf,
   mandamientos-img-actas, mandamientos-pdf-actas, registro-criminal, vehiculos).
3. Servidor: PHP 8.3+ con `pdo_mysql`, `imagick` (fotos de registro criminal a WebP), `gd`, `zip`, `intl`, `mbstring`;
   Node 20+ sólo para compilar el frontend.

## 1. Código

```bash
git clone … daci && cd daci
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env    # completar DB_*, APP_URL, APP_KEY (php artisan key:generate), APP_ENV=production, APP_DEBUG=false
```

## 2. Archivos

Copiar la carpeta de FELCC con la misma estructura y crear el enlace público:

```bash
cp -r /ruta/felcc/storage/app/public/. storage/app/public/
php artisan storage:link
```

Las rutas guardadas en `multimedia.ruta` y `fotos_registro.ruta_archivo` son relativas a ese disco y no cambian.

## 3. Base de datos

> La base de producción **no es la local**: tiene más información y su estado puede ser otro. No se borra nada.

```bash
php artisan migrate:status     # revisar qué figura como «Pending» (sólo lee)
```

Lo esperado son las pendientes de DACI: `2026_10_22_120000_migrar_roles_a_spatie_permission` y
`2026_10_26_210000_crear_tablas_faltas_y_contravenciones` (sólo CREA 4 tablas nuevas; no toca ninguna existente). Si además figuran
migraciones del propio FELCC (la copia local `felcc` tenía pendientes `remove_unique_from_ci_in_persona_table` y
`create_audits_table`), **detenerse y revisar** antes de seguir:

- `create_audits_table`: DACI la necesita (casi todos los modelos registran auditoría; sin la tabla, todo guardado
  falla). Si la tabla `audits` ya existe pero la migración no figura, `migrate` fallará: registrar la migración
  como ejecutada en vez de correrla.
- `remove_unique_from_ci_in_persona_table`: quita el índice único de `persona.ci` (no borra datos; no se puede
  revertir). DACI permite C.I. repetidos, como el FELCC actual.

```bash
php artisan migrate --force    # renombra roles → roles_legado y crea las tablas de Spatie con los roles
php artisan db:seed --class=PermisosSeeder --force  # = acceso:sincronizar; sólo agrega permisos/roles base, repara users.role_id
```

La migración de roles se protege sola: **se detiene sin cambios** si ya existe alguna tabla de Spatie, y si falla a
mitad (p. ej. un rol escrito con otras mayúsculas que choca con un rol base) **deshace lo hecho** y deja `roles`
como estaba. En ese caso, corregir el dato con el usuario y volver a correrla.

**No** correr `migrate:fresh`, `migrate:refresh`, `db:wipe` ni `db:seed --class=InstalacionNuevaSeeder`.

Verificación rápida:

```bash
php artisan tinker --execute 'echo DB::table("model_has_roles")->count()." usuarios con rol / ".DB::table("users")->whereNotNull("role_id")->count()." con role_id";'
```

Si algo sale mal: `php artisan migrate:rollback --step=1` deja la tabla `roles` como estaba (FELCC vuelve a funcionar).
Los usuarios creados o cambiados de rol en DACI conservan su rol en FELCC porque `users.role_id` se mantiene en
espejo; sólo quien tenga un rol **creado en DACI** queda sin rol en FELCC. Los roles y permisos editados en DACI se
pierden con el rollback (viven en las tablas de Spatie).

## 4. Optimización y tareas

```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Programador (respaldo diario a las 00:00): `* * * * * cd /ruta/daci && php artisan schedule:run >> /dev/null 2>&1`.

## 5. Después

- Entrar como superadmin y revisar Seguridad › Roles y permisos.
- Dar de baja FELCC (o dejarlo sin acceso público) para que no haya dos sistemas escribiendo en la misma base.
