---
name: felcc-frontend-vue
description: Convenciones OBLIGATORIAS del frontend de FELCC/DACI (Inertia v2 + Vue 3 + plantilla Velzon sobre Bootstrap 5, sin Tailwind ni jQuery) — layout persistente, estructura de páginas y componentes, menú lateral en `Navegacion/navegacion.js`, permisos en pantalla con `usePermisos()`, listados paginados en el servidor con `useListado()`, modales `BaseModal`, buscadores `BuscadorRemoto`, subida de archivos `SubirArchivos`, notificaciones con SweetAlert2 y cómo portar una pantalla del sistema anterior (Blade + jQuery) sin perder funcionalidad. Usa esta skill SIEMPRE antes de crear o editar cualquier archivo en `resources/js/**`, `resources/css/**` o `resources/views/app.blade.php`, y antes de migrar una vista de `felcc/resources/views` o un script de `felcc/public/assets/js`.
---

# Frontend FELCC / DACI — Vue + Inertia + Velzon

Convenciones tomadas del proyecto `tienda` (layout persistente, menú declarativo, permisos en pantalla) y adaptadas
a este sistema, que **conserva la apariencia del anterior**: la plantilla **Velzon** (HTML original en
`felcc/material`, CSS compilado en `public/assets/css`).

## 1. Estilo: Velzon tal cual, con oficio

- La interfaz se arma con **el marcado y las clases de Velzon/Bootstrap 5** (`card`, `page-title-box`,
  `btn-soft-*`, `badge bg-soft-*`, `avatar-*`, `table-nowrap align-middle`, `form-control`, íconos `ri-*`, `mdi-*`,
  `bx-*`). Antes de inventar un patrón, buscarlo en `felcc/material/*.html` y copiar su marcado.
- **No** hay Tailwind ni jQuery. Nada de plugins jQuery (select2, FilePond, DataTables): cada uno tiene su
  reemplazo Vue (§4).
- CSS propio sólo en `resources/css/app.css` (o `<style scoped>`), con las variables `--vz-*` de la plantilla.
- Criterio de calidad (impeccable / taste-skill aplicado a pantallas de trabajo): todo listado tiene estado de
  **carga** (`FilasCargando`), **vacío** (`EstadoVacio` que dice qué pasó) y **error** dentro del bloque; cifras con
  `cifra`/clase `.cifra` (tabulares); badges suaves (`bg-soft-*`) para estados; sin gradientes nuevos, sin emojis,
  sin datos o métricas inventadas; textos de botones que nombran la acción («Guardar», «Vincular»); confirmación que
  nombra el objeto antes de borrar.
- Textos de la interfaz en **español**, con los mismos rótulos que el sistema anterior (los usuarios ya los conocen).

## 2. Estructura

```
resources/js/
  app.js                       → crea la app; asigna el layout PERSISTENTE (AppLayout, o AuthLayout para Auth/*)
  Layouts/AppLayout.vue        → topbar + sidebar + footer; muestra los flash como toast
  Layouts/AuthLayout.vue       → fondo y tarjeta de las pantallas de acceso
  Navegacion/navegacion.js     → ÚNICA definición del menú lateral
  Composables/                 → usePermisos, useListado (paginado Inertia), useCargaInfinita (scroll infinito JSON),
                                 useFormularioJson (envío multipart por axios), useNotificacion, useTema, useVistaGuardada
  Components/Layout/           → AppTopbar, AppSidebar, AppFooter, PageHeader
  Components/Ui/               → BaseModal, Paginacion, EstadoVacio, FilasCargando, CampoError, Avatar,
                                 BuscadorRemoto, SelectorCatalogo (con «añadir nuevo»), SubirArchivos, RangoFechas,
                                 VisorImagenes, ListaInfinita
  Components/{Modulo}/         → piezas de un módulo (formularios, detalles) reutilizadas por sus páginas
  Pages/{Modulo}/{Accion}.vue  → una página por pantalla: Index, Formulario, Consultas, Importar, Ver…
  Utils/formato.js             → fechas es-BO, hoyIso, nombre completo, CI, urlArchivo, cifra, monto
  Utils/http.js                → instancia axios para JSON (no tocar los valores globales de axios: los usa Inertia)
  Utils/directivas.js          → v-mayuscula, v-solo-numeros, v-sin-espacios, v-texto-normal, v-texto-descripcion, v-decimal
  Utils/uuid.js                → identificador de consulta (crypto.randomUUID falla por HTTP fuera de localhost)
```

- Las páginas **no** declaran layout: lo pone `app.js`. Una página sin layout usa `defineOptions({ layout: null })`.
- Cada página empieza con `<PageHeader titulo="…" :migas="[…]">` (título del navegador incluido) y, si tiene,
  `<template #acciones>` con sus botones principales.
