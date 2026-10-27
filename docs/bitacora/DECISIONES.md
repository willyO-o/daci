# Decisiones

Cada decisión con su motivo y la alternativa descartada. Las más recientes al final.

## D-001 · Spatie con sus tablas estándar; la tabla `roles` antigua pasa a `roles_legado`

**Motivo:** el usuario pidió usar sólo las tablas de spatie/laravel-permission, y la tabla `roles` (con `nombre`,
`descripcion`) ya existía. Renombrarla permite crear la `roles` de Spatie sin borrar nada: la FK `users.role_id`
sigue apuntando a `roles_legado` (InnoDB la actualiza sola) y la columna no se toca. Se conservan los **mismos ids**
de rol. `down()` revierte todo; probado en una base temporal (migrar → verificar → rollback → comparar) antes de
aplicarla en `felcc1`.
**Descartado:** tablas de Spatie con nombres propios (`acceso_rol`…) — el usuario prefirió las estándar.

## D-002 · Permisos `modulo.accion` con traducción congelada del ACL anterior

**Motivo:** los permisos del sistema anterior (`personas_all`, `consulta_mandamientos`…) mezclaban «todo el
módulo» con acciones y varias rutas aceptaban «cualquiera de» tres permisos. Se reemplazan por permisos granulares
y se traduce cada permiso antiguo a los nuevos que concedía, de modo que cada rol queda con exactamente el mismo
acceso. Lo que el anterior decidía por **nombre de rol** (menú `@canany`, botones con `window.role`) también se
convirtió en permisos (`ACCESOS_POR_ROL` en la migración). Verificado: 6 roles, 49 permisos, 15/15 usuarios con su
rol.
**Descartado:** conservar los slugs antiguos tal cual — obligaba a seguir con `*_all` y con chequeos por rol.

## D-003 · Modelos en `App\Models` con el mismo nombre de clase

**Motivo:** `audits.auditable_type` guarda el nombre completo de la clase; moverlos (p. ej. a `App\Domain`) rompería
el historial de la Auditoría de cambios.

## D-004 · Velzon sobre Bootstrap 5, sin Tailwind

**Motivo:** la apariencia debe ser la de Velzon. Breeze trae Tailwind, cuyo `preflight` choca con Bootstrap. Se
quitó Tailwind y se usan los CSS originales de la plantilla (`material/assets/css`) desde `public/assets/css`, sin
jQuery: el comportamiento del layout (`assets/js/app.js` de la plantilla) se reescribió en `useTema()`.

## D-005 · `stevebauman/location` reemplazado por `App\Support\UbicacionIp`

**Motivo:** el paquete no soporta Laravel 13 (exige Guzzle 7). Sólo se usaba para mostrar ciudad y país de la IP en
la Auditoría de consultas; `UbicacionIp` consulta el mismo servicio que el paquete traía por defecto (ip-api.com)
con el cliente HTTP de Laravel, caché de un día y las mismas claves (`cityName`, `countryName`).

## D-006 · `intervention/image` fijado en v3

**Motivo:** el sistema anterior usa la API v3 (`ImageManager` + driver Imagick) para convertir fotos a WebP. La v4
cambia la API.

## D-007 · `box/spout` reemplazado por `openspout/openspout` v4

**Motivo:** `box/spout` está abandonado; OpenSpout es su continuación y el anterior ya lo tenía instalado. Las
importaciones se leen con un lector común (`App\Support\LectorTabular`) que conserva la detección de delimitador,
la normalización de cabeceras y el corte tras 20 filas vacías.

## D-008 · Login rechaza usuarios desactivados

**Motivo:** el sistema anterior los dejaba entrar y los expulsaba en la primera petición. Ahora el login responde
con el mismo mensaje y el middleware `AsegurarUsuarioActivo` sigue expulsando a quien sea desactivado con la sesión
abierta.

## D-009 · Sin excepciones de CSRF

**Motivo:** el anterior desactivaba CSRF en `mandamientos/*`, `personas/*` y `registro-criminal/*` (por las
peticiones jQuery). Con Inertia + axios el token viaja solo (cookie `XSRF-TOKEN`); no hace falta la excepción.

## D-010 · Las pruebas corren sobre MariaDB (`felcc_pruebas`), no SQLite

**Motivo:** el código heredado usa funciones de MySQL (`CONCAT`, `JSON_OBJECT`, `JSON_ARRAYAGG`, `MD5()`) que SQLite
3.40 no tiene. `phpunit.xml` apunta a `felcc_pruebas` y `tests/TestCase.php` aborta si la base es otra (candado
contra `felcc1`). `MigracionRolesTest` reconstruye la base con `migrate:fresh` porque los `down()` heredados no son
reversibles (claves foráneas) y no se editan.

## D-011 · Eliminar persona: una migración fallida ya no borra datos

**Motivo:** `Persona::migrateRelationsTo()` atrapa sus errores y devuelve `false`; el sistema anterior seguía y, con
«Eliminar completamente», borraba los datos que no se habían migrado. Ahora se aborta la transacción y se avisa.

## D-012 · «Eliminar duplicados» pasa de URL a comando artisan

**Motivo:** en el anterior era `GET /eliminar-duplicados` y la comprobación de la clave estaba mal escrita
(`!$codigo == '…'`): cualquier usuario con sesión podía ejecutarlo. Hace cambios masivos (restaura eliminados, une y
borra personas), así que ahora es `php artisan personas:eliminar-duplicados`, muestra antes lo que haría y pide
confirmación. Sin la tabla `documento` (rama de ciudadano, se ignora).

## D-013 · Auditoría de consultas sin HTML armado en el servidor

**Motivo:** `AuditoriaHelper` devolvía enlaces HTML con nombres de personas sin escapar; pintarlo con `v-html` abría
una vía de XSS. `App\Support\AccesosConsultados` devuelve descripción y URL por separado.

