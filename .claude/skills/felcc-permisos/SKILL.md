---
name: felcc-permisos
description: Roles y permisos DINÁMICOS de FELCC/DACI con spatie/laravel-permission — catálogo único de permisos en `App\Support\Acceso\CatalogoPermisos` (slugs `modulo.accion`), roles editables desde Seguridad › Roles y permisos, protección de cada ruta con `permission:`, superadmin con acceso total por `Gate::before`, comando `acceso:sincronizar`, cómo se tradujeron los permisos del sistema anterior (`config/acl.php` + chequeos por nombre de rol) y cómo agregar un permiso nuevo sin romper el menú. Usa esta skill SIEMPRE antes de crear una ruta, un controlador o un botón que dependa de quién es el usuario, al tocar `routes/web.php`, `CatalogoPermisos`, `navegacion.js`, `usePermisos`, la pantalla de roles o los tests de autorización.
---

# Roles y permisos — FELCC / DACI

## Modelo

- **Permisos fijos** (los define el código): `App\Support\Acceso\CatalogoPermisos::modulos()`, agrupados por módulo
  con su etiqueta para la pantalla de roles. Slug `modulo.accion` en español: `personas.listar`, `personas.ver`,
  `personas.crear`, `personas.editar`, `personas.eliminar`, `personas.restaurar`, `consultas.mandamientos`,
  `importar.vehiculos`, `auditoria.cambios`…
- **Roles dinámicos**: se crean, editan (nombre, descripción, permisos) y eliminan en Seguridad › Roles y permisos
  (`roles.listar` para ver, `roles.gestionar` para cambiar). Los 6 roles base heredados (`superadmin`,
  `administrador`, `tecnico_felcc`, `consultor_felcc`, `tecnico_daci`, `consultor_daci`) no se pueden eliminar ni
  renombrar.
- **Un rol por usuario** (como el sistema anterior), asignado en Usuarios.
- **Superadmin**: `Gate::before` en `AppServiceProvider` le concede todo, también los permisos futuros. Su rol no
  se edita desde la pantalla, no aparece en el listado de usuarios ni en el selector de roles (igual que antes).
- Modelo de rol: `App\Models\Rol` (extiende el de Spatie, agrega `descripcion` y auditoría).

## En el servidor

```php
Route::get('/personas', [PersonaController::class, 'index'])
    ->name('personas.index')->middleware('permission:personas.listar');
Route::post('/telefonos-imeis-search', …)->middleware('permission:imeis.listar|imeis.crear|imeis.editar'); // cualquiera
```

- **Toda ruta del sistema** declara su permiso, salvo las que sólo exigen sesión: dashboard, perfil, catálogos de
  sólo lectura que alimentan formularios. `tests/Feature/AutorizacionRutasTest.php` falla si aparece una ruta nueva
  sin `permission:` que no esté en su lista de excepciones.
- Reglas finas dentro de una acción (p. ej. «no puedes eliminarte a ti mismo») van en el controlador.

## En pantalla

`usePermisos()` lee `auth.can` (compartido por `HandleInertiaRequests`; el superadmin recibe todo el catálogo):
`puede('x')`, `puedeAlguno([...])`, `cumple(regla)`. **Nunca** se compara el nombre del rol en Vue.
El menú (`Navegacion/navegacion.js`) usa el mismo slug que la ruta.

## Agregar un permiso

1. Sumarlo a `CatalogoPermisos::modulos()` con su etiqueta.
2. Protegerlo en la ruta y usarlo en `navegacion.js` / botones.
3. `php artisan acceso:sincronizar` — crea el permiso y se lo da a superadmin. **No** quita permisos a ningún rol.
4. Darlo a los demás roles desde la pantalla de roles (o, si debe venir en una instalación, en un seeder idempotente).
5. Tests: el de autorización de rutas y el del menú.

## Traducción del sistema anterior (no perder accesos)

La migración `2026_10_22_120000_migrar_roles_a_spatie_permission` guarda la tabla de equivalencias congelada:
cada permiso de `felcc/config/acl.php` (`personas_all`, `consulta_mandamientos`, …) concede exactamente los nuevos
que lista, y `ACCESOS_POR_ROL` convierte en permisos lo que el anterior decidía por **nombre de rol** (menú
`@canany`, botones `window.role`): auditorías para superadmin/administrador, ficha de registro e importaciones para
`tecnico_daci`, importar mandamientos para `tecnico_felcc`. La tabla `roles` antigua quedó como `roles_legado`
(con `users.role_id` apuntándola) como respaldo; `down()` revierte todo.

Chequeos heredados que eran «cualquiera de» se reproducen dando el permiso nuevo a quien tenía cualquiera de los
viejos (p. ej. ver el detalle de un mandamiento: `mandamientos_all | mandamientos_listar | consulta_mandamientos` →
`mandamientos.ver`).
