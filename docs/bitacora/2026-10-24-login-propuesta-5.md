# 2026-10-24 · Login con la «propuesta 5»

**Hora:** 14:43 (Bolivia) · **Pedido:** rediseñar el login según la propuesta 5 (escritorio y celular) con la foto del
Palacio como fondo, sin romper la funcionalidad. Sólo en DACI.

## Qué se hizo

- Layout de acceso nuevo (`AuthLayout` + `resources/css/acceso.css`): foto de fondo oscurecida, encabezado
  «POLICÍA BOLIVIANA» con franja tricolor, marca «FELCC - DACI» a la derecha (escritorio), tarjeta de vidrio con el
  escudo oficial de la FELCC, campos con ícono, botón verde «Ingresar», «Acceso seguro» y el lema «Por una Bolivia
  más segura». En el celular el formulario va sin caja, sobre la foto centrada en el Palacio y la bandera.
- Mismo diseño para recuperar, restablecer y confirmar contraseña (`TarjetaAcceso`, `CampoAcceso`).
- Fondo: `public/felcc/img/login-fondo.webp` (la imagen enviada, sin el texto «BOLIVIA» incrustado, que chocaba con
  el encabezado). Tipografía de títulos: Barlow (`@fontsource/barlow` 5.3.0, local, sin Google).

## Funcionalidad conservada

Ingreso con **correo electrónico** (el rótulo dice «Correo electrónico» y no «Usuario», porque es lo que se escribe),
mostrar/ocultar contraseña, «Recordarme», «¿Olvidó su contraseña?», mensajes de estado y de cuenta desactivada,
errores de validación, autocompletado del navegador. Rótulos accesibles ocultos a la vista.

## Verificación

Capturas a 1920×1080, 1366×768, 390×844 y 360×640 · detector de diseño sin hallazgos · pruebas en verde.

## A confirmar

El texto «Dirección de Análisis de Información Criminal» es el de la propuesta; la ficha de registro del sistema
anterior dice «Departamento de Análisis Criminal e Inteligencia (D.A.C.I.)».

## Favicon (15:28)

El ícono del navegador pasa a ser el **escudo de la FELCC** (antes, el de la plantilla Velzon). Generado de
`public/felcc/img/felcc.png` sin el margen transparente: `public/favicon.ico` (16, 32, 48 y 64 px; el anterior
era un archivo vacío), `favicon-32.png`, `favicon-192.png` y `apple-touch-icon.png` (180 px, para el acceso
directo en celulares). Enlazados en `app.blade.php`; `public/assets/images/favicon.ico` de la plantilla queda sin uso.