## D-014 · Correcciones de datos en Vehículos

1. Vincular persona guardaba el caso en `caso` (columna inexistente): se perdía. Va en `numero_informacion`.
2. Editar un vehículo leía `pivot->caso` y borraba el caso guardado. Ahora usa `numero_informacion`.
3. Las fotos se guardaban con el nombre original (dos «foto.jpg» se pisaban). Ahora nombre único en la misma
   carpeta `vehiculos/`; `nombre_archivo` conserva el original.
4. La ITV se ordena por año descendente (la primera fila resaltada es la última inspección).

## D-015 · Comportamientos heredados que se CONSERVAN a propósito

- La importación de teléfonos crea un registro por fila aunque el número exista (no hay índice único en la base y
  así trabajaba el sistema anterior). A revisar con el usuario.
- Al reemplazar el acta de ejecución de un mandamiento, la anterior no se borra (el anterior buscaba por
  `tipo_archivo` y nunca la encontraba); se muestra la más reciente.
- Los mandamientos cuya persona está eliminada no aparecen en listados ni detalle (filtro de `getMandamientos`).
- Un registro criminal no edita los datos personales: se corrigen en Personas (el anterior mostraba los campos pero
  no los guardaba; ahora se ven de sólo lectura).

## D-016 · Otras correcciones menores

- Ficha de registro: el anterior leía `division->nombre` (no existe) y la división salía vacía; y enviaba el HTML de
  los bloques editables, que el filtro de caracteres dejaba como «br». Ahora usa `division` y texto plano.
- Reportes «Registro criminal» y «Personas» cargaban relaciones inexistentes y fallaban siempre.
- Alta rápida de persona (desde mandamientos): «celular» y «dirección» se perdían por no coincidir con las columnas
  (`telefono`, `domicilio`).
- Filtro de personas por C.I.: un `orWhere` sin agrupar anulaba los filtros de género y estado civil.
- `agregarIdsAccedidos` sin identificador sumaba el acceso a cualquier consulta con identificador nulo.
- Cabecera «Respuesta Req.» del listado de teléfonos mostraba la persona vinculada: ahora dice «Persona Vinculada».
- La página de reportes anunciaba PDF, filtros e historial que no existían: sólo se muestra el CSV.
- `crypto.randomUUID` no existe por HTTP fuera de localhost: el identificador de consulta tiene respaldo.

## D-017 · Revisión de bugs (2026-10-22 23:15)

Revisión completa del código migrado contra el esquema real de `felcc1` (claves foráneas y largos de columna) y
contra los datos existentes (sólo lectura). Corregido:

1. **Eliminar completo una persona** fallaba siempre que tuviera un registro criminal con fotos, fichas o vehículos:
   `deleteRelationsData` borraba `registro_criminal` antes que sus hijos (FK RESTRICT). Ahora borra en el orden que
   permiten las claves, incluye fotos y fichas de sus registros y borra del disco los archivos (persona y
   mandamientos) **después** de confirmar. Un error de base se muestra como mensaje, sin borrar nada. No se puede
   migrar los datos a una persona eliminada.
2. **Eliminar un registro criminal** daba error 500 si tenía una ficha impresa o un vehículo vinculado (el anterior
   también). Ahora borra sus fichas, deja el vínculo vehículo-persona sin el registro y quita las fotos del disco.
3. **Archivos reemplazados** (imagen de mandamiento, fotos de registro): se borraban del disco dentro de la
   transacción; si luego fallaba algo, la base volvía atrás pero el archivo ya no existía. Ahora se borran al final.
   Eliminar un mandamiento o un vehículo también borra sus archivos (antes quedaban huérfanos).
4. **Editar un vehículo** borraba los vínculos con personas eliminadas (no se muestran en el formulario). **Editar un
   IMEI** desvinculaba sus teléfonos eliminados. Ahora se conservan.
5. **Validación vs. datos heredados**: 1 persona tiene un C.I. de 26 caracteres (importado) y no se podía editar
   (máx. 25). El C.I. admite el largo de su columna (250; el formulario sigue limitando a 25 lo que se escribe) y
   `datos_segip` el de un TEXT. El alta de registro criminal valida los largos de columna y que las fotos sean
   imágenes (antes: error de base o de Imagick → 500); el formulario muestra esos errores.
6. **Borrar una foto** (`multimedia.destroy`) exige el permiso del registro dueño (persona, mandamiento o vehículo);
   antes bastaba cualquiera de los tres.
7. **Importaciones**: un archivo dañado o con otra extensión respondía 500; ahora 422 con mensaje. Personas ya no
   ofrece `.xls` (ningún lector lo soporta, tampoco el anterior).
8. **Auditoría de consultas**: un identificador ajeno ya no suma accesos a la consulta de otro usuario.
9. Vincular persona a vehículo: la comprobación de duplicado normaliza el tipo igual que al guardar.
10. Frontend: el scroll infinito no pedía la página siguiente si las tarjetas no llenaban la caja; una petición
    cancelada apagaba el «Cargando» de la siguiente (también en `BuscadorRemoto`, que además mostraba «Sin
    resultados» durante la espera).

Pedido del usuario en la misma ronda: las fotos se ven en **FsLightbox 3.4.0-2**, la misma librería y versión del
sistema anterior (archivo idéntico), en lugar del modal de Bootstrap. Se carga sólo al abrir un visor.

Pruebas de regresión: `tests/Feature/Revision/IntegridadDatosTest.php` (10).

## D-018 · `users.role_id` en espejo del rol de Spatie; migración de roles a prueba de producción (2026-10-23 11:05)

**Contexto:** producción es otra base, con mucha más información que la local; no se pierde nada y sólo se pueden
modificar roles, permisos y sus controles.

