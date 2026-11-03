# 2026-11-02 · Módulo Evidencias (D-038)

**Pedido:** módulo para registrar objetos secuestrados, recuperados o recolectados (evidencia + artículos + archivos),
con publicación individual revisada para un futuro portal ciudadano. Decisiones del usuario: funcionario como campo de
texto; estados REGISTRADA / CERRADA / ANULADA; sin ubicación de almacenamiento; con Unidad / División relacionada con
las tablas existentes; **sin API** (el portal será otro proyecto que leerá la base); código de evidencia lo escribe el
usuario; permisos sólo para el administrador.

## Base de datos (sólo tablas nuevas)

`2026_11_02_120000_crear_tablas_evidencias` — comprueba antes (sólo lecturas) y se detiene sin cambios si algo no
calza; funciona con `--pretend`; `down()` borra sólo lo suyo.

| Tabla | Relaciones con lo existente | Notas |
|---|---|---|
| `tipo_articulo` | `user_id` → `users` | catálogo; `grupo` CELULAR / EQUIPO / MATERIAL / OTRO decide los campos; 8 tipos iniciales |
| `evidencia` | `id_caso` → `caso` (el CUD), `id_division` → `division`, `user_id` → `users` | `codigo` opcional y único (lo escribe el usuario); funcionario responsable como texto; borrado lógico |
| `evidencia_articulo` | `evidencia_id`, `tipo_articulo_id`, `publicado_por` → `users` | IMEI y serie como texto, con índice; publicación individual; `codigo_publico` (ULID) para uso externo; borrado lógico |
| `evidencia_archivo` | `evidencia_id`, `evidencia_articulo_id` (opcional), `user_id` | archivos en el disco **privado**; `es_publico` falso por defecto; borrado lógico |

No se reutilizó `multimedia` (habría que alterar una tabla existente) ni se escribe en la tabla `imei` de las
investigaciones: un IMEI que coincida con ella se **avisa** en el detalle (sólo lectura). `evidencia_movimiento`
(cadena de custodia) queda para una etapa posterior: estas tablas no necesitan cambios para agregarla.

## Reglas

- **Campos según el grupo** del tipo (`ReglasEvidencia::GRUPOS`): el formulario muestra sólo los suyos y el servidor
  vacía los demás (un material nunca queda con un IMEI). Descripción obligatoria en MATERIAL y OTRO. IMEI de 14 a 16
  dígitos (se aceptan espacios y guiones al escribirlo); el formulario avisa si el dígito verificador no coincide.
- **Una sola transacción** por guardado (evidencia, artículos, archivos); si falla, se borran los archivos recién
  escritos. Quitar un artículo al editar lo elimina (lógico) con sus archivos.
- **Publicación:** nada se publica solo. «Solicitar publicación» → PENDIENTE_REVISION. Quien tiene `evidencias.publicar`
  aprueba (PUBLICADO, `publicado_at`, `publicado_por`), rechaza (NO_PUBLICAR, motivo obligatorio) o retira (RETIRADO,
  motivo). Si cambia un dato público de un artículo publicado (tipo, descripción, marca, modelo, color, IMEI, serie) o la
  fecha de la evidencia, vuelve a revisión. Anular o eliminar la evidencia retira sus publicaciones. Una acción desde un
  estado que ya cambió (otro usuario) no pisa nada: avisa.
- **Archivos:** disco privado `storage/app/private/evidencias/{id}/`, servidos por `evidencias.archivos.show`.
  Fotografías → WebP de 2000 px como máximo (se pierden los metadatos, como el GPS). Actas, comprobantes y documentos
  piden además `evidencias.documentos` y nunca pueden ser públicos; de las fotos, sólo la del artículo (no la del IMEI).
- **Límites:** 20 archivos por envío (PHP descarta en silencio los que pasan de `max_file_uploads`), fotos de 5 MB,
  documentos de 10 MB; la pantalla cuenta los archivos y el peso antes de enviar.
- Auditoría de cambios en los cuatro modelos; Auditoría de consultas `listado_evidencias` y `detalle_evidencia`.

## Permisos (sólo administrador; el superadmin tiene todo)

`evidencias.listar`, `ver`, `crear`, `editar`, `eliminar`, `documentos`, `publicar`, `tipos` — los crea `PermisosSeeder`
(sólo agrega). Menú «Evidencias»: Evidencias, Publicación, Tipos de Artículo (y «Registrar Evidencia» para quien registra
sin ver el listado).

## Pantallas

`Evidencias/Index` (filtros: IMEI o serie, CUD, código o acta, rango de fechas, tipo, estado, unidad, funcionario),
`Evidencias/Formulario` (procedimiento + artículos que se agregan, duplican o quitan; barra de guardado fija con el
contador de archivos), `Evidencias/Ver` (con coincidencias de IMEI o serie en otras evidencias y en IMEIs),
`Evidencias/Publicacion` (pestañas, «Lo que verá el ciudadano», fotos públicas, aprobar / rechazar / retirar) y
`Evidencias/TiposArticulo`. Componentes `ArticuloEvidencia` y `GaleriaArchivos`. `VisorImagenes` acepta además URLs
protegidas.

## Para el proyecto del portal (lee la base directamente)

Mostrar **sólo** artículos con `evidencia_articulo.estado_publicacion = 'PUBLICADO'` y `deleted_at IS NULL`, cuya
`evidencia` tenga `deleted_at IS NULL` y `estado <> 'ANULADA'`. Columnas que se pueden mostrar: `codigo_publico`, tipo
(`tipo_articulo.nombre`), `marca`, `modelo`, `color`, `evidencia.fecha_hora` (fecha de recuperación); buscar por `imei_1`,
`imei_2` o `numero_serie` exactos. Fotos: sólo `evidencia_archivo` con `es_publico = 1`, `tipo = 'FOTO_ARTICULO'` y
`deleted_at IS NULL` (el archivo está en el disco privado de DACI: `ruta` es relativa a `storage/app/private`). **Nunca**
`id_caso`/CUD, `lugar`, `detalle_lugar`, funcionarios, `numero_acta`, observaciones, ni otros archivos. Recomendado: un
usuario de MariaDB de sólo lectura limitado a esas columnas (o a una vista).

## Resultado

Pruebas: `tests/Feature/Evidencias/` (14) — 218 en verde; `npm run build` OK. Ensayo sobre una copia de `felcc1`
(borrada): sólo se crean las 4 tablas, las 37 existentes con suma de verificación idéntica, 8 permisos sólo al
administrador, vuelta atrás limpia. `MigracionCasosTest` y `MigracionAntecedentesTest` ajustadas (quitan y reponen
Evidencias, que apunta a `caso`). **No probado en el navegador** desde esta sesión. **No aplicado en `felcc1`.**
