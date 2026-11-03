# Propuesta · Casos con `id_caso` (D-037)

> **Estado:** fases 1, 2 y 4 programadas y ensayadas; fases 1 y 2 aplicadas en la base local `felcc1`. Fase 3: módulo
> Casos hecho (2026-11-02); faltan los formularios por `id_caso`.
> No aplicado en ninguna base real. Fecha: 2026-11-01.

## 1. Qué se quiere

Hoy el caso o CUD se escribe como **texto libre** en 5 columnas. Se crea una tabla `caso`, cada una de esas tablas
recibe una columna **`id_caso`** que apunta a ella, se registran los casos existentes tabla por tabla y, cuando todo
esté verificado en producción, se eliminan las columnas de texto.

| Tabla | Columna de texto (hoy) | Nueva | Copia local `felcc1` |
|---|---|---|---|
| `telefono` | `caso` | `id_caso` | 1.555 filas: 1.336 con caso (21 casos), 219 sin caso |
| `registro_criminal` | `cud` | `id_caso` | 25 filas: 4 con CUD (3 casos), 21 sin CUD |
| `falta_contravencion` | `cud` | `id_caso` | vacía |
| `vehiculo` | `caso_relacionado` | `id_caso` | vacía |
| `vehiculo_caso` | `numero_informacion` (en pantalla: «Caso») | `id_caso` | vacía |

**No se incluyen:** `ficha_registro.caso_cud` y `ficha_falta_contravencion.caso_cud` (texto de una **ficha ya impresa**:
es copia de un documento emitido y no debe reescribirse), `telefono.persona_caso` (nombre de la persona),
`telefono.informacion` (operadora), `mandamiento.hoja_ruta` (número de trámite).

## 2. Las 4 fases

| Fase | Qué hace | Cómo se corre | ¿Pierde algo? | Vuelta atrás |
|---|---|---|---|---|
| **1** | Crea `caso` y agrega `id_caso` (nula, con clave foránea) a las 5 tablas. | `php artisan migrate --force` | No. No toca ningún dato ni columna existente. | `migrate:rollback --step=1` |
| **2** | Registra cada caso y pone su id en `id_caso`, **tabla por tabla**. | `php artisan casos:migrar` (o `db:seed --class=CasosSeeder`) | No. Sólo escribe `id_caso`. | Rollback de la fase 1 (las columnas de texto siguen intactas). |
| **3** | Pantallas: el formulario guarda el caso por `id_caso` (crea el caso si no existe) y sigue copiando el texto en la columna vieja. Módulo Casos. | Despliegue de código | No. | Volver al código anterior. |
| **4** | Elimina las 5 columnas de texto, **después de respaldar cada valor**. | `php artisan migrate --path=database/migrations/casos-fase-4 --force` | No: cada valor queda en `caso_texto_respaldo`. | `migrate:rollback --path=… ` repone columnas y valores. |

Entre la fase 3 y la 4 se deja un **período de observación** (p. ej. 2 semanas) con el sistema en uso.

## 3. Reglas de la fase 2 (seeder)

1. **Por fases:** `telefono` → `registro_criminal` → `falta_contravencion` → `vehiculo` → `vehiculo_caso`. Cada tabla en
   su propia transacción; si una falla se deshace sólo esa y se vuelve a correr. `--tabla=telefono` corre una sola.
2. **El caso ya existe → se toma su id; si no → se crea.** El mismo texto es el mismo caso en todas las tablas (el CUD
   de un registro criminal y de una falta quedan con el mismo id).
3. **Nulo, vacío o sin caso → `id_caso` nulo.** Se considera «sin caso»:
   - nulo, vacío, sólo espacios (también no separables y tabuladores);
   - sólo guiones o signos: `-`, `--`, `—`, `...`, `_`;
   - relleno: `S/N`, `N/A`, `S/C`, `SIN CASO`, `SIN CUD`, `NINGUNO`, `NO`, `0`, `000`, `XXX`, `CASO` o `CUD` solos;
   - errores de Excel: `#¿NOMBRE?`, `#N/A`, `#REF!`.

   El relleno se **lista** en el informe (texto y cantidad). El texto sigue en su columna hasta la fase 4 y luego en el respaldo.