1. **`role_id` en espejo.** Antes DACI asignaba el rol sólo en Spatie y `users.role_id` quedaba con el valor de la
   migración: tras un `migrate:rollback` de emergencia, los usuarios creados en DACI quedaban sin rol (FELCC falla con
   `$user->role->nombre`) y los cambios de rol se perdían. Ahora `User::asignarRol()` guarda el id del mismo rol en
   `roles_legado` (null si es un rol creado en DACI) y `acceso:sincronizar` repara desfases.
   **Descartado:** dejar `role_id` como dato histórico — rompía la vuelta atrás que promete `DESPLIEGUE.md`.
   **Descartado:** que `sincronizar` ponga null a usuarios sin rol en Spatie — borraría el único dato de rol que tienen.
2. **Migración de roles.** Se detiene sin tocar nada si ya existe alguna tabla de Spatie o `roles_legado` (la
   reversión las borraría), y si falla a mitad ejecuta `down()`: sin esto la base quedaba a medias y
   `migrate:rollback` no podía deshacerla (la migración no queda registrada). Caso real posible: un rol antiguo con
   otras mayúsculas (`Tecnico_DACI`) choca con el rol base en el índice único (la colación no distingue mayúsculas).
3. **Migraciones de FELCC pendientes.** La copia local tenía 34 de 36 (sin `audits` y con `persona.ci` único).
   No se fuerza nada: `DESPLIEGUE.md` indica revisar `migrate:status` en producción y decidir antes de migrar.

Pruebas: `UsuariosTest` (2) y `MigracionRolesTest` (2).

## D-019 · Segunda verificación de paridad (2026-10-24 09:22)

Se recorrieron otra vez las 142 rutas, las 60 vistas Blade y todos los eventos de los scripts jQuery del sistema
anterior. Faltaban tres piezas del **layout** (la funcionalidad de los módulos ya estaba completa):

1. **«Theme Customizer» de Velzon** (engranaje flotante): ahora `AppCustomizer` con los mismos ajustes — esquema de
   color, ancho (completo/encajado), posición (fija/desplazable), color de la barra superior, tamaño del menú (4),
   vista del menú (normal/separado), color del menú (claro, oscuro, 4 degradados), imagen del menú (4) y pantalla de
   carga — guardados por navegador en `felcc.tema` (lee el formato anterior). **No se incluye** «Layout» horizontal /
   dos columnas: requiere otro marcado del menú; queda a decisión del usuario.
2. **Botón «volver arriba»** y **pantalla de carga** (`#preloader`) de la plantilla.
3. **Página de error propia**: el anterior tenía su 404 («Uppsi! Página no encontrada»). Ahora `Errores/Error` con
   el mismo diseño, también para 403/419/500/503; JSON sigue siendo JSON y en local los 500 muestran la depuración.

Revisado y **sin nada que trasladar** (código muerto en el anterior): `telefonos.agregar-imei` (botón oculto y escribe
en una columna inexistente `imeis_asociados`), `.btn-desvincular-persona-tabla` (el evento existe pero ningún HTML
dibuja el botón), buscador móvil y notificaciones de la barra superior (plantilla sin función / comentado), rutas
`create/edit/show/update/destroy` vacías de los catálogos y de `paises`, y los endpoints de filtros de auditoría
(ahora llegan como props).

## D-020 · `PermisosSeeder`: actualizar permisos sin afectar el resto de la base (2026-10-26 17:31)

**Pedido:** un seeder que, cada vez que se agregue un permiso, lo inserte al ejecutarse sin afectar el resto de la
información.

**Decisión:** la lógica vive en `App\Support\Acceso\SincronizadorAcceso` y la usan `PermisosSeeder` (que llama
`DatabaseSeeder`) y `acceso:sincronizar` (atajo del seeder). Corre en una transacción y **sólo agrega**: permisos y
roles base que falten, todos los permisos al superadmin, el espejo de `users.role_id` y la asignación de
`CatalogoPermisos::asignacionInicial()` **sólo para los permisos creados en esa corrida**. No usa `syncPermissions`
ni borra nada: lo configurado en la pantalla de roles se respeta. Los permisos de la base que ya no están en el
catálogo se informan, no se borran.

**Descartado:** sincronizar «a espejo» (borrar lo que no está en el catálogo o rehacer los permisos de cada rol) —
borraría asignaciones hechas por el administrador. **Descartado:** dar cada permiso nuevo a los roles en todas las
corridas — volvería a dar un permiso que el administrador quitó.

Verificado sobre la base local `felcc`: segunda corrida con sumas de verificación idénticas en `permissions`,
`roles`, `role_has_permissions`, `model_has_roles`, `users` y `persona`. Pruebas: `PermisosSeederTest` (6).

## D-021 · Cambiar el estado de ejecución desde Consultas › Mandamientos (2026-10-26 17:48)

**Pedido:** con permiso, un botón que abra un modal para cambiar sólo el estado; si el estado dice «ejecutado», además
fecha de ejecución, ejecutado por y acta; con otros estados, sólo el campo. Sólo en el módulo de consulta.

1. Permiso nuevo **`mandamientos.estado`** (módulo Mandamientos). Por `asignacionInicial()` lo reciben al crearse
   `administrador`, `tecnico_felcc` y `tecnico_daci` (los que ya podían editar mandamientos, estado incluido);
   los consultores no. La ruta exige además `consultas.mandamientos`.
2. `PATCH /consultas/mandamientos/{id}/estado` (`ActualizarEstadoMandamientoRequest`): «EJECUTADO» se detecta
   como en el formulario de edición (el estado lo contiene; «POR EJECUTAR» no). Con «EJECUTADO»: fecha (≤ hoy),
   ejecutado por y acta obligatorios (el acta no, si ya tiene una), detalle opcional. Con otro estado **sólo se
   escribe `estado`**: fecha, ejecutor, detalle y acta registrados se conservan y el resto de campos que lleguen
   se ignora. Estado y ejecutor se guardan en mayúsculas.
