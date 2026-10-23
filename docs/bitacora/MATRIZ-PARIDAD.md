# Matriz de paridad FELCC → DACI

Cada ruta del sistema anterior (`felcc/routes/web.php`) con su equivalente en DACI. Las URL y los nombres de ruta se
conservaron para que los enlaces guardados sigan funcionando.

Leyenda: ✅ migrado igual · 🔧 migrado con corrección · ➖ no se migra (con motivo).

## Acceso y general

| Ruta anterior | Nombre | DACI | Permiso | Estado |
|---|---|---|---|---|
| `GET /` | — | redirige a `/login` | — | ✅ |
| `GET /login`, `POST /login`, `POST /logout` | `login`, `logout` | Breeze + `Auth/Login.vue` | — | 🔧 rechaza usuarios inactivos en el login (D-008) |
| `password/*` (reset, email, confirm) | `password.*` | Breeze (`forgot-password`, `reset-password`, `confirm-password`) | — | ✅ (URL de Breeze) |
| `GET /home` | `home` | redirige a `dashboard` | sesión | ✅ |
| `GET /dashboard` | `dashboard` | `Dashboard/Index` | sesión | 🔧 enlaces y «Nuevo Mandamiento» sólo si hay permiso |
| `GET /perfil`, `POST /perfil/password` | `perfil`, `perfil.password` | `Perfil/Index` | sesión | ✅ |
| `GET /velzon/{file}` | — | — | — | ➖ páginas de demostración de la plantilla |
| `GET /eliminar-duplicados` | `eliminar-duplicados.index` | comando `php artisan personas:eliminar-duplicados` | consola | 🔧 ya no es una URL web (D-012) |

## Seguridad

| Ruta anterior | Nombre | DACI | Permiso | Estado |
|---|---|---|---|---|
| `GET /usuarios` | `usuarios.index` | `Usuarios/Index` | `usuarios.listar` | ✅ |
| `POST /usuarios`, `PUT /usuarios/{id}`, `DELETE /usuarios/{id}` | `usuarios.store/update/destroy` | modal del listado | `usuarios.crear/editar/eliminar` | ✅ |
| `POST /usuarios/{id}/toggle-status` | `usuarios.toggle-status` | botón del listado | `usuarios.activar` | ✅ |
| `GET /usuarios/create`, `/{id}/edit`, `/{id}` | — | (el modal ya trae los datos) | — | ➖ no hacen falta |
| — | `roles.*` | `Roles/Index` (nuevo: roles dinámicos) | `roles.listar`, `roles.gestionar` | ✅ nuevo |
| `GET /auditar-consultas` + `/{id}` + `/filtros/modulos`, `/filtros/roles` | `auditar-consultas.*` | `AuditarConsultas/Index` (los filtros llegan como props) | `auditoria.consultas` | 🔧 accesos sin HTML crudo (D-013) |
| `GET /auditorias` + `/{id}` + `-filtros/modelos`, `-filtros/eventos` | `auditorias.*` | `Auditorias/Index` | `auditoria.cambios` | ✅ |

## Mandamientos y consultas

| Ruta anterior | Nombre | DACI | Permiso | Estado |
|---|---|---|---|---|
| `GET /mandamientos` (vista + AJAX) | `mandamientos.index` | `Mandamientos/Index` (scroll infinito, grid/lista) | `mandamientos.listar` | ✅ |
| `GET /mandamientos/create` | `mandamientos.create` | `Mandamientos/Registrar` | `mandamientos.crear` | ✅ |
| `POST /mandamientos` | `mandamientos.store` | JSON | `mandamientos.crear` | ✅ |
| `GET /mandamientos/{id}` | `mandamientos.show` | JSON para el modal | `mandamientos.ver` | ✅ |
| `GET /mandamientos/{id}/edit` | `mandamientos.edit` | (la tarjeta ya trae los datos) | — | ➖ |
| `PUT /mandamientos/{id}`, `DELETE` | `mandamientos.update/destroy` | JSON | `mandamientos.editar/eliminar` | ✅ |
| `GET /mandamiento/{codigo}` | `mandamientos.showByCodigo` | `Mandamientos/Ver` | `mandamientos.ver` | ✅ |
| `GET /consultas/mandamientos` | `consultas.mandamientos` | `Consultas/Mandamientos` | `consultas.mandamientos` | 🔧 filtro «encargado» corregido (decía «encargardo») |
| `GET /consultas/registro-criminal` | `consultas.registro-criminal` | `Consultas/RegistroCriminal` | `consultas.registro_criminal` | ✅ |
| `resource tipos-mandamientos`, `juzgados`, `delitos`, `paises`, `divisiones` (index + store) | `*.index`, `*.store` | JSON (los demás métodos estaban vacíos) | listar: sesión; alta: permiso del módulo que la usa | 🔧 alta protegida |
| `DELETE /multimedia/{id}` | `multimedia.destroy` | JSON | `personas/mandamientos/vehiculos.editar` | 🔧 antes sin control |

## EREBOR

