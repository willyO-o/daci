# Guía de despliegue — DACI reemplaza a FELCC

Pasos para poner DACI en producción sobre la **misma base de datos** que usa hoy FELCC, en un servidor de **red
privada sin git ni internet**: el sistema se lleva en un **ZIP** y se instala a mano. Revisada y ensayada el
2026-10-27 (ver «Qué cambia en la base»).

> **La base de producción no se borra ni se reemplaza.** DACI sólo agrega: cambia la tabla de roles a las de Spatie
> (la antigua queda como `roles_legado`) y crea las 4 tablas de Faltas y Contravenciones. Todo lo demás queda igual.

---

## Qué cambia en la base (ensayo del 2026-10-27)

Se ensayó sobre una copia de la base en el **mismo estado que producción** (las 36 migraciones de FELCC aplicadas,
nada de DACI): se tomó la suma de verificación de **todas** las tablas, se corrieron exactamente los comandos de esta
guía y se comparó.

| Resultado | Tablas |
|---|---|
| **Sin ningún cambio** (idénticas byte a byte) | `persona`, `mandamiento`, `registro_criminal`, `users`, `telefono`, `imei`, `vehiculo`, `multimedia`, `fotos_registro`, `ficha_registro`, `auditar_consultas`, `audits` y todas las demás |
| Cambia (esperado) | `migrations` (registra las 2 migraciones de DACI) · `roles` (pasa a ser la de Spatie) |
| Nuevas | `roles_legado` (la tabla `roles` original, **filas idénticas**) · `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` · `falta_contravencion`, `foto_falta_contravencion`, `ficha_falta_contravencion`, `ficha_falta_contravencion_plantilla` |
| Desaparecen | **ninguna** |

- Los usuarios conservan su rol (15 de 15 en el ensayo) y `users.role_id` no cambia.
- **Volver atrás** (`migrate:rollback --step=2`) dejó la base **exactamente** como estaba (ninguna diferencia en
  ninguna tabla).
- La tabla `ciudadano` (≈10 millones de filas) DACI no la usa ni la toca.

---

## 0. Antes del día del cambio

### 0.1 Requisitos del servidor (verificar con anticipación)