3. El acta va a las mismas carpetas (`mandamientos-pdf-actas` / `mandamientos-img-actas`) y la anterior se
   conserva (igual que al editar); se muestra la más reciente. El cambio queda en la Auditoría de cambios.
4. Sugerencias propias: botón «Hoy» para la fecha, «Detalle de la ejecución» (columna existente
   `detalle_ejecucion`, que ninguna pantalla mostraba; ahora también en el detalle), resumen del mandamiento y
   estado actual en el modal, enlace al acta actual, sugerencias con los 30 estados más usados.

**Descartado:** reutilizar `mandamientos.editar` — daría el botón a quien sólo debe editar desde el listado y no
permitiría separar quién cambia estados. **Descartado:** vaciar fecha/ejecutor al pasar a otro estado — perdería datos.

Pruebas: `tests/Feature/Mandamientos/EstadoMandamientoTest.php` (7).

## D-022 · Registrar personas, teléfonos, IMEIs y vehículos sin ver el listado (2026-10-26 18:42)

**Pedido:** que quien sólo deba registrar tenga un acceso como «Registrar Mandamiento», reutilizando los
formularios, con botones en forma de tarjetas en el Dashboard. El usuario eligió **reutilizar el permiso
«Registrar» (`*.crear`)** en lugar de crear permisos nuevos.

1. Rutas `personas.create`, `telefonos.create`, `imeis.create`, `vehiculos.create` (permiso `*.crear`) y
   páginas `*/Registrar` sobre un marco común `Components/Ui/PaginaRegistro.vue`. Guardan con las mismas rutas
   `*.store`; el servidor vuelve a la página y el formulario queda limpio para el siguiente.
2. Los formularios salieron de los listados a componentes (`FormularioPersona`, `FormularioTelefono`,
   `FormularioImei`, `FormularioVehiculo`), como ya era `FormularioMandamiento`; el modal del listado y la página
   usan el mismo. Verificado contra la versión anterior: mismas líneas de plantilla (sólo cambia el `id` por prop
   y, en teléfono y vehículo, un `:key` en el buscador para vaciarlo al registrar otro).
3. Menú: «Registrar …» en EREBOR sólo para quien tiene `*.crear` sin `*.listar` (misma regla que mandamientos).
4. Dashboard: sección «Registro rápido» con una tarjeta por módulo que el usuario puede registrar; reemplaza al
   botón suelto «Nuevo Mandamiento».

**Descartado:** permisos nuevos `*.registrar` — el usuario prefirió no duplicar «Registrar». Con esto,
`tecnico_felcc` (tiene `personas.crear` sin listar) ve ahora «Registrar Persona» en el menú y en el Dashboard.

Pruebas: `tests/Feature/Acceso/RegistroDirectoTest.php` (10) y 4 pantallas nuevas en `PaginasTest`.

## D-023 · Módulo Faltas y Contravenciones (2026-10-26 20:35)

**Pedido:** un módulo para registrar personas por faltas y contravenciones, «lo mismo que registro criminal, con
todos sus permisos», separado de Registro Criminal, con **otra tabla** de **los mismos campos, nada más**, sin
**afectar las otras tablas** ni los datos de producción. Un solo módulo: «Faltas y Contravenciones».

1. **Base:** `2026_10_26_210000_crear_tablas_faltas_y_contravenciones` sólo CREA 4 tablas, copia exacta de las de
   Registro Criminal: `falta_contravencion` (← `registro_criminal`), `foto_falta_contravencion` (← `fotos_registro`),
   `ficha_falta_contravencion` (← `ficha_registro`) y `ficha_falta_contravencion_plantilla`. No altera ninguna tabla
   existente (`migrate --pretend`: todo el SQL es sobre las 4 nuevas). Se detiene sin cambios si alguna ya existe;
   si falla a mitad borra lo creado; `down()` borra sólo esas 4. Las claves foráneas viven en las tablas nuevas. La
   plantilla de la ficha se copia de la activa de Registro Criminal (sólo se lee) cambiando «REGISTRO CRIMINAL» por
   «REGISTRO DE FALTAS Y CONTRAVENCIONES».
2. **Código:** `ModuloAntecedente` (enum, un caso) define tablas, modelos, rutas (`faltas-contravenciones.*`),
   permisos (`faltas_contravenciones.*`) y carpeta de fotos; `AntecedenteController` tiene la lógica (copiada de
   Registro Criminal) y `FaltaContravencionController` elige el módulo. Los registros se buscan sólo en sus tablas: un
   id de Registro Criminal da 404 (probado en ver, editar, eliminar, ficha y código). La ficha que se corrige debe ser
   del mismo registro.
3. **Registro Criminal no cambia:** sus tablas, controlador y páginas siguen igual; a `TarjetaRegistro`,
   `DetalleRegistro`, `FichaRegistro` e `InformacionAdicional` sólo se les agregaron props opcionales cuyo valor por
   defecto es el de Registro Criminal. `divisiones.store` acepta también `faltas_contravenciones.crear|editar`.
4. **Permisos** (`faltas_contravenciones.listar/ver/crear/editar/eliminar/ficha` y `consultas.faltas_contravenciones`,
   asignación inicial igual a Registro Criminal): administrador todo; técnico DACI todo menos eliminar; consultor
   DACI consultar y ver.
5. **Eliminaciones:** eliminar borra el registro con sus fotos y fichas en una transacción, y los archivos sólo tras
   confirmar. Eliminar o unir una persona (`deleteRelationsData`, `migrateRelationsTo`, `personas:eliminar-duplicados`)
   incluye sus faltas y contravenciones; el modal de eliminar persona las cuenta.
6. Diferencias menores con Registro Criminal, para no romper: el número correlativo cuenta también los eliminados
   (no se repite); tras guardar, quien no ve el listado vuelve al formulario en vez de recibir un 403.