4. **Limpieza del código del caso:** espacios unificados, sin guiones o signos sueltos en los bordes, MAYÚSCULA
   (`"  caso   keyton "` → `CASO KEYTON`). Los guiones de dentro se conservan (`2011-0201-26`). La base no distingue
   mayúsculas ni tildes: `TÍO` y `TIO` son el mismo caso.
5. **Sólo escribe `id_caso` y sólo donde está nulo.** Nunca pisa uno puesto por un usuario o por una corrida anterior;
   no cambia `updated_at`; incluye las filas eliminadas (soft delete).
6. **Verificación antes de confirmar cada tabla:** huella (MD5) de todas las demás columnas de todas las filas
   igual que antes; cada fila con caso tiene el id del caso de su texto; ninguna fila con caso quedó con `id_caso`
   nulo. Si algo no cuadra → se deshace esa tabla.
7. `--simular` hace todo y lo deshace: muestra el informe sin guardar nada.

## 4. Permisos

`casos.listar`, `casos.ver`, `casos.crear`, `casos.editar`, `casos.eliminar` (módulo Casos), asignados al crearse
**sólo al administrador** (el superadmin tiene todo). Registrar el CUD/caso en un formulario **no pide permiso de
casos**: lo hace quien registra en ese módulo. Los crea el `PermisosSeeder` de siempre (`RolesSeeder` lo corre): sólo
agrega los nuevos, nunca borra ni quita, y respeta lo cambiado en la pantalla de roles.

## 5. Qué puede salir mal en producción y cómo está cubierto

| # | Riesgo | Cobertura |
|---|---|---|
| 1 | `migrate` corre **todas** las migraciones pendientes, no sólo ésta. | Antes: `migrate:status`; debe estar pendiente **sólo** `2026_11_01_100000_crear_tabla_caso_y_agregar_id_caso`. Si hay otra, no seguir. |
| 2 | La base de producción es distinta (tabla `caso` o columna `id_caso` ya existentes, tablas faltantes, motor no InnoDB). | La fase 1 lo comprueba antes (sólo lecturas) y se detiene **sin cambios** diciendo qué. `migrate --pretend` muestra el SQL exacto antes. |
| 3 | Falla a mitad de la fase 1. | Deshace lo que alcanzó a hacer (quita `id_caso` y `caso`). |
| 4 | `ALTER TABLE` bloquea las tablas mientras corre. | Con el sistema en mantenimiento (`php artisan down`). Copia local: 1,1 s. La columna nula al final no reescribe datos. |
| 5 | Usuarios escribiendo durante la fase 2. | Mantenimiento. Si una fila cambia en plena corrida, la tabla se deshace sola y pide repetir. |
| 6 | Corte de luz o de conexión en la fase 2. | Una transacción por tabla: lo confirmado queda bien, lo demás no se aplicó. Volver a correr continúa. |
| 7 | Datos sucios (nulos, espacios, guiones, relleno, UTF-8 inválido). | `id_caso` nulo, informados en el reporte, el texto no se toca. |
| 8 | El mismo caso escrito casi igual («EAL MILLON» / «EAL 1 MILLON»). | Quedan como casos distintos; se unen en la pantalla de casos (fase 3). |
| 9 | Volver a correr el seeder. | Idempotente: no cambia nada ya hecho; recoge filas nuevas. |
| 10 | Borrados físicos heredados (eliminar persona, vehículo, registro). | La clave va de la fila al caso: borrar la fila no se ve afectado (probado). Un caso usado no se puede borrar. |
| 11 | Auditoría. | El seeder escribe directo (no llena `audits` con miles de filas ni cambia `updated_at`). Desde la fase 3, los cambios de `id_caso` sí quedan en la Auditoría de cambios (el campo está en el modelo). |
| 12 | Borrar las columnas de texto antes de tiempo. | La fase 4 **no corre con `migrate`** (carpeta aparte) y se niega si queda una fila con caso y `id_caso` nulo, o si el código todavía usa la columna (fase 3 sin desplegar). |
| 13 | Perder el texto original al borrar (incluido el relleno). | La fase 4 copia **cada** valor no nulo a `caso_texto_respaldo` y comprueba la copia antes de borrar; si un borrado falla, repone lo borrado. |
| 14 | Arrepentirse después de la fase 4. | Su `down()` vuelve a crear las columnas en su lugar con sus valores exactos (probado). |
| 15 | FELCC (sistema anterior). | Hasta la fase 3 FELCC sigue pudiendo encenderse (las columnas existen). **Después de la fase 4, no.** |
| 16 | Caché de configuración/rutas. | `php artisan optimize` después de cada despliegue. |

