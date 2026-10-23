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

```bash
php artisan migrate:status     # deben figurar 36 migraciones «Ran» y 1 «Pending» (2026_10_22_120000_migrar_roles_a_spatie_permission)
php artisan migrate --force    # renombra roles → roles_legado y crea las tablas de Spatie con los 6 roles
php artisan acceso:sincronizar # idempotente: crea permisos nuevos del catálogo y se los da a superadmin
```

**No** correr `migrate:fresh`, `migrate:refresh`, `db:wipe` ni `db:seed --class=InstalacionNuevaSeeder`.

Verificación rápida:

```bash
php artisan tinker --execute 'echo DB::table("model_has_roles")->count()." usuarios con rol / ".DB::table("users")->whereNotNull("role_id")->count()." con role_id";'
```

Si algo sale mal: `php artisan migrate:rollback --step=1` deja la tabla `roles` como estaba (FELCC vuelve a funcionar).

## 4. Optimización y tareas

```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Programador (respaldo diario a las 00:00): `* * * * * cd /ruta/daci && php artisan schedule:run >> /dev/null 2>&1`.

## 5. Después

- Entrar como superadmin y revisar Seguridad › Roles y permisos.
- Dar de baja FELCC (o dejarlo sin acceso público) para que no haya dos sistemas escribiendo en la misma base.