7. **Menú:** «Faltas y Contravenciones» en EREBOR, junto a Registro Criminal, y «Consultar Faltas y Contravenciones»
   en Consultas.

**Corrección en la misma ronda:** primero se entendió «dos módulos» como Faltas y Contravenciones por separado (8
tablas, 14 permisos). El usuario aclaró que los dos módulos son Registro Criminal y Faltas y Contravenciones. En la
base local se revirtió aquella migración (tablas vacías, sola en su lote), se borraron sus 14 permisos (sin
asignaciones directas a usuarios) y se aplicó la definitiva; en producción nunca existió.

**Descartado:** columna `tipo` en `registro_criminal` (modificaría una tabla de producción).
**No incluido** (el usuario pidió no agregar más): totales en el Dashboard, reporte CSV y pestañas en el detalle de
la persona.

Pruebas: `tests/Feature/Antecedentes/AntecedentesTest.php` (11) y `MigracionAntecedentesTest.php` (3).

## D-024 · Rol predefinido `pol_110` (2026-10-27 13:05)

**Pedido:** un seeder para un rol nuevo `pol_110` que consulte mandamientos y registre vehículos, teléfonos,
personas e IMEIs, sin poder ver los módulos (usando las páginas «Registrar …» de D-022).

1. El rol se define en `CatalogoPermisos::rolesPredefinidos()` y lo crea `RolesSeeder` (que antes corre
   `PermisosSeeder`; `DatabaseSeeder` llama a `RolesSeeder`). Permisos: `consultas.mandamientos`, `mandamientos.ver`
   (abrir el detalle desde la consulta, como `consultor_felcc`), `personas.crear`, `telefonos.crear`, `imeis.crear`,
   `vehiculos.crear`. Sin `*.listar` ve en el menú y en el Dashboard las páginas «Registrar …» y no los listados.
2. El seeder sólo crea el rol si falta; a un rol que ya existe no lo toca, así lo que se cambie en la pantalla de
   roles se respeta. Falla si la definición menciona un permiso que no está en el catálogo.
3. No es rol base: se puede editar desde Roles. No existe en `roles_legado`, así que sus usuarios quedan con
   `users.role_id` nulo (D-018).

**Descartado:** asignar los permisos en cada corrida — devolvería permisos quitados a mano.

Aplicado en la base local `felcc`: rol creado con sus 6 permisos; los otros 6 roles sin cambios. Pruebas:
`tests/Feature/Acceso/RolesSeederTest.php` (5).

## D-025 · Rol `conciliacion_ciudadana`; Faltas y Contravenciones sólo para él y los administradores (2026-10-27 13:15)

**Pedido:** un rol nuevo `conciliacion_ciudadana` que consulte mandamientos y registre faltas y contravenciones; «en
principio sólo éste debería tener el permiso de faltas y contravenciones, y los administradores»; sin afectar la base.

1. Se suma a `CatalogoPermisos::rolesPredefinidos()` (lo crea `RolesSeeder`, como `pol_110`) con `consultas.mandamientos`,
   `mandamientos.ver` y del módulo Faltas y Contravenciones: listar, ver, crear, editar, ficha y su consulta. **Eliminar
   queda para los administradores.** Descripción en la cabecera: «Conciliación Ciudadana».
2. La asignación inicial de Faltas y Contravenciones (D-023) pasa a ser **sólo administrador** (superadmin tiene
   todo). En la base local se quitaron esas asignaciones a `tecnico_daci` (6) y `consultor_daci` (2): el módulo no
   tenía registros ni asignaciones directas a usuarios; los permisos siguen existiendo. En producción nunca existieron.

**Interpretación a confirmar:** «registrar faltas y contravenciones» se tomó como trabajar el módulo (ver el listado,
registrar, editar, consultar, imprimir la ficha), no sólo dar de alta.

Pruebas: `RolesSeederTest` (+3).

## D-026 · Estado civil y ocupación se actualizan al editar un registro (2026-10-27 14:01)

**Pedido:** «en el formulario de registro criminal al editar no permite modificar el estado civil ni la ocupación;
son datos que pueden cambiar a lo largo del tiempo».

Al **editar** un registro de Registro Criminal o de Faltas y Contravenciones, el estado civil y la ocupación se
pueden cambiar y se guardan en la **persona** (son columnas de `persona`; quedan en la Auditoría de cambios). Se
validan igual que en Personas (`estado_civil` en la lista del `enum`, `ocupacion` hasta 150). Los demás datos
personales siguen de sólo lectura y se corrigen en Personas. Si la petición no trae esos campos, no se tocan.
Al **registrar** con una persona ya elegida siguen bloqueados, como antes (el alta no modifica a la persona).

Modifica parcialmente D-015 («un registro criminal no edita los datos personales»). Datos existentes revisados:
todos los `estado_civil` están en la lista y la ocupación más larga tiene 47 caracteres. Pruebas: +3.

## D-027 · Búsqueda de C.I. + complemento al registrar, con COALESCE (2026-10-27 14:12)

**Pedido:** al crear un registro criminal o una falta, buscar en la base por Nro. C.I. + complemento si la persona ya
existe, usando COALESCE para evitar errores en las búsquedas.

1. `Persona::scopeConCi($ci, $complemento)`: `TRIM(COALESCE(ci,'')) = ?` y `TRIM(COALESCE(complemento,'')) = ?`. Trata
   como iguales NULL y '' (en SQL `complemento = NULL` nunca es verdadero) y no depende de espacios; sin complemento
   busca a quien no lo tiene. `persona.ci` no tiene índice, así que la comparación con funciones no pierde ninguno
   (≈10–20 ms con 4.095 personas).