- Componentes con un único elemento raíz.

## 3. Menú y permisos

- El sidebar se define **sólo** en `Navegacion/navegacion.js`: `{ etiqueta, icono, ruta, activo?, permiso?, hijos? }`.
  `permiso` es el MISMO slug que exige la ruta en el servidor (`App\Support\Acceso\CatalogoPermisos`), así un ítem
  visible nunca da 403. Una sección sin ítems visibles se oculta sola.
- En pantalla: `const { puede, puedeAlguno } = usePermisos();` → `v-if="puede('personas.editar')"`. Es sólo
  presentación; la autorización real es la ruta (`->middleware('permission:…')`). **Nunca** comparar nombres de rol
  en el frontend (el anterior usaba `window.role`; eso se reemplazó por permisos).
- Pantalla nueva = ruta con permiso + entrada en `navegacion.js` (si va al menú) + permiso en el catálogo.

## 4. Patrones

**Listado** (reemplaza `cargarX(page)` + `$.ajax` + `renderPaginacion`):

```js
const props = defineProps({ personas: Object, filtros: Object });
const { filtros, cargando, irAPagina } = useListado('personas.index', props.filtros, ['personas']);
```
```html
<input v-model="filtros.search" type="search" class="form-control">   <!-- espera 400 ms -->
<select v-model="filtros.genero" class="form-select">…</select>       <!-- filtra al instante -->
<tbody><FilasCargando v-if="cargando" :columnas="10" /> <tr v-for=…> </tbody>
<EstadoVacio v-if="!cargando && !personas.data.length" titulo="No se encontraron personas" />
<Paginacion :paginado="personas" entidad="personas" @ir="irAPagina" />
```
El controlador devuelve `Inertia::render('Personas/Index', ['personas' => $q->paginate($tam)->withQueryString(),
'filtros' => $request->only([...])])`.

**Formulario en modal** (reemplaza el HTML cargado por AJAX en `#modalX`):
`useForm()` + `BaseModal v-model estatico` + `form.post(route(...), { forceFormData: true, onSuccess: cerrar })`.
El servidor responde `back()->with('success', '…')`; el toast lo muestra `AppLayout`. Errores de validación en
`form.errors` con `CampoError`. «Guardar y registrar nuevo» = `onSuccess: () => form.reset()` sin cerrar.
Para editar con archivos: `form.transform(d => ({ ...d, _method: 'put' })).post(...)`.

**Detalle en modal** (reemplaza el `show` que devolvía HTML): el controlador responde JSON si
`$request->wantsJson()`; la página lo pide con `axios.get(route('x.show', id))` y lo pinta con un componente
`Components/{Modulo}/Detalle*.vue`, el mismo que usa la página por código (`showByCodigo`).

**Selects con búsqueda** (reemplaza select2): `BuscadorRemoto` (v-model = id, `mapear` para el texto).
Catálogos cortos (países, divisiones, juzgados…) llegan como prop y van en un `<select class="form-select">`.

**Listados con scroll infinito** (mandamientos, registro criminal, como el anterior): el controlador responde JSON
`{ datos, total, page, last_page }` cuando `wantsJson()`; la página usa `useCargaInfinita` + `ListaInfinita`.

**Archivos** (reemplaza FilePond): `SubirArchivos v-model="form.fotos" :maximo :tamano-maximo-mb acepta`.

**Confirmaciones y mensajes** (reemplaza `confirmarEnvio`, `notification`, `processError`):
`const { confirmar, exito, errorDeRespuesta } = useNotificacion();` →
`if (await confirmar({ titulo: '¿Eliminar a Juan Pérez?', peligro: true })) router.delete(...)`.

**Peticiones JSON** con `axios` (ya configurado en `app.js`: `X-Requested-With` + XSRF). Para navegar o enviar
formularios, siempre Inertia (`router`, `useForm`, `<Link>`), nunca `window.location`.

## 5. Al portar una pantalla del sistema anterior

1. Leer completos la vista Blade, sus parciales y su script en `felcc/public/assets/js/{modulo}/`.
2. Anotar en `docs/bitacora/MATRIZ-PARIDAD.md` cada ruta, filtro, columna, botón y regla de visibilidad.
3. Botones que antes dependían de `window.role`/`@canany(roles)` → el permiso equivalente (ver la traducción en la
   migración de roles). Si no hay equivalente, agregarlo al catálogo y a la migración/sincronización, nunca por rol.
4. Mantener rótulos, columnas, filtros, orden por defecto y tamaño de página (10) del sistema anterior.
5. Registrar en la bitácora lo migrado, lo corregido (bugs heredados) y lo que queda pendiente.
