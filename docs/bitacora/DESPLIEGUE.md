# Guía de despliegue — DACI reemplaza a FELCC

Paso a paso para reemplazar FELCC por DACI en producción **sobre la misma base de datos**. El servidor tiene internet
para descargar paquetes (PHP, extensiones, Composer), pero **no se conecta a repositorios git**: el código llega en un
**ZIP sin `vendor/` ni `node_modules/`** y el frontend compilado llega aparte (**`build/`**).

Los comandos son para **Linux (Ubuntu/Debian)**; donde Windows cambia, hay una nota. En los ejemplos FELCC está en
`/var/www/felcc` y DACI quedará en `/var/www/daci`; el servidor web corre como `www-data`. Ajustar a lo real.

> **La base de producción no se borra ni se reemplaza.** DACI sólo cambia la tabla de roles a las de Spatie (la
> antigua queda guardada como `roles_legado`) y crea las 4 tablas de Faltas y Contravenciones. Ensayado el 2026-10-27
> sobre una copia en el estado de producción: el resto de las tablas quedó **idéntico** y la vuelta atrás dejó la base
> **exactamente** como estaba (detalle al final).

---

## Resumen del día del cambio

| # | Paso | Tiempo aprox. |
|---|---|---|
| 1 | Poner FELCC en mantenimiento y detener su tarea programada | 2 min |
| 2 | Respaldo de la base de datos | depende del tamaño (la tabla `ciudadano` es grande) |
| 3 | Respaldo de la carpeta de FELCC (código + archivos) | depende del tamaño de los archivos |
| 4 | Instalar DACI: ZIP, `build/`, `composer install`, `.env` | 10–15 min |
| 5 | Copiar los archivos (storage) y crear el enlace simbólico | depende del tamaño |
| 6 | Migrar la base | 1 min |
| 7 | Optimizar, tarea programada, servidor web | 5 min |
| 8 | Pruebas | 15 min |

Todo lo de la **sección A** se hace **días antes**, con FELCC funcionando.

---

## A. Preparación (días antes, sin cortar el servicio)

### A.1 PHP 8.3 o superior

FELCC funciona con PHP 8.2, pero **DACI necesita PHP 8.3+** (Laravel 13). FELCC también funciona con 8.3, así que
se puede actualizar antes sin afectarlo.

```bash
php -v
```

Si dice 8.2 o menos, instalar 8.3 (Ubuntu):

```bash
sudo apt update
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php     # en Debian: repositorio packages.sury.org/php
sudo apt update
```

### A.2 Extensiones de PHP

```bash
sudo apt install -y php8.3-cli php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml php8.3-zip \
                    php8.3-gd php8.3-intl php8.3-bcmath php8.3-curl php8.3-imagick
```

(`php8.3-xml` trae `dom`, `xmlreader` y `libxml`; las demás que se necesitan — `ctype`, `fileinfo`, `filter`,
`hash`, `iconv`, `json`, `openssl`, `pcre`, `session`, `tokenizer`, `zlib` — vienen con PHP.)

Si el servidor web es **Apache con mod_php** en lugar de PHP-FPM:

```bash
sudo apt install -y libapache2-mod-php8.3
sudo a2dismod php8.2 && sudo a2enmod php8.3 && sudo systemctl restart apache2
```

Verificar que estén todas:

```bash
php -m | grep -Ei 'pdo_mysql|mbstring|dom|xmlreader|zip|gd|intl|bcmath|curl|imagick|fileinfo|openssl|tokenizer'
```

> **Windows (XAMPP / Laragon / IIS):** usar el PHP 8.3 de la herramienta y habilitar en `php.ini` las líneas
> `extension=pdo_mysql`, `mbstring`, `fileinfo`, `gd`, `intl`, `zip`, `curl`, `openssl`; `imagick` se instala aparte
> (DLL de imagick para la versión exacta de PHP + ImageMagick).

### A.3 Composer

```bash
composer --version      # Composer 2.x
```

Si no está:

```bash
cd ~
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
```

### A.4 Confirmar el estado de la base de producción

Desde la carpeta de **FELCC** (ya está conectada a la base):

```bash
cd /var/www/felcc
php artisan migrate:status
```

