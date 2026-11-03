# 2026-11-01 · Casos: tabla `caso` y columna `id_caso` (D-037)

**Pedido:** a cada tabla con caso o CUD agregar `id_caso` (de la nueva tabla `caso`); registrar los casos por fases de
cada tabla (si existe, tomar su id; nulo o vacío → id nulo) con un seeder, con mucho cuidado de no borrar
información; recién cuando todo esté bien, eliminar las columnas sobrantes; propuesta con lo que pueda ocurrir para no
tener errores en producción. (Reemplaza la versión con tablas pivote del 2026-10-31, que el usuario revirtió con
`git stash`.)

## Qué se hizo

- **Fase 1** `2026_11_01_100000_crear_tabla_caso_y_agregar_id_caso`: tabla `caso` + `id_caso` nula con clave foránea
  en `telefono`, `registro_criminal`, `falta_contravencion`, `vehiculo`, `vehiculo_caso`. Comprueba antes (sólo
  lecturas) y se detiene sin cambios si algo no calza; funciona con `--pretend`; `down()` quita sólo lo agregado.
- **Fase 2** `App\Support\Casos\MigradorCasos` + `CasosSeeder` + `php artisan casos:migrar [--simular] [--tabla=]`:
  una transacción por tabla, sólo escribe `id_caso` donde está nulo, huella MD5 del resto de columnas antes y después.
- **Fase 4** `database/migrations/casos-fase-4/2026_11_01_200000_eliminar_columnas_de_texto_del_caso` (fuera de la
  carpeta que lee `migrate`): se niega si queda un caso sin `id_caso` o si el código usa la columna; respalda cada
  valor en `caso_texto_respaldo`; `down()` repone columnas y valores.
- Modelo `Caso`; relación `casoVinculado()` e `id_caso` en `$fillable` de `Telefono`, `RegistroCriminal`,
  `RegistroAntecedente`, `Vehiculo`, `VehiculoCaso` (la relación no se llama `caso()` porque `telefono` tiene esa columna
  y Eloquent devolvería el texto).
- Permisos `casos.listar/ver/crear/editar/eliminar` sólo para el administrador (`PermisosSeeder`).
- `PROPUESTA-CASOS.md` (fases, riesgos, procedimiento, ensayo, preguntas) y `DESPLIEGUE.md` § E.

## Resultado

Ensayo sobre una copia de `felcc1` (borrada): ver `PROPUESTA-CASOS.md` § 7 — huella de las 36 tablas idéntica en
cada paso salvo `id_caso`. Pruebas: `tests/Feature/Casos/` (13); `MigracionAntecedentesTest` ajustada (quita y repone
la fase 1 porque borra y recrea `falta_contravencion`). **No se aplicó en `felcc1`.**

## Pendiente

- Preguntas de `PROPUESTA-CASOS.md` § 8 (`AUTORIDADES`, relleno propio, fichas, fase 3).
- Fase 3 (pantallas y formularios por `id_caso`) y, semanas después, fase 4.

## 2026-11-02 · Módulo Casos (pantalla y menú)

**Pedido:** «no veo el módulo ni enlace de casos» (las fases 1 y 2 ya estaban aplicadas en la base local `felcc1` por
el usuario: 24 casos, 1.336 teléfonos y 4 registros con `id_caso`).

- Menú EREBOR › **Casos** (`casos.listar`). Rutas `casos.index/store/show/update/destroy` con sus permisos.
- `Casos/Index`: buscador (caso/CUD, descripción), filtro por estado, cantidad de teléfonos, registros, faltas y
  vehículos de cada caso (cada columna sólo si el usuario puede ver ese módulo), alta y edición en modal.
- `Casos/Ver`: datos del caso y todo lo vinculado por `id_caso` — teléfonos, registro criminal, faltas y
  contravenciones (con enlace a su ficha por código), vehículos y vínculos vehículo–persona —, cada sección sólo con el
  permiso de ver ese módulo; hasta 100 filas por sección (las más recientes) con el total.
- Alta/edición: el código se limpia como en la migración y se rechaza vacío, relleno («S/N», «-») o repetido (también
  eliminado). Editar el código lo corrige en todas las filas del caso.
- Eliminar: sólo un caso sin ninguna fila vinculada (también eliminadas); se borra del todo para poder reutilizar el código.
- Auditoría de consultas: `listado_casos` y `detalle_caso`.
- Pruebas: `tests/Feature/Casos/ModuloCasosTest.php` (6). 196 en verde; `npm run build` OK.

**Aún no (resto de la fase 3):** los formularios de teléfono, vehículo, registro criminal y faltas siguen guardando el
texto (`caso`, `cud`…) y no el `id_caso`; un registro nuevo aparece en Casos recién al correr `php artisan casos:migrar`.