2. `personas.check-ci` usa ese criterio (lo comparten Personas, Registro Criminal y Faltas y Contravenciones), devuelve
   los datos para completar el formulario y, con `contar` (lista blanca), cuántos registros tiene la persona en el
   módulo, sólo si el usuario puede registrar en él.
3. Formularios de alta: `AvisoPersonaExistente` busca mientras se escribe (400 ms, desde 4 caracteres, cancela la
   búsqueda anterior) y avisa «Ya existe una persona con C.I. …» con «Usar esta persona», o «se registrará una nueva».
4. **Corrección:** el alta (Registro Criminal y Faltas y Contravenciones) reutilizaba una persona buscando sólo por
   C.I., sin el complemento (1234567-A quedaba asociado a 1234567). Ahora usa C.I. + complemento.
5. **Corrección:** el filtro de C.I. del listado de Personas usaba `CONCAT(ci, '-', complemento)`, que da NULL sin
   complemento; ahora con COALESCE.

**Sin cambiar** (otro alcance, se avisa): las importaciones y la alta rápida de `Persona` siguen buscando sólo por C.I.
Pruebas: `tests/Feature/Personas/BusquedaCiTest.php` (6).

## D-028 · Pantalla «Cargando…» al navegar entre páginas (2026-10-27 14:47)

**Pedido:** un cargador con el logo y «Cargando…» al navegar entre rutas, sin bugs.

`Components/Layout/CargaNavegacion.vue` en el layout persistente (`AppLayout`), sobre los eventos `start`/`finish` del
router de Inertia:

1. Sólo cambios de página: GET con `preserveState === false`, sin `only`, sin `prefetch` ni `async`. No se abre con
   filtros y paginación de listados (`useListado`: `preserveState`), recargas parciales (`router.reload({ only })`),
   precargas ni formularios (POST/PUT, que ya muestran «Guardando…»).
2. Sin parpadeo: aparece si tarda más de 250 ms y, visible, dura al menos 400 ms.
3. Lleva la cuenta de las visitas en curso por objeto de visita (Inertia 2.3 pasa el mismo objeto a `start` y
   `finish`, y emite `finish` también al cancelar o interrumpir): si una navegación interrumpe a otra, sigue visible
   hasta la última. Limpia escuchas y temporizadores al desmontarse (p. ej. al cerrar sesión).
4. Logo FELCC con pulso, barra indeterminada, variables `--vz-*` (modo oscuro), `prefers-reduced-motion`,
   `role="status"`; bloquea clics mientras carga. La barra de progreso de Inertia se conserva.

Verificado con una simulación de la misma lógica (5 casos: rápida sin parpadeo, lenta ≥ 400 ms, interrumpida, visitas
que no deben abrirla, `finish` ajeno). El proyecto no tiene pruebas de JS.

## D-029 · Guía paso a paso en Consultas › Mandamientos con driver.js (2026-10-27 15:03)

**Pedido:** un tour para Consultar Mandamientos; el usuario usaba driver.js. Luego: «sólo debe activarse al hacer clic
en un ícono de ayuda».

1. **driver.js 1.9.0** (MIT, sin dependencias), versión fija. Se eligió sobre Shepherd.js (más pesado, licencia a
   revisar), Intro.js (AGPL o comercial) y vue-tour / vue3-tour (Vue 2 o poco mantenido). Se tomó la 1.9.0 y no la
   1.10.2 porque ésta tenía días de publicada.
2. `Composables/useTour.js`: carga driver.js y su CSS **sólo al abrir la guía** (archivo aparte en el build), textos
   en español («Siguiente», «Anterior», «Finalizar», «Paso n de N»), salta los pasos cuyo elemento no existe o no se ve
   (o muestra su texto alternativo `siNoExiste`) y se cierra al salir de la pantalla. Reutilizable en otras pantallas.
3. Consultas › Mandamientos: ícono de ayuda (?) en el encabezado — **única forma de abrirla**, no se abre sola ni
   guarda nada. Pasos: presentación y auditoría, filtro, texto, fechas, Buscar, vista, resultados (o «aquí
   aparecerán…» si no buscó), ver detalle, estado de ejecución (sólo con permiso), archivo, fotos y el propio botón.
   Los elementos se marcan con `data-tour="…"` (no con clases), así un cambio de diseño no rompe la guía.
4. Estilos en `resources/css/app.css` (`.guia-felcc`) con las variables `--vz-*` (modo oscuro).
   Corrección: el `:hover`/`:focus` de driver.css (fondo `#f7f7f7`, mismo peso y cargado después) dejaba «Siguiente»
   blanco sobre blanco; los estados hover/foco usan selectores `.driver-popover.guia-felcc …`, que pesan más.
5. `Components/Ui/BotonAyuda.vue`: ícono de ayuda que, al pasar el mouse o enfocarlo con el teclado, se ensancha y
   muestra «¿Necesita ayuda? Ver la guía» de izquierda a derecha (`clip-path` + ancho, ~250 ms, curva suave). Sin hover
   (táctil) queda el ícono; el texto va en `aria-label`; respeta `prefers-reduced-motion`.
6. La misma guía en **Consultar Registro Criminal** y **Consultar Faltas y Contravenciones** (pasos compartidos en
   `Utils/guias.js` → `guiaConsultaRegistros(modulo)`; marcas `data-tour` en `TarjetaRegistro`). En las tres consultas se
   quitó el párrafo de instrucciones (ocupaba espacio; lo explican la guía y el estado vacío).
7. Tarjeta de mandamiento: lápiz junto al estado que abre el mismo modal «Estado de Ejecución»; sólo aparece con
   `puedeCambiarEstado` (Consultas › Mandamientos y permiso `mandamientos.estado`). El botón verde del encabezado se
   conserva y también lleva el lápiz (antes un círculo con check), para que se entienda que es la misma acción.

## D-030 · Guía resumida en todos los formularios de registro (2026-10-27 15:24)