Deben figurar **36 migraciones «Ran»** y ninguna «Pending». Si alguna está «Pending» (en la copia local faltaban
`create_audits_table` y `remove_unique_from_ci_in_persona_table`), **avisar al equipo de desarrollo antes de seguir**.

### A.5 Espacio en disco

```bash
df -h                                                    # espacio libre
du -sh /var/www/felcc/storage/app/public                 # tamaño de los archivos (fotos, PDF)
mysql -u <usuario> -p -e "SELECT ROUND(SUM(data_length+index_length)/1024/1024) AS MB FROM information_schema.tables WHERE table_schema='<base>';"
```

Hace falta libre, como mínimo: el tamaño de la base (respaldo) + 2 veces el de los archivos (respaldo y copia para
DACI) + ~200 MB para DACI y sus dependencias.

### A.6 Material que entrega el equipo de desarrollo

1. `daci.zip` — el código, **sin** `vendor/`, `node_modules/` ni `.env`.
2. `build/` (o `build.zip`) — el frontend compilado, que va en `daci/public/build/`.
3. Esta guía.

---

## B. El día del cambio

> Hacerlo en una **ventana sin usuarios**. Desde el paso B.1 nadie debe usar FELCC hasta terminar.

### B.1 Poner FELCC en mantenimiento

```bash
cd /var/www/felcc
php artisan down
```

Los usuarios verán «en mantenimiento». Detener también su **tarea programada** (respaldo diario de FELCC), para que no
corra en medio de la migración:

```bash
sudo crontab -u www-data -e      # comentar (#) la línea de FELCC: "... cd /var/www/felcc && php artisan schedule:run ..."
```

> **Windows:** Programador de tareas → deshabilitar la tarea de FELCC.

### B.2 Respaldo de la base de datos

```bash
mkdir -p ~/respaldos
FECHA=$(date +%Y%m%d_%H%M)
mysqldump -u <usuario> -p --single-transaction --routines --triggers --events \
          --default-character-set=utf8mb4 <base> | gzip > ~/respaldos/felcc_base_$FECHA.sql.gz
```

Comprobar el respaldo **antes de seguir**:

```bash
ls -lh ~/respaldos/                                         # tamaño razonable (no 0 bytes)
gunzip -c ~/respaldos/felcc_base_$FECHA.sql.gz | tail -1    # debe decir "-- Dump completed on ..."
```

> Incluye la tabla `ciudadano` (≈10 millones de filas): tarda varios minutos y ocupa espacio. Es normal.
> **Windows:** el mismo comando sin `| gzip` (`> felcc_base.sql`), desde la carpeta `bin` de MySQL/MariaDB.

### B.3 Respaldo de la carpeta de FELCC (código y archivos)

```bash
cd /var/www
sudo tar -czf ~/respaldos/felcc_carpeta_$FECHA.tar.gz felcc
ls -lh ~/respaldos/
```

Anotar cuántos archivos tiene FELCC, para comparar después de copiarlos a DACI:

```bash
find /var/www/felcc/storage/app/public -type f | wc -l
```

### B.4 Instalar el código de DACI

```bash
sudo mkdir -p /var/www/daci
sudo unzip ~/daci.zip -d /var/www/daci        # que quede /var/www/daci/artisan (no /var/www/daci/daci/artisan)
ls /var/www/daci                              # debe verse: app  artisan  bootstrap  config  database  public  routes  storage ...
```

Copiar el frontend compilado:

```bash
sudo cp -r ~/build /var/www/daci/public/build
ls /var/www/daci/public/build                 # debe verse manifest.json y la carpeta assets/
sudo rm -f /var/www/daci/public/hot           # si existiera, la página buscaría el servidor de desarrollo
```

Instalar las dependencias de PHP (las descarga de internet):

```bash
cd /var/www/daci
composer install --no-dev --optimize-autoloader --no-interaction
```

Debe terminar sin errores. Si avisa de una extensión que falta («requires ext-…»), instalarla (A.2) y repetir.

### B.5 Configuración (`.env`)

**Se reutiliza el `.env` de FELCC**: DACI no usa ninguna variable que FELCC no tenga (comparado en los `config/`).

