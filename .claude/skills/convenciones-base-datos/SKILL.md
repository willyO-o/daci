---
name: convenciones-base-datos
description: Convenciones OBLIGATORIAS de base de datos, persistencia y consultas del sistema FELCC/DACI (Bolivia) — la base `felcc1` es la del sistema anterior EN PRODUCCIÓN y su esquema NO se toca (salvo roles y permisos); nombres de tablas y columnas en español y singular, claves foráneas con los dos estilos heredados (`id_tabla` y `tabla_id`), valores de estado en MAYÚSCULA, tablas exentas por ser de Laravel o de spatie/laravel-permission, tablas que se ignoran (ciudadano y relacionadas), modelos en `App\Models` con el mismo nombre de clase (auditoría), y la regla de que filtrar/ordenar/paginar se hace EN la base de datos. Usa esta skill SIEMPRE antes de crear o modificar una migración, un modelo, una factory, un seeder o un cast, antes de nombrar cualquier columna o relación, y antes de escribir cualquier consulta o listado en un controlador. Aplica a `database/**`, `app/Models/**`, `app/Support/**` y `app/Http/Controllers/**`.
---

# Convenciones de base de datos — FELCC / DACI

> Este proyecto (`daci`) **reemplaza** al sistema anterior (`felcc`, Laravel + jQuery) y trabaja sobre **la misma
> base de producción** (MariaDB). La regla número uno no es de estilo: **el esquema existente no se modifica y no se
> pierde ningún dato**. Lo que sigue describe cómo es la base para escribir código que la respete, y cómo nombrar lo
> poco nuevo.
>
> **La base de producción NO es la local.** Es otra, con mucha más información (más personas, usuarios, quizá roles
> o datos que la copia local no tiene). Las bases locales (`felcc1`, `felcc` en Laragon) son copias parciales: todo
> cambio debe ser seguro sobre datos desconocidos y su estado de migraciones se verifica en producción con
> `php artisan migrate:status` antes de desplegar. Lo único que se puede modificar son roles, permisos y sus controles.

---

## 0. Reglas que no se discuten

1. **No se crean, renombran ni borran tablas o columnas existentes, ni se agregan índices.** La única migración que
   tocó el esquema es `2026_10_22_120000_migrar_roles_a_spatie_permission` (roles y permisos, autorizada por el
   usuario). Si una funcionalidad parece pedir una columna nueva, **se pregunta antes**.
   Tablas NUEVAS autorizadas (sólo se crearon, no alteran nada existente): `2026_10_26_210000_crear_tablas_faltas_y_contravenciones`
   — `falta_contravencion`, `foto_falta_contravencion`, `ficha_falta_contravencion`, `ficha_falta_contravencion_plantilla`. Copian columnas y
   nombres de `registro_criminal` y sus tablas (por eso usan `id_persona`, `id_usuario`, `id_division`), excepto el
   tipo de la foto, que es `string` y no `enum`. Toda migración que cree tablas: se detiene sin cambios si alguna ya
   existe, deshace lo creado si falla y su `down()` borra sólo lo suyo.
2. **Las 36 migraciones del sistema anterior** están copiadas en `database/migrations` sólo como registro. **No se
   editan.** Una base puede no tenerlas todas ejecutadas (la copia local `felcc` tenía 34: le faltaban
   `remove_unique_from_ci_in_persona_table` y `create_audits_table`); `migrate` las correría junto con la de roles.
3. **Nunca** `migrate:fresh`, `migrate:refresh`, `db:wipe` ni `db:seed` sin `--class` contra `felcc1`.
   `DatabaseSeeder` sólo corre `PermisosSeeder` (idempotente, sólo agrega permisos/roles base; seguro en producción). Los catálogos iniciales de una base vacía están en
   `InstalacionNuevaSeeder` y **no** se corren en producción.
4. Los tests usan la base **`felcc_pruebas`** (MariaDB, porque el código heredado usa `CONCAT`, `JSON_OBJECT`, `MD5()`…);
   `tests/TestCase.php` aborta si la base configurada es otra. Jamás apuntan a `felcc1`.
5. Antes de un cambio que escriba en masa, respaldo con `php artisan db:backup` (comando heredado).

## 1. Tablas que se ignoran

`ciudadano` (≈10,6 millones de filas) y sus relacionadas — `libro`, `notario`, `localidad`, `departamento`,
`documento` — pertenecen a otra rama del sistema anterior. **Se tratan como si no existieran**: no se consultan, no
se unen, no se exportan, no se muestran. Si un código heredado las mencionaba (p. ej. la relación `documento` de
`Persona::migrateRelationsTo`), se deja comentado como estaba.

## 2. Forma de los nombres (heredada y para lo nuevo)

