# 2026-10-26 · Módulo Faltas y Contravenciones

**Hora:** 19:00 – 20:35 (Bolivia) · **Pedido:** módulo «Faltas y Contravenciones» igual a Registro Criminal con sus
permisos, con su propia tabla y los mismos campos, sin afectar las otras tablas.

## Qué se hizo (D-023)

- Migración que sólo crea 4 tablas nuevas (`falta_contravencion`, `foto_falta_contravencion`,
  `ficha_falta_contravencion`, `ficha_falta_contravencion_plantilla`) y siembra la plantilla de la ficha.
- Modelos `RegistroAntecedente`/`FotoAntecedente`/`FichaAntecedente`/`PlantillaAntecedente` (bases) y
  `FaltaContravencion`, `FotoFaltaContravencion`, `FichaFaltaContravencion`, `FichaFaltaContravencionPlantilla`.
- `App\Support\Antecedentes\ModuloAntecedente`, `AntecedenteController` + `FaltaContravencionController`,
  `GuardarAntecedenteRequest`, rutas `faltas-contravenciones.*` y `consultas.faltas-contravenciones`.
- Páginas `Antecedentes/{Index,Formulario,Ver}` y `Consultas/Antecedentes` (desde las de Registro Criminal).
- Menú (EREBOR y Consultas), permisos, auditoría de cambios y de consultas, eliminar/unir personas.

## Corrección

Se había hecho como dos módulos (Faltas, Contravenciones). Aclarado por el usuario: es uno solo. En la base local se
revirtió la versión anterior (tablas vacías), se quitaron sus 14 permisos y se aplicó la definitiva.

## Resultado

144 pruebas en verde · Pint OK · `npm run build` OK.

**Aplicado en la base local `felcc` (20:35):** `migrate` creó las 4 tablas y `PermisosSeeder` agregó 7 permisos
(57 en total) con su asignación inicial. Antes y después: suma de verificación idéntica en las 30 tablas existentes
comparadas y ninguna tabla desaparecida. La base local no tiene plantilla de Registro Criminal: la ficha tomó el
texto de `PlantillaFichaSeeder`.

## Pendiente

- En producción: `php artisan migrate` y `php artisan db:seed --class=PermisosSeeder --force`.
- Prueba manual en el navegador (alta con fotos, edición, ficha/PDF, consulta, eliminar).
- Revisar el texto de la plantilla de ficha (copiado de Registro Criminal: habla de «aprehendido» y «presunto delito»).