```bash
sudo cp /var/www/felcc/.env /var/www/daci/.env
nano /var/www/daci/.env
```

Revisar sólo estas líneas (el resto, incluidos `DB_*` y `APP_KEY`, queda igual); agregar las que falten:

```dotenv
APP_NAME="FELCC DACI"                        # opcional
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<dirección del sistema>      # exacta, la usan los enlaces de fotos y PDF
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
LOG_LEVEL=error

DB_CONNECTION=mysql                          # si dice sqlite, cambiar
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

**No** correr `php artisan key:generate`: se conserva la `APP_KEY` de FELCC. Sólo si estuviera vacía, generarla.

```bash
php artisan about          # resumen: entorno production, PHP 8.3, conexión a la base, etc.
```

> **No** poner `APP_DEBUG=true` en producción: mostraría datos internos ante un error.

### B.6 Copiar los archivos (storage) de FELCC a DACI

Fotos de personas, imágenes y PDF de mandamientos, actas, fotos de registro criminal y de vehículos:

```bash
sudo cp -a /var/www/felcc/storage/app/public/. /var/www/daci/storage/app/public/
```

(`-a` conserva fechas y permisos; el `/.` copia el **contenido** de la carpeta, también los ocultos.)

Comprobar que se copió todo — el número debe ser **igual** al anotado en B.3:

```bash
find /var/www/daci/storage/app/public -type f | wc -l
ls /var/www/daci/storage/app/public
# mandamientos  mandamientos-img-actas  mandamientos-pdf  mandamientos-pdf-actas  personas  registro-criminal  vehiculos
```

> **No** copiar `felcc/public/storage`: es un enlace, no una carpeta con archivos. Se crea de nuevo en el paso siguiente.

### B.7 Enlace simbólico `public/storage`

El navegador ve los archivos en `https://<sistema>/storage/...`; ese enlace apunta a `storage/app/public`:

```bash
cd /var/www/daci
php artisan storage:link
ls -l public/storage          # debe mostrar: public/storage -> /var/www/daci/storage/app/public
```

Si dice que ya existe y apunta a otro lado: `rm public/storage` y repetir.

- **Apache:** el sitio necesita `Options FollowSymLinks` (ver B.10).
- **Nginx:** sigue enlaces por defecto (no poner `disable_symlinks on`).
- **Windows:** abrir la consola **como administrador** y correr `php artisan storage:link`; si falla:
  `mklink /D C:\ruta\daci\public\storage C:\ruta\daci\storage\app\public`

### B.8 Permisos de carpetas

El servidor web debe poder escribir en `storage/` (archivos, logs, sesiones) y en `bootstrap/cache/`:

```bash
sudo chown -R www-data:www-data /var/www/daci
sudo find /var/www/daci -type d -exec chmod 755 {} \;
sudo find /var/www/daci -type f -exec chmod 644 {} \;
sudo chmod -R 775 /var/www/daci/storage /var/www/daci/bootstrap/cache
sudo chmod 600 /var/www/daci/.env
```

### B.9 Migrar la base de datos

```bash
cd /var/www/daci
php artisan migrate:status
```

Debe mostrar **36 «Ran»** (las de FELCC) y **2 «Pending»**:

```
2026_10_22_120000_migrar_roles_a_spatie_permission ........ Pending
2026_10_26_210000_crear_tablas_faltas_y_contravenciones .... Pending
```

**Si no es exactamente así, DETENERSE** y avisar. Si es así:

```bash
php artisan migrate --force
php artisan db:seed --class=RolesSeeder --force
```

- La primera pasa los roles a Spatie (la tabla antigua queda como `roles_legado`, con sus filas) y crea las tablas de
  Faltas y Contravenciones.
- La segunda crea los permisos nuevos, los roles `pol_110` y `conciliacion_ciudadana`, y le da todo al superadmin.
- Las dos migraciones se protegen solas: si una de sus tablas ya existe **se detienen sin cambiar nada**; si fallan a
  mitad, **deshacen lo que alcanzaron a hacer**.

Verificar:

```bash
php artisan migrate:status | grep -c Ran          # 38
php artisan tinker --execute 'echo DB::table("model_has_roles")->count()." usuarios con rol / ".DB::table("users")->whereNotNull("role_id")->count()." con role_id\n";'
```

