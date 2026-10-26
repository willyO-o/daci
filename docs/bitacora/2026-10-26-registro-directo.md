# 2026-10-26 · Registro directo de personas, teléfonos, IMEIs y vehículos

**Hora:** 17:55 – 18:42 (Bolivia) · **Pedido:** «necesito permisos adicionales solo para registrar elementos, algo
como en mandamientos… reutilizando formularios»; luego: «reusar, pero estos botones deben agregarse como tarjetas en
el dashboard para que sea más intuitivo».

## Qué se hizo (D-022)

- `create()` en `Persona`, `Telefono`, `Imei` y `VehiculoController` + rutas `*.create` (`*.crear`).
- Componentes de formulario extraídos de los listados; `ModalPersona` ahora envuelve `FormularioPersona`.
- Páginas `Personas|Telefonos|Imeis|Vehiculos/Registrar.vue` con `Components/Ui/PaginaRegistro.vue`.
- Menú (`navegacion.js`) y Dashboard (tarjetas «Registro rápido»).

## Resultado

130 pruebas en verde (14 nuevas) · Pint OK · `npm run build` OK. Sin cambios en la base ni permisos nuevos.

## Pendiente

- Prueba manual en el navegador: alta y edición desde cada listado (modal) y desde cada página «Registrar».