**Pedido:** un tour resumido en todos los formularios de registro, con un ícono arriba (en el modal si es modal); en
los formularios largos, resumido: «no quiero los pasos a detalle porque será tedioso».

1. **Un paso por bloque de campos**, no por campo (3 a 6 pasos + Guardar). Textos en `Utils/guias.js`:
   `guiaFormularioMandamiento`, `guiaFormularioPersona`, `guiaFormularioTelefono`, `guiaFormularioImei`,
   `guiaFormularioVehiculo` y `guiaFormularioRegistro(modulo)` (Registro Criminal y Faltas y Contravenciones). Dicen qué
   es obligatorio y explican lo no evidente: «+ Añadir», «(añadir nuevo)», aviso de C.I. existente, EJECUTADO, límites
   de fotos, «Guardar y Registrar Nuevo».
2. **Dónde está el ícono:** en los modales de alta/edición de los listados (Mandamientos, Personas, Teléfonos, IMEIs,
   Vehículos) junto al título, con la prop nueva `BaseModal :guia` (versión `compacto` de `BotonAyuda`); en las páginas
   «Registrar …» (prop `PaginaRegistro :guia`), «Registrar Mandamiento» y los formularios de Registro Criminal y Faltas
   y Contravenciones, en el encabezado. Sólo se abre con el ícono.
3. Marcas `data-tour="f-…"` en las secciones de cada formulario y `f-guardar` en su botón Guardar.
4. **Modales:** Bootstrap atrapa el foco dentro del modal y los botones de la guía quedan fuera; mientras la guía está
   abierta se suspende la trampa (`_focustrap.deactivate()`, Bootstrap 5.3.8) y se reactiva al cerrarla si el modal
   sigue abierto. Al cerrar el modal se cierra también la guía (`useTour().cerrar`, opción `alCerrar`).

5. También en **Usuarios** (nombre y correo, rol, contraseña, guardar) y en **Roles y Permisos** (nombre y
   descripción, copiar permisos de otro rol, permisos por módulo, guardar): `guiaFormularioUsuario`, `guiaFormularioRol`.

**No incluido:** el alta rápida de persona dentro del formulario de mandamiento (`ModalPersonaRapida`, 4 campos).

## D-031 · Espera de 900 ms en los buscadores sin botón (2026-10-27 15:30)

**Pedido:** en los buscadores de Registro Criminal, Faltas, Personas, Teléfonos, IMEIs y Vehículos, y en los demás
lugares que buscan sin botón, esperar 900 ms: hacían peticiones muy seguidas.

Constante única `ESPERA_BUSQUEDA = 900` (`Utils/espera.js`), contada desde la última tecla, en: `useListado` (Personas,
Teléfonos, IMEIs, Vehículos, Usuarios, Auditoría de consultas y de cambios), los listados con scroll infinito
(Registro Criminal, Faltas y Contravenciones, Mandamientos; antes 500 ms), `BuscadorRemoto` (buscadores de los
formularios; antes 250 ms), el aviso de C.I. existente (antes 400 ms) y el aviso de C.I. duplicado del formulario de
Personas (antes 350 ms). Cada búsqueda nueva sigue cancelando la anterior. Los selects y el rango de fechas filtran
al instante, y `SelectorCatalogo` filtra su lista en el navegador (no consulta al servidor). Las consultas con botón
«Buscar» no cambian.

## D-032 · La ficha de registro ya no falla si la base no tiene plantilla (2026-10-27 15:35)

**Error reportado:** «No query results for model [App\Models\FichaRegistroPlantilla]» al imprimir la ficha.

**Causa:** la vista previa exigía una fila ACTIVA en `ficha_registro_plantilla` (`firstOrFail`). En la base local esa
tabla tiene 0 filas: FELCC la llenaba con `PlantillaFichaSeeder` al instalarse y esta copia no lo corrió.

**Corrección (sin escribir en la base):** `FichaRegistroPlantilla::TEXTO_ESTANDAR` (el texto del seeder, ahora en un solo
lugar) y `activaOEstandar()`: si hay plantilla activa se usa (producción), si no, el texto estándar sin guardarlo. Lo
mismo en Faltas y Contravenciones, con «REGISTRO DE FALTAS Y CONTRAVENCIONES». `PlantillaFichaSeeder` usa la constante.
Verificado sobre la base local (vista previa 200, la tabla sigue vacía). Pruebas: +2.

Para editar el texto en la base local se podría correr `php artisan db:seed --class=PlantillaFichaSeeder` (inserta una
fila); no se hizo, porque escribe en una tabla existente.

## D-033 · Pantalla «Plantilla de la Ficha» (2026-10-27 15:43)

**Pedido:** una interfaz para modificar la plantilla de la ficha de registro.

1. `PlantillaFichaController` + página `PlantillaFicha/Editar` (menú EREBOR › Plantilla de la Ficha). Pestañas por
   módulo con plantilla: **Registro Criminal** (`ficha_registro_plantilla`) y **Faltas y Contravenciones**
   (`ficha_falta_contravencion_plantilla`); sólo se ven las que el usuario puede editar.
2. Editor por bloque (introducción, requiere, persona, resultado, nota de certificación, nota general) con contador
   (largo de la columna), **variables con un clic** insertadas en el cursor (`{fecha_hora_actual}`, `{nombre_persona}`,
   `{ci_persona}`, `{division}`, `{fecha_aprehension}`, `{delito}`, cada una sólo en su bloque) y aviso de variable
   inexistente. **Vista previa en vivo** (`Components/RegistroCriminal/HojaFicha.vue`, mismo diseño que la ficha
   editable, de sólo lectura) con datos de ejemplo y el bloque en edición resaltado.
3. «Usar texto estándar» (`FichaRegistroPlantilla::TEXTO_ESTANDAR`, no guarda hasta «Guardar»), «Descartar cambios» y
   aviso al salir con cambios sin guardar. Guía con el ícono de ayuda.