Los dos números deben ser **iguales** (cada usuario conservó su rol). También desde MySQL:

```sql
SELECT COUNT(*) FROM roles_legado;                 -- los roles de FELCC (6)
SELECT name FROM roles ORDER BY id;                -- los 6 de siempre + pol_110 y conciliacion_ciudadana
SHOW TABLES LIKE '%falta_contravencion%';          -- 4 tablas nuevas
```

> **Nunca** correr `migrate:fresh`, `migrate:refresh`, `db:wipe`, `db:seed` sin `--class`, ni los seeders
> `InstalacionNuevaSeeder`, `inicialesSeeder` o `PlantillaFichaSeeder`: borrarían o duplicarían datos
> (tipos de mandamiento, delitos, juzgados, plantillas). El **único** seeder para producción es `RolesSeeder`.

### B.10 Servidor web: apuntar a DACI

La raíz del sitio pasa de `felcc/public` a **`daci/public`**.

**Apache** (`/etc/apache2/sites-available/<sitio>.conf`):

```apache
DocumentRoot /var/www/daci/public
<Directory /var/www/daci/public>
    Options FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

```bash
sudo a2enmod rewrite
sudo apachectl configtest && sudo systemctl reload apache2
```

**Nginx** (`/etc/nginx/sites-available/<sitio>`):

```nginx
root /var/www/daci/public;
index index.php;
client_max_body_size 20M;          # fotos y PDF

location / {
    try_files $uri $uri/ /index.php?$query_string;
}
location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
}
```

```bash
sudo nginx -t && sudo systemctl reload nginx
```

Subida de archivos (fotos hasta 10 MB): en `/etc/php/8.3/fpm/php.ini` (o el de Apache) `upload_max_filesize = 20M` y
`post_max_size = 25M`; reiniciar PHP-FPM (`sudo systemctl restart php8.3-fpm`).

### B.11 Optimizar y tarea programada

```bash
cd /var/www/daci
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Tarea programada de DACI (respaldo diario de la base a las 00:00, igual que FELCC; necesita `mysqldump`):

```bash
sudo crontab -u www-data -e
```

```
* * * * * cd /var/www/daci && php artisan schedule:run >> /dev/null 2>&1
```

(La línea de FELCC queda comentada.) Los respaldos diarios quedan en `daci/storage/backups/`.

> **Windows:** Programador de tareas → tarea cada 1 minuto que ejecute `php C:\ruta\daci\artisan schedule:run`.

### B.12 Pruebas

Entrar con un **superadmin** y revisar:

- [ ] Dashboard con los totales.
- [ ] Seguridad › Roles y Permisos: los 8 roles con sus permisos.
- [ ] Personas: buscar una persona y ver sus **fotos** (comprueba el storage y el enlace).
- [ ] Mandamientos: abrir uno y su **PDF** o imagen.
- [ ] Registro Criminal: ver un registro con fotos e **imprimir su ficha** (PDF).
- [ ] Faltas y Contravenciones: abrir el listado (vacío al inicio).
- [ ] Consultas › Consultar Mandamientos: hacer una búsqueda; luego verla en Seguridad › Auditoría de Consultas.
- [ ] Registrar un dato de prueba (p. ej. un teléfono) y eliminarlo.
- [ ] Entrar con un usuario de cada rol y comprobar que ve sólo lo suyo.

Si todo está bien, el cambio terminó. **FELCC queda apagado**:

> **FELCC no debe volver a encenderse sobre esta base**: después de la migración, la tabla `roles` es la de Spatie y
> FELCC ya no encuentra sus roles. Su carpeta queda sólo como respaldo (se puede quitar del servidor web).

---

## C. Si algo sale mal (vuelta atrás)

**Antes de B.9 (sin migrar):** no se cambió nada en la base. Volver el servidor web a `felcc/public`, rehabilitar su
tarea programada y `php artisan up` en FELCC.

**Después de B.9:** revertir las 2 migraciones de DACI (ensayado: deja la base **exactamente** como estaba):

```bash
cd /var/www/daci
php artisan down
php artisan migrate:rollback --step=2 --force
```

