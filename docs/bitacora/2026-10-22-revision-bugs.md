# 2026-10-22 · Revisión de bugs y lightbox de fotos

**Hora:** 23:15 (Bolivia) · **Pedido:** «analízalo a detalle y verifica que no haya bugs»; «las imágenes de un
registro criminal se mostraban en lightbox, implementa lo mismo»; al final, enlazar a
`https://github.com/willyO-o/daci.git` (rama `main`) y hacer push.

## Qué se hizo

- Lectura completa de rutas, middleware, controladores, modelos, requests, comandos, migración de roles,
  composables y pantallas principales, cruzada con las FK y largos reales de `felcc1` y con los datos existentes
  (consultas de sólo lectura). Verificado en la base: 49 permisos = catálogo, 6 roles, ningún usuario sin rol.
- 10 bugs corregidos (detalle en `DECISIONES.md` D-017) con 10 pruebas de regresión.
- `VisorImagenes` reescrito sobre `fslightbox@3.4.0-2` (dependencia nueva, versión fijada = la del anterior).

## Archivos

`app/Models/{Persona,AuditarConsultas}.php` · `app/Http/Controllers/{Persona,Mandamiento,RegistroCriminal,Vehiculo,
Imei,Multimedia,Importacion}Controller.php` · `app/Http/Requests/{PersonaRequest,GuardarRegistroCriminalRequest}.php`
· `resources/js/Composables/useCargaInfinita.js` · `resources/js/Components/Ui/{VisorImagenes,BuscadorRemoto}.vue` ·
`resources/js/Components/Personas/ModalPersona.vue` · `resources/js/Pages/RegistroCriminal/Formulario.vue` ·
`resources/js/Pages/Importar/Personas.vue` · `tests/Feature/Revision/IntegridadDatosTest.php` · `package.json`.

## Resultado

96 pruebas / 560 aserciones en verde · Pint OK · `npm run build` OK.

## Pendiente

Lo de `ESTADO-ACTUAL.md` (prueba manual en navegador, decisiones abiertas, despliegue).