| Ruta anterior | Nombre | DACI | Permiso | Estado |
|---|---|---|---|---|
| `resource personas` (index, store, show, update, destroy) | `personas.*` | `Personas/Index` + modales, `Personas/Ver` | `personas.listar/crear/ver/editar/eliminar` | 🔧 filtro CI agrupado; migración fallida ya no borra (D-011) |
| `PATCH /personas/{id}` sólo con `url_documento` | (dentro de update) | `PATCH /personas/{id}/documento` `personas.documento` | `personas.editar` | ✅ |
| `GET /personas/{id}/delete-modal` | `personas.showDeleteModal` | JSON | `personas.eliminar` | ✅ |
| `POST /personas-restore/{id}` | `personas.restore` | Inertia | `personas.restaurar` | ✅ |
| `GET /personas-search`, `GET /personas-check-ci` | `personas.search`, `personas.check-ci` | JSON | sesión | ✅ |
| `resource registro-criminal` | `registro-criminal.*` | `RegistroCriminal/Index`, `Formulario`, modal | `registro_criminal.*` | ✅ |
| `GET /registro/{codigo}` | `registro-criminal.showByCodigo` | `RegistroCriminal/Ver` | `registro_criminal.ver` | ✅ |
| `GET /registro-vista-previa/{id}` | `registro.vista-previa` | JSON + `FichaRegistro.vue` | `registro_criminal.ficha` | 🔧 la división ya no sale vacía |
| `POST /ficha-registro`, `GET /ficha-registro/pdf/{codigo}` | `ficha-registro.store/pdf` | JSON / PDF | `registro_criminal.ficha` | 🔧 texto sin restos de HTML |
| `resource telefonos` | `telefonos.*` | `Telefonos/Index` | `telefonos.*` | ✅ |
| `POST /telefonos/{id}/vincular-persona` | `telefonos.vincular-persona` | Inertia | `telefonos.editar` | ✅ |
| `POST /telefonos/{id}/agregar-imei` | `telefonos.agregar-imei` | — | — | ➖ código muerto: columna `imeis_asociados` inexistente, botón oculto |
| `GET /telefonos-imeis-search` | `imeis.telefonos-search` | JSON (máx. 30) | `imeis.listar|crear|editar` | 🔧 acotado |
| `resource imeis`, `POST /imeis-vincular-telefono/{id}` | `imeis.*` | `Imeis/Index` | `imeis.*` | ✅ |
| `resource vehiculos` | `vehiculos.*` | `Vehiculos/Index` + `DetalleVehiculo` | `vehiculos.*` | 🔧 el caso de la persona se guarda; fotos no se pisan (D-014) |
| `POST /vehiculos/{id}/vincular-persona`, `DELETE …/desvincular-persona/{persona}` | `vehiculos.vincular/desvincular-persona` | Inertia | `vehiculos.vincular` | ✅ |

## Importaciones y reportes

| Ruta anterior | Nombre | DACI | Permiso | Estado |
|---|---|---|---|---|
| `GET/POST /mandamientos/importar` | `importar.mandamientos.*` | `Importar/Mandamientos` | `importar.mandamientos` | ✅ |
| `GET/POST /personas/importar`, `GET …/plantilla` | `personas.importar.*` | `Importar/Personas` (oculto en el menú, como antes) | `importar.personas` | 🔧 detecta el delimitador |
| `GET/POST /telefonos/importar`, `POST /imeis/importar` | `telefonos.importar.*`, `imeis.importar.store` | `Importar/Telefonos` (pestañas) | `importar.telefonos` | 🔧 ignora filas sin número |
| `GET/POST /vehiculos/importar`, `POST …/carguios/importar`, `POST …/inspecciones/importar` | `vehiculos.*.importar.*` | `Importar/Vehiculos` (pestañas) | `importar.vehiculos` | ✅ |
| `GET /reportes` | `reportes.index` | `Reportes/Index` | `reportes.exportar` | 🔧 sólo muestra lo que funciona (CSV) |
| `GET /reportes/exportar` | `reportes.exportar` | CSV en streaming | `reportes.exportar` | 🔧 «Registro criminal» y «Personas» fallaban en el anterior |
| `GET /reportes/formulario/{tipo}` | `reportes.formulario` | — | — | ➖ sin enlace y sin filtros implementados en el anterior |

## Comandos

| Anterior | DACI | Estado |
|---|---|---|
| `db:backup` (programado 00:00) | igual, en `routes/console.php` | ✅ |
| `backup:verify` | igual | ✅ |
| — | `acceso:sincronizar` | nuevo |
| — | `personas:eliminar-duplicados` | 🔧 reemplaza la URL |

## Comportamientos de pantalla revisados (2026-10-22 23:15)

| Anterior | DACI | Estado |
|---|---|---|
| Clic en la foto de una tarjeta (registro criminal, mandamientos, consultas) → FsLightbox 3.4.0-2 | `VisorImagenes` sobre la misma librería y versión | ✅ misma apariencia |
| Eliminar completo una persona / eliminar un registro criminal | `personas.destroy` · `registro-criminal.destroy` | 🔧 ya no fallan por claves foráneas (D-017) |
| Editar vehículo / IMEI | `vehiculos.update` · `imeis.update` | 🔧 conservan vínculos con personas / teléfonos eliminados (D-017) |

## Roles en la base (2026-10-23 11:05)

| Anterior | DACI | Estado |
|---|---|---|
| `users.role_id` → `roles` | Spatie (`model_has_roles`) + `users.role_id` → `roles_legado` en espejo (`User::asignarRol`) | 🔧 rollback a FELCC conserva el rol (D-018) |
| — | `acceso:sincronizar` repara `users.role_id` | nuevo |
