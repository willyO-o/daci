# 2026-10-27 · Editar estado civil y ocupación desde el registro

**Hora:** 14:01 (Bolivia) · **Pedido:** al editar un registro criminal, poder cambiar estado civil y ocupación.

## Qué se hizo (D-026)

- `RegistroCriminalController::update` y `AntecedenteController::update`: validan y guardan `estado_civil` y
  `ocupacion` en la persona del registro.
- `RegistroCriminal/Formulario.vue` y `Antecedentes/Formulario.vue`: esos dos campos quedan habilitados al editar;
  el aviso dice que se actualizan ahí y que el resto se corrige en Personas.
- Pruebas en `RegistroCriminalTest` (2) y `AntecedentesTest` (1).

## Resultado

155 pruebas en verde · Pint OK · `npm run build` OK. Sin cambios en la base (sólo código).
