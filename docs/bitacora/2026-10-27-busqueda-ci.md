# 2026-10-27 · Búsqueda de C.I. + complemento al registrar

**Hora:** 14:12 (Bolivia) · **Pedido:** al crear un registro criminal o una falta, buscar por C.I. + complemento si la
persona existe, con COALESCE.

## Qué se hizo (D-027)

- `Persona::scopeConCi` (COALESCE + TRIM) y `PersonaController::checkCI` reescrito con él (datos + conteo opcional).
- `Components/Personas/AvisoPersonaExistente.vue` en `RegistroCriminal/Formulario.vue` y `Antecedentes/Formulario.vue`.
- Altas de Registro Criminal y Faltas y Contravenciones: la persona se busca por C.I. + complemento.
- Filtro de C.I. del listado de Personas con COALESCE.

## Resultado

161 pruebas en verde (6 nuevas) · Pint OK · `npm run build` OK. Sólo código; la base no cambió.

## Pendiente

- Decidir si las importaciones de personas también deben distinguir el complemento.
