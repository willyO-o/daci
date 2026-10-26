# 2026-10-26 · Estado de ejecución desde Consultas › Mandamientos

**Hora:** 17:35 – 17:48 (Bolivia) · **Pedido:** botón con permiso que abre un modal para cambiar el estado de
ejecución; con «ejecutado» pide fecha, ejecutado por y acta; «sólo aplícalo en el módulo de consulta de
mandamientos».

## Qué se hizo (D-021)

- Permiso `mandamientos.estado` + `asignacionInicial` (administrador, técnicos FELCC y DACI).
- `ActualizarEstadoMandamientoRequest`, `MandamientoController::actualizarEstado`, ruta
  `consultas.mandamientos.estado`; la página de consulta recibe `estados` (sugerencias) sólo con el permiso.
- `ModalEstadoMandamiento.vue`; botón opcional en `TarjetaMandamiento`/`ListadoMandamientos` (apagado por
  defecto: el listado de Mandamientos no cambia); `esEjecutado()` en `Utils/mandamiento.js`.
- Detalle del mandamiento muestra «Detalle de ejecución» cuando existe.
- Base local `felcc`: `PermisosSeeder` creó el permiso (50) y lo asignó a los 3 roles.

## Resultado

116 pruebas en verde (7 nuevas) · Pint OK · `npm run build` OK.

## Pendiente

- Prueba manual en el navegador con un técnico y un consultor.
- En producción: `php artisan db:seed --class=PermisosSeeder --force` tras desplegar.