## 6. Procedimiento en el servidor (fases 1 y 2)

```bash
php artisan down
php artisan db:backup                                  # respaldo completo antes de empezar
php artisan migrate:status | grep Pending              # debe salir SÓLO 2026_11_01_100000_crear_tabla_caso_y_agregar_id_caso
php artisan migrate --pretend                          # revisar: create table `caso` + alter table … add `id_caso`
php artisan migrate --force                            # fase 1
php artisan casos:migrar --simular                     # fase 2 en simulación: revisar el informe y el relleno
php artisan casos:migrar                               # fase 2 (o por tabla: --tabla=telefono, luego la siguiente…)
php artisan db:seed --class=RolesSeeder --force        # permisos casos.* para el administrador
php artisan optimize
php artisan up
```

Fase 4 (semanas después, con la fase 3 en uso): `db:backup` → `migrate --path=database/migrations/casos-fase-4 --pretend`
→ `migrate --path=database/migrations/casos-fase-4 --force`.

## 7. Ensayo (2026-11-01, copia de `felcc1` sin `ciudadano` y relacionadas, borrada al terminar)

| Paso | Resultado |
|---|---|
| `migrate --pretend` | Sólo `create table caso` y `alter table <5 tablas> add id_caso` + su clave foránea. |
| Fase 1 | 1,1 s. Huella de **las 36 tablas** (todas las columnas menos `id_caso`) idéntica. |
| Fase 2 simulada | 24 casos, 1.340 filas; después `caso` vacía y `id_caso` todo nulo. |
| Fase 2 (primero `--tabla=telefono`, luego el resto) | 1.336 teléfonos y 4 registros con su `id_caso`; 219 teléfonos y 21 registros sin caso → nulo. Huella de las 36 tablas idéntica salvo `id_caso`. |
| Segunda corrida | 0 asignadas. |
| Permisos | 5 nuevos, sólo administrador (y superadmin). |
| Fase 4 | Se niega: «el código todavía usa telefono.caso (falta desplegar la fase 3)». |
| Vuelta atrás de la fase 1 | Sin `caso` ni `id_caso`; huella de las 36 tablas idéntica al inicio. |

Pruebas: `tests/Feature/Casos/` — `MigracionCasosTest` (3), `CasosSeederTest` (7), `EliminacionColumnasCasoTest` (3).

## 8. Para decidir

1. **`AUTORIDADES`** (1.211 teléfonos, 78 % de los que tienen caso) parece una categoría, no un caso. Como pediste
   «registrar todos los casos», **se registra como un caso más**. ¿O se deja con `id_caso` nulo? (Si se deja nulo, su
   texto sólo quedará en el respaldo después de la fase 4.)
2. **¿Algún relleno propio de la institución** («EN TRÁMITE», «PENDIENTE»…) que deba quedar nulo?
3. **Fichas (`caso_cud`):** se proponen sin cambios. ¿De acuerdo?
4. **Fase 3:** ¿la programo ya? Archivos a cambiar: formularios y controladores de teléfono, vehículo (y vínculo con
   persona), registro criminal y faltas; búsquedas y filtros por caso/CUD; importaciones de teléfonos y vehículos;
   reportes CSV; detalle de persona; y la pantalla Casos (listado, detalle, editar, unir casos).