| Requisito | Detalle |
|---|---|
| **PHP 8.3 o superior** | ⚠ FELCC funciona con PHP 8.2, **DACI necesita 8.3+** (Laravel 13). Revisar con `php -v`. Si el servidor tiene 8.2, hay que instalar 8.3 **antes** (sin internet: llevar el instalador/paquetes). |
| Extensiones PHP | `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `zip`, `intl`, `gd` y **`imagick`** (fotos de registro criminal y faltas a WebP). Revisar con `php -m`. |
| MariaDB / MySQL | La misma de FELCC. `mysqldump` en el PATH (respaldo diario `db:backup`). |
| Servidor web | Apache o Nginx con la raíz del sitio en `daci/public`. |
| Composer, Node, git | **No hacen falta** en el servidor: el ZIP lleva todo compilado. |

### 0.2 Confirmar el estado de la base de producción

En el servidor, **desde la carpeta de FELCC** (que ya está conectada a la base):

```bash
php artisan migrate:status
```

Deben figurar las **36 migraciones de FELCC como «Ran»**. Si alguna figura «Pending» (en la copia local faltaban
`create_audits_table` y `remove_unique_from_ci_in_persona_table`), **avisar antes de seguir**: DACI necesita la tabla
`audits`, y esas migraciones correrían junto con las de DACI.

### 0.3 Preparar el ZIP (en la máquina de desarrollo, con internet)

Desde una copia limpia del proyecto `daci` (sin tocar la carpeta de trabajo):

```bash
composer install --no-dev --optimize-autoloader   # vendor/ sin herramientas de desarrollo
npm ci && npm run build                           # frontend compilado en public/build/
```

El ZIP **lleva**: `app/`, `bootstrap/` (sin `bootstrap/cache/*.php`), `config/`, `database/`, `lang/`, `public/`
(con `public/build/`), `resources/`, `routes/`, `storage/` (sólo la estructura de carpetas), `vendor/`, `artisan`,
`composer.json`, `composer.lock`, `package.json`, `.env.example`.

El ZIP **no lleva**: `.env` (credenciales locales), `.git/`, `node_modules/`, `tests/`, `storage/logs/*`,
`storage/framework/{cache,sessions,views}/*`, `storage/app/public/*` (los archivos vienen de FELCC),
`public/storage` (el enlace se crea en el servidor), `public/storage2/`, `.claude/`, `.ai/`, `docs/`.

---

## 1. El día del cambio (ventana sin usuarios conectados)

### 1.1 Detener FELCC y respaldar

```bash
cd /ruta/felcc
php artisan down                      # FELCC en mantenimiento: nadie escribe en la base
php artisan db:backup                 # o mysqldump completo de la base (incluida ciudadano)
```

Copiar también **toda la carpeta de FELCC** (código y `storage/`) a un lugar seguro. Comprobar que el respaldo de la
base existe y tiene tamaño razonable **antes** de seguir.

### 1.2 Instalar DACI

1. Descomprimir el ZIP en una **carpeta nueva** (p. ej. `/ruta/daci`). **No** sobre la de FELCC.
2. Crear el `.env` a partir de `.env.example` y completar:

   ```dotenv
   APP_NAME="DACI"
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://<dirección del sistema>
   APP_TIMEZONE=America/La_Paz

   DB_CONNECTION=mysql
   DB_HOST=<el mismo de FELCC>
   DB_PORT=3306
   DB_DATABASE=<la misma base de FELCC>
   DB_USERNAME=<el mismo de FELCC>
   DB_PASSWORD=<el mismo de FELCC>

   SESSION_DRIVER=database
   CACHE_STORE=database
   QUEUE_CONNECTION=database
   ```

   Los datos de conexión son **los mismos del `.env` de FELCC**.
3. Generar la clave de la aplicación (FELCC no guarda datos cifrados en la base; sólo se cierran las sesiones):

   ```bash
   cd /ruta/daci
   php artisan key:generate
   ```

4. Permisos de escritura para el usuario del servidor web en `storage/` y `bootstrap/cache/`.

### 1.3 Archivos (fotos, PDF, actas)

```bash
cp -r /ruta/felcc/storage/app/public/. /ruta/daci/storage/app/public/
php artisan storage:link
```

Las rutas guardadas en la base (`multimedia.ruta`, `fotos_registro.ruta_archivo`) son relativas a esa carpeta y no
cambian. En Windows, `storage:link` necesita una consola como administrador.

### 1.4 Base de datos

```bash
php artisan migrate:status    # 36 «Ran» + 2 «Pending» (las de DACI). Si no es así, DETENERSE.
php artisan migrate --force   # roles → Spatie (la antigua queda como roles_legado) y tablas de Faltas y Contravenciones
php artisan db:seed --class=RolesSeeder --force   # permisos nuevos, roles base, pol_110 y conciliacion_ciudadana
```

Ambas migraciones se protegen solas: si encuentran alguna de sus tablas ya creada **se detienen sin cambiar nada**, y si
fallan a mitad **deshacen lo que alcanzaron a hacer**.

**No** correr nunca `migrate:fresh`, `migrate:refresh`, `db:wipe`, `db:seed` sin `--class`, ni
`--class=InstalacionNuevaSeeder`.

Verificación rápida:

```bash
php artisan tinker --execute 'echo DB::table("model_has_roles")->count()." usuarios con rol / ".DB::table("users")->whereNotNull("role_id")->count()." con role_id";'
```

Los dos números deben coincidir.

### 1.5 Optimización y tareas programadas

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Reemplazar la tarea programada de FELCC por la de DACI (respaldo diario a las 00:00):

```
* * * * * cd /ruta/daci && php artisan schedule:run >> /dev/null 2>&1
```

### 1.6 Publicar

1. Cambiar la raíz del sitio en el servidor web de `felcc/public` a **`daci/public`** y recargar el servidor web.
2. Entrar como **superadmin** y revisar: Dashboard, Seguridad › Roles y Permisos, una consulta de mandamientos, un
   detalle de persona con fotos, un PDF de ficha, Auditoría de consultas.
3. Probar con un usuario de cada rol que vea sólo lo suyo.

> **FELCC no debe volver a encenderse sobre esta base**: después de la migración la tabla `roles` es la de Spatie y FELCC
> ya no encuentra los roles. Dejar su carpeta sin acceso (sólo como respaldo).

---

## 2. Si algo sale mal (vuelta atrás)

Ensayado: deja la base **exactamente** como estaba.

```bash
cd /ruta/daci
php artisan down
php artisan migrate:rollback --step=2 --force   # quita Faltas y Contravenciones y devuelve la tabla roles original
```

Luego volver la raíz del sitio a `felcc/public`, restaurar su tarea programada y `php artisan up` en FELCC.

- Se pierde **sólo** lo que se haya hecho en DACI con lo nuevo: los registros de Faltas y Contravenciones y los cambios
  de roles y permisos. Si ya hay registros de Faltas y Contravenciones, **respaldar sus 4 tablas antes** de revertir.
- Lo cargado en DACI en los módulos de siempre (personas, mandamientos, registros…) **se conserva**: son las mismas
  tablas que usa FELCC.
- Último recurso: restaurar el respaldo completo del paso 1.1 (se pierde todo lo cargado después).

---

## 3. Después del cambio

- **Usuarios y roles:** asignar los roles nuevos `pol_110` y `conciliacion_ciudadana` a quien corresponda (Seguridad ›
  Usuarios). Faltas y Contravenciones sólo lo ven administradores y `conciliacion_ciudadana`.
- **Contraseñas:** ya no hay «olvidé mi contraseña»; el administrador la restablece en Seguridad › Usuarios › Editar.
- **Plantilla de la ficha:** revisar el texto en «Plantilla de la Ficha» (Registro Criminal y Faltas y
  Contravenciones). La de Faltas y Contravenciones se copió de la de Registro Criminal: ajustar su redacción.
- **Auditoría:** desde DACI se registran búsquedas, detalles abiertos y exportaciones de todos los módulos.

## Qué escribe DACI en la base durante el uso normal

Nada de esto cambia el esquema; son datos que el propio uso genera:

| Tabla | Qué agrega o cambia |
|---|---|
| `auditar_consultas` | Una fila por búsqueda, detalle abierto y exportación (antes sólo en las consultas). |
| `audits` | Cambios de registros, roles y plantillas (como FELCC, en más modelos). |
| `users.role_id` | Se mantiene en espejo del rol de Spatie al asignar un rol (para poder volver a FELCC). |
| `persona.estado_civil`, `persona.ocupacion` | Se pueden actualizar al editar un registro criminal o una falta. |
| `ficha_registro_plantilla` | Sólo al guardar en «Plantilla de la Ficha» (la fila activa; la crea si no hay). |
| `cache`, `sessions` | Caché de permisos y sesiones (tablas de Laravel que FELCC ya tenía). |
| `storage/app/public/faltas-contravenciones/` | Fotos del módulo nuevo. |