4. **Servidor:** sólo escribe en la fila ACTIVA del módulo (la crea si no hay ninguna); no toca filas inactivas ni
   otras tablas. Valida largos y rechaza variables que no existen en el bloque. Permisos nuevos
   `registro_criminal.plantilla` y `faltas_contravenciones.plantilla` (sólo administrador; el del módulo se exige
   también al guardar). Los dos modelos de plantilla pasan a ser auditables (Auditoría de cambios).

Base local: `PermisosSeeder` creó los 2 permisos (59 en total) y se los dio a administrador. La tabla de plantillas de
Registro Criminal sigue vacía hasta que alguien guarde. Pruebas: `PlantillaFichaTest` (7) + pantalla en `PaginasTest`.

## D-034 · Auditoría de consultas en todos los módulos (2026-10-27 15:54)

**Pedido:** auditar todas las consultas de todos los módulos (personas, teléfonos, IMEIs, etc.) de cualquier usuario,
sin romper lo actual ni los datos de producción.

**Sin cambios en la tabla** `auditar_consultas` (sólo filas nuevas; las existentes no se tocan). Se registra:

1. **Búsquedas en los listados** — `listado_personas`, `listado_telefonos`, `listado_imeis`, `listado_vehiculos`,
   `listado_mandamientos`, `listado_registro_criminal`, `listado_faltas_contravenciones`, `listado_usuarios` — con sus
   criterios y la cantidad de resultados (`Controller::auditarBusqueda` → `AuditarConsultas::registrarBusqueda`). No se
   registran: el listado sin filtros, elegir sólo el campo sin texto, las páginas siguientes / el scroll, ni la misma
   búsqueda del mismo usuario dentro de 10 minutos (`MINUTOS_SIN_REPETIR`).
2. **Detalles abiertos** — persona, teléfono, IMEI, vehículo, mandamiento, registro criminal, faltas y contravenciones
   (`registrarAcceso`): si llegan con el `identificador` de una consulta auditada se suman a ella, **igual que antes**;
   si no, quedan como fila `detalle_*` con el registro en `ids_accedidos` (sin repetir en 10 minutos).
3. **Exportación de reportes** CSV (`exportar_reporte`), con el tipo y la cantidad de registros.
4. **Nunca interrumpe** la consulta: los registros van dentro de `rescue()` (si fallan, el error queda en el log y el
   usuario recibe sus resultados).
5. Pantalla de auditoría: nombre legible de cada módulo (`AuditarConsultas::ETIQUETAS`) en el filtro, la tabla y el
   detalle; los accesos a teléfonos, IMEIs y vehículos se describen por número / IMEI / placa y la persona enlaza a su
   ficha.

**No incluido:** los buscadores auxiliares de los formularios (elegir persona o teléfono, aviso de C.I.), que
generarían una fila por cada consulta de autocompletado. Pruebas: `tests/Feature/Revision/AuditoriaConsultasTodosTest.php` (7).

## D-035 · Sin «¿Olvidó su contraseña?» en el login (2026-10-27 16:00)

**Pedido:** deshabilitar el enlace de recuperar contraseña del login; lo hará el administrador.

Se quitaron las rutas `password.request`, `password.email`, `password.reset` y `password.store` (ahora 404), sus
controladores (`PasswordResetLinkController`, `NewPasswordController`), sus pantallas (`ForgotPassword.vue`,
`ResetPassword.vue`) y su prueba de Breeze. El login muestra «¿Olvidó su contraseña? Solicítela al administrador.»
La contraseña se restablece en Seguridad › Usuarios › Editar (ya existía); cada usuario puede cambiar la suya en
Perfil. Se conservan `password.confirm` y `perfil.password`. La tabla `password_reset_tokens` no se toca. Los archivos
quitados quedan en el historial de git.

Pruebas: `tests/Feature/Auth/RecuperarClaveDeshabilitadaTest.php` (3).

## D-036 · Revisión final y ensayo del despliegue sobre una copia de producción (2026-10-27 16:20)

**Pedido:** verificar que nada rompa la base de producción (sólo deben cambiar roles y permisos y las tablas nuevas de
Faltas y Contravenciones) e indicar el proceso de migración y reemplazo. Producción está en una red privada sin git:
se lleva en ZIP y se instala a mano.

1. **Migraciones:** las 36 de FELCC están idénticas en DACI; sólo hay 2 nuevas (roles y Faltas y Contravenciones).
2. **Ensayo:** copia de la base local (sin `ciudadano`) en `felcc_ensayo`, llevada al estado de producción (36 de
   FELCC, nada de DACI); suma de verificación de todas las tablas; `migrate --force` + `db:seed --class=RolesSeeder`.
   Resultado: sólo cambiaron `migrations` y `roles`; 9 tablas nuevas; ninguna desaparecida; `roles_legado` con las
   filas originales; 15/15 usuarios con su rol; `users.role_id` sin cambios. `migrate:rollback --step=2` dejó la base
   **idéntica** al estado original. La base de ensayo se borró.
3. **Hallazgos para el servidor:** DACI exige **PHP 8.3+** (FELCC 8.2); sin internet el ZIP debe llevar `vendor/` (sin
   dev) y `public/build/`; ningún código de la aplicación usa paquetes de desarrollo; FELCC no guarda datos cifrados
   (se puede generar `APP_KEY` nueva); `config:cache`, `route:cache` y `view:cache` funcionan; el respaldo `db:backup`
   es el mismo de FELCC. **FELCC no puede volver a encenderse** sobre la base migrada (salvo revirtiendo).
4. `DESPLIEGUE.md` reescrita para instalación manual con ZIP, con el resultado del ensayo, la vuelta atrás y lo que
   DACI escribe en la base durante el uso normal.