Luego volver el servidor web a `felcc/public`, rehabilitar la tarea programada de FELCC y:

```bash
cd /var/www/felcc
php artisan up
```

- Se pierde **sólo lo nuevo de DACI**: los registros de Faltas y Contravenciones y los cambios de roles y permisos. Si ya
  se cargaron faltas o contravenciones, respaldar antes sus 4 tablas:
  `mysqldump -u <usuario> -p <base> falta_contravencion foto_falta_contravencion ficha_falta_contravencion ficha_falta_contravencion_plantilla > faltas.sql`
- Lo cargado en DACI en los módulos de siempre (personas, mandamientos, registros, teléfonos…) **se conserva**: son las
  mismas tablas de FELCC.
- **Último recurso:** restaurar el respaldo de B.2 (se pierde todo lo cargado después):
  `gunzip -c ~/respaldos/felcc_base_<FECHA>.sql.gz | mysql -u <usuario> -p <base>`

---

## D. Después del cambio

- **Roles nuevos:** asignar `pol_110` y `conciliacion_ciudadana` a quien corresponda (Seguridad › Usuarios).
  Faltas y Contravenciones sólo lo ven los administradores y `conciliacion_ciudadana`.
- **Contraseñas:** ya no hay «¿olvidó su contraseña?»; el administrador la restablece en Seguridad › Usuarios › Editar.
- **Plantilla de la ficha:** revisar los textos en «Plantilla de la Ficha» (la de Faltas y Contravenciones se copió de
  la de Registro Criminal).
- **Logs de DACI:** `daci/storage/logs/laravel.log`.
- **Actualizaciones futuras:** reemplazar el código (sin tocar `.env`, `storage/` ni `public/storage`), copiar el `build/`
  nuevo, y luego `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`,
  `php artisan db:seed --class=RolesSeeder --force` y `php artisan optimize` (o los tres `*:cache`).

---

## Anexo 1 · Qué cambia en la base (ensayo del 2026-10-27)

Ensayo sobre una copia de la base en el estado de producción (36 migraciones de FELCC, nada de DACI): suma de
verificación de **todas** las tablas antes y después de `migrate --force` + `db:seed --class=RolesSeeder --force`.

| Resultado | Tablas |
|---|---|
| **Sin ningún cambio** (idénticas byte a byte) | `persona`, `mandamiento`, `registro_criminal`, `users`, `telefono`, `imei`, `vehiculo`, `multimedia`, `fotos_registro`, `ficha_registro`, `auditar_consultas`, `audits` y todas las demás |
| Cambia (esperado) | `migrations` (registra las 2 de DACI) · `roles` (pasa a ser la de Spatie) |
| Nuevas | `roles_legado` (la `roles` original, filas idénticas) · `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` · `falta_contravencion`, `foto_falta_contravencion`, `ficha_falta_contravencion`, `ficha_falta_contravencion_plantilla` |
| Desaparecen | **ninguna** |

Los usuarios conservaron su rol (15 de 15) y `users.role_id` no cambió. `migrate:rollback --step=2` dejó la base
idéntica al estado original. La tabla `ciudadano` DACI no la usa ni la toca.

## Anexo 2 · Qué escribe DACI en la base durante el uso normal

Nada de esto cambia el esquema; son datos que genera el propio uso:

| Tabla | Qué agrega o cambia |
|---|---|
| `auditar_consultas` | Una fila por búsqueda, detalle abierto y exportación en todos los módulos (antes sólo en las consultas). |
| `audits` | Cambios de registros, roles y plantillas (como FELCC, en más modelos). |
| `users.role_id` | Se mantiene en espejo del rol de Spatie al asignar un rol (permite volver a FELCC). |
| `persona.estado_civil`, `persona.ocupacion` | Se pueden actualizar al editar un registro criminal o una falta. |
| `ficha_registro_plantilla` | Sólo al guardar en «Plantilla de la Ficha» (la fila activa; la crea si no hay). |
| `cache`, `sessions` | Caché de permisos y sesiones (tablas de Laravel que FELCC ya tenía). |
| `storage/app/public/faltas-contravenciones/` | Fotos del módulo nuevo. |