| Elemento | Regla | Ejemplos reales |
|---|---|---|
| Tabla | español, **singular**, snake_case | `persona`, `mandamiento`, `tipo_mandamiento`, `registro_criminal`, `vehiculo_caso`, `imei_telefono` |
| Modelo | español, singular, PascalCase, **en `App\Models`** y con `$table` fijado | `Persona` → `persona`, `TipoMandamiento` → `tipo_mandamiento` |
| Columna | español, singular, minúscula, snake_case | `nombre_juzgado`, `fecha_ejecucion`, `numero_celular`, `estado_investigacion` |
| Clave foránea | **dos estilos heredados**: `id_{tabla}` en el núcleo antiguo (`id_persona`, `id_juzgado`, `id_delito`, `id_tipo_mandamiento`, `id_division`, `id_usuario`, `id_mandamiento`, `id_vehiculo`, `id_registro_criminal`, `id_pais`) y `{tabla}_id` en lo más nuevo (`persona_id`, `vehiculo_id`, `telefono_id`, `imei_id`, `registro_criminal_id`, `estacion_servicio_id`, `user_id`). **Se usa la que ya tiene la tabla**; una tabla NUEVA usa `{tabla}_id`. |
| Tabla pivote | los dos singulares en orden alfabético | `imei_telefono` (la de `vehiculo_caso` es pivote con datos propios: `tipo`, `numero_informacion`) |
| Booleano | adjetivo directo | `is_active` en `users` (nativo heredado; no se renombra) |

**Nombres nativos que no se traducen**: `id`, `created_at`, `updated_at`, `deleted_at`, `remember_token`,
`email_verified_at`, y las tablas `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`,
`job_batches`, `failed_jobs`, `migrations`, `audits` (owen-it/laravel-auditing) y las de spatie/laravel-permission
(`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`; `roles` tiene además
`descripcion`).

`roles_legado` es la tabla de roles del sistema anterior renombrada (respaldo); `users.role_id` la sigue apuntando.
El rol vigente vive en Spatie y `role_id` se mantiene **en espejo**: se asigna siempre con `User::asignarRol()`
(nunca `syncRoles`/`assignRole` sueltos en código de la aplicación), que deja el id del mismo rol en `roles_legado`
o null si es un rol creado en DACI. `acceso:sincronizar` repara desfases y no toca a usuarios sin rol en Spatie.

## 3. Valores literales

- **Estados y catálogos en MAYÚSCULA**: `estado_juzgado`/`estado_delito`/`estado_tipo_mandamiento` = `ACTIVO`/`INACTIVO`
  (enum MySQL heredado); `persona.genero` = `MASCULINO`/`FEMENINO`; `persona.estado_civil` = `SOLTERO`, `CASADO`,
  `DIVORCIADO`, `VIUDO`, `CONYUGUE`; `fotos_registro.tipo` = `FRONTAL`/`LATERAL`; `vehiculo_caso.tipo` = `RUAT`,
  `BSISA`, `SOAT`, …; `ficha_registro_plantilla.estado` = `ACTIVO`.
- `mandamiento.estado` es texto libre heredado (los valores existentes se listan con `select distinct`).
- `multimedia.tipo` en minúscula snake_case: `persona`, `mandamiento`, `acta_ejecucion`, `foto_vehiculo`.
- Los textos que se guardan desde importaciones pasan por los helpers `campoDB()` (espacios + MAYÚSCULA),
  `limpiarCI()`, `campoDBNumero()`, `campoDBFechaHora()` de `app/Helpers/TextoHelper.php`.
- Columnas nuevas: `string` con validación en la app, nunca `enum()` de MySQL.

## 4. Modelos

- Se mantienen en **`App\Models` con el mismo nombre de clase** que en el sistema anterior: `audits.auditable_type`
  guarda el nombre completo (`App\Models\Persona`) y la Auditoría de cambios depende de ello. No se mueven a otro
  namespace ni se usa `morphMap`.
- Todos los modelos de negocio implementan `OwenIt\Auditing\Contracts\Auditable` (como antes).
- Los que tienen `deleted_at` usan `SoftDeletes` (`persona`, `mandamiento`, `registro_criminal`, `telefono`, `imei`,
  `vehiculo`, `ficha_registro`, `auditar_consultas`, `users`).
- Los `boot()` heredados asignan el autor (`id_usuario`, `user_id`) y numeran (`nro_registro`, `numero_ficha`): no
  se duplica esa lógica en los controladores.
- Dinero (`cargio.monto`) y cantidades (`cargio.cantidad`) son `decimal(10,2)` heredados.

## 5. Consultas — el trabajo lo hace la base

- Listados **siempre paginados en el servidor** (`paginate()->withQueryString()`), con tamaño acotado (máx. 100).
- Filtros con **lista blanca** de valores (`match` sobre el filtro recibido); nada arbitrario del request a SQL.
- `with()` explícito para cada relación que se muestra (N+1 = 0); `select()` de lo necesario en búsquedas.
- Los buscadores para selects (`personas.search`, `imeis.telefonos-search`) llevan `limit()`.
- Exportaciones masivas con `cursor()`/`lazy()` y respuesta en streaming.
- **No se pueden agregar índices** (regla 0.1): las búsquedas `LIKE '%…%'` heredadas se conservan porque el usuario
  depende de ellas, pero no se suman nuevas sobre tablas grandes sin medir con `EXPLAIN`.
- SQL crudo permitido sólo **parametrizado** (`whereRaw('… ?', [$valor])`).

## 6. Archivos

Los archivos viven en el disco `public` (`storage/app/public`) con las **mismas carpetas** del sistema anterior —
`personas/`, `mandamientos/`, `mandamientos-pdf/`, `mandamientos-img-actas/`, `mandamientos-pdf-actas/`,
`registro-criminal/`, `vehiculos/` — porque en el despliegue se copia la carpeta del sistema anterior tal cual. Las
rutas guardadas en `multimedia.ruta` y `fotos_registro.ruta_archivo` son relativas a ese disco y se muestran con
`/storage/{ruta}`. No se cambia el nombre ni la carpeta de un archivo existente.
