<?php

use App\Http\Controllers\AuditarConsultasController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DelitoController;
use App\Http\Controllers\DivisionController;
use App\Http\Controllers\FichaRegistroController;
use App\Http\Controllers\ImeiController;
use App\Http\Controllers\ImportacionController;
use App\Http\Controllers\JuzgadoController;
use App\Http\Controllers\MandamientoController;
use App\Http\Controllers\MultimediaController;
use App\Http\Controllers\PaisController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\PersonaController;
use App\Http\Controllers\RegistroCriminalController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\TelefonoController;
use App\Http\Controllers\TipoMandamientoController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehiculoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas web
|--------------------------------------------------------------------------
|
| Mismas URL y nombres de ruta que el sistema anterior (FELCC), para que los enlaces guardados sigan funcionando.
| Cada ruta exige su permiso con `permission:` (Spatie); los slugs están en App\Support\Acceso\CatalogoPermisos.
| Una ruta sin permiso sólo exige sesión (dashboard, perfil, catálogos para los formularios).
|
*/

Route::redirect('/', '/login');

Route::middleware('auth')->group(function () {
    Route::get('/home', fn () => redirect()->route('dashboard'))->name('home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/perfil', [PerfilController::class, 'index'])->name('perfil');
    Route::post('/perfil/password', [PerfilController::class, 'actualizarClave'])->name('perfil.password');

    // Catálogos de los formularios: listar sólo exige sesión; agregar un valor al vuelo, poder registrar o editar
    // el módulo que lo usa (en el sistema anterior no tenían control alguno).
    Route::get('/juzgados', [JuzgadoController::class, 'index'])->name('juzgados.index');
    Route::post('/juzgados', [JuzgadoController::class, 'store'])->name('juzgados.store')->middleware('permission:mandamientos.crear|mandamientos.editar');
    Route::get('/delitos', [DelitoController::class, 'index'])->name('delitos.index');
    Route::post('/delitos', [DelitoController::class, 'store'])->name('delitos.store')->middleware('permission:mandamientos.crear|mandamientos.editar');
    Route::get('/tipos-mandamientos', [TipoMandamientoController::class, 'index'])->name('tipos-mandamientos.index');
    Route::post('/tipos-mandamientos', [TipoMandamientoController::class, 'store'])->name('tipos-mandamientos.store')->middleware('permission:mandamientos.crear|mandamientos.editar');
    Route::get('/divisiones', [DivisionController::class, 'index'])->name('divisiones.index');
    Route::post('/divisiones', [DivisionController::class, 'store'])->name('divisiones.store')->middleware('permission:registro_criminal.crear|registro_criminal.editar');
    Route::get('/paises', [PaisController::class, 'index'])->name('paises.index');

    // Mandamientos
    Route::get('/mandamientos', [MandamientoController::class, 'index'])->name('mandamientos.index')->middleware('permission:mandamientos.listar');
    Route::get('/mandamientos/create', [MandamientoController::class, 'create'])->name('mandamientos.create')->middleware('permission:mandamientos.crear');
    Route::post('/mandamientos', [MandamientoController::class, 'store'])->name('mandamientos.store')->middleware('permission:mandamientos.crear');
    Route::get('/mandamientos/{mandamiento}', [MandamientoController::class, 'show'])->whereNumber('mandamiento')->name('mandamientos.show')->middleware('permission:mandamientos.ver');
    Route::match(['put', 'patch'], '/mandamientos/{mandamiento}', [MandamientoController::class, 'update'])->whereNumber('mandamiento')->name('mandamientos.update')->middleware('permission:mandamientos.editar');
    Route::delete('/mandamientos/{mandamiento}', [MandamientoController::class, 'destroy'])->whereNumber('mandamiento')->name('mandamientos.destroy')->middleware('permission:mandamientos.eliminar');
    Route::get('/mandamiento/{codigo}', [MandamientoController::class, 'showByCodigo'])->name('mandamientos.showByCodigo')->middleware('permission:mandamientos.ver');

    // Consultas
    Route::get('/consultas/mandamientos', [MandamientoController::class, 'consultarMandamientos'])->name('consultas.mandamientos')->middleware('permission:consultas.mandamientos');

    Route::get('/consultas/registro-criminal', [RegistroCriminalController::class, 'consultarRegistroCriminal'])->name('consultas.registro-criminal')->middleware('permission:consultas.registro_criminal');

    // EREBOR › Registro criminal
    Route::get('/registro-criminal', [RegistroCriminalController::class, 'index'])->name('registro-criminal.index')->middleware('permission:registro_criminal.listar');
    Route::get('/registro-criminal/create', [RegistroCriminalController::class, 'create'])->name('registro-criminal.create')->middleware('permission:registro_criminal.crear');
    Route::post('/registro-criminal', [RegistroCriminalController::class, 'store'])->name('registro-criminal.store')->middleware('permission:registro_criminal.crear');
    Route::get('/registro-criminal/{registroCriminal}', [RegistroCriminalController::class, 'show'])->whereNumber('registroCriminal')->name('registro-criminal.show')->middleware('permission:registro_criminal.ver');
    Route::get('/registro-criminal/{registroCriminal}/edit', [RegistroCriminalController::class, 'edit'])->whereNumber('registroCriminal')->name('registro-criminal.edit')->middleware('permission:registro_criminal.editar');
    Route::match(['put', 'patch'], '/registro-criminal/{registroCriminal}', [RegistroCriminalController::class, 'update'])->whereNumber('registroCriminal')->name('registro-criminal.update')->middleware('permission:registro_criminal.editar');
    Route::delete('/registro-criminal/{registroCriminal}', [RegistroCriminalController::class, 'destroy'])->whereNumber('registroCriminal')->name('registro-criminal.destroy')->middleware('permission:registro_criminal.eliminar');
    Route::get('/registro/{codigo}', [RegistroCriminalController::class, 'showByCodigo'])->name('registro-criminal.showByCodigo')->middleware('permission:registro_criminal.ver');
    Route::get('/registro-vista-previa/{registroCriminal}', [RegistroCriminalController::class, 'vistaPrevia'])->whereNumber('registroCriminal')->name('registro.vista-previa')->middleware('permission:registro_criminal.ficha');
    Route::post('/ficha-registro', [FichaRegistroController::class, 'store'])->name('ficha-registro.store')->middleware('permission:registro_criminal.ficha');
    Route::get('/ficha-registro/pdf/{codigo}', [FichaRegistroController::class, 'pdf'])->name('ficha-registro.pdf')->middleware('permission:registro_criminal.ficha');

    // EREBOR › Personas
    Route::get('/personas', [PersonaController::class, 'index'])->name('personas.index')->middleware('permission:personas.listar');
    Route::post('/personas', [PersonaController::class, 'store'])->name('personas.store')->middleware('permission:personas.crear');
    Route::get('/personas/{persona}', [PersonaController::class, 'show'])->whereNumber('persona')->name('personas.show')->middleware('permission:personas.ver');
    Route::match(['put', 'patch'], '/personas/{persona}', [PersonaController::class, 'update'])->whereNumber('persona')->name('personas.update')->middleware('permission:personas.editar');
    Route::patch('/personas/{persona}/documento', [PersonaController::class, 'vincularDocumento'])->whereNumber('persona')->name('personas.documento')->middleware('permission:personas.editar');
    Route::delete('/personas/{persona}', [PersonaController::class, 'destroy'])->whereNumber('persona')->name('personas.destroy')->middleware('permission:personas.eliminar');
    Route::get('/personas/{persona}/delete-modal', [PersonaController::class, 'showDeleteModal'])->whereNumber('persona')->name('personas.showDeleteModal')->middleware('permission:personas.eliminar');
    Route::post('/personas-restore/{persona}', [PersonaController::class, 'restore'])->whereNumber('persona')->name('personas.restore')->middleware('permission:personas.restaurar');
    // Buscadores de los formularios (en el sistema anterior sólo exigían sesión).
    Route::get('/personas-search', [PersonaController::class, 'search'])->name('personas.search');
    Route::get('/personas-check-ci', [PersonaController::class, 'checkCI'])->name('personas.check-ci');

    // EREBOR › Teléfonos
    Route::get('/telefonos', [TelefonoController::class, 'index'])->name('telefonos.index')->middleware('permission:telefonos.listar');
    Route::post('/telefonos', [TelefonoController::class, 'store'])->name('telefonos.store')->middleware('permission:telefonos.crear');
    Route::get('/telefonos/{telefono}', [TelefonoController::class, 'show'])->whereNumber('telefono')->name('telefonos.show')->middleware('permission:telefonos.ver');
    Route::match(['put', 'patch'], '/telefonos/{telefono}', [TelefonoController::class, 'update'])->whereNumber('telefono')->name('telefonos.update')->middleware('permission:telefonos.editar');
    Route::delete('/telefonos/{telefono}', [TelefonoController::class, 'destroy'])->whereNumber('telefono')->name('telefonos.destroy')->middleware('permission:telefonos.eliminar');
    Route::post('/telefonos/{telefono}/vincular-persona', [TelefonoController::class, 'vincularPersona'])->whereNumber('telefono')->name('telefonos.vincular-persona')->middleware('permission:telefonos.editar');
    Route::get('/telefonos-imeis-search', [TelefonoController::class, 'searchTelefonos'])->name('imeis.telefonos-search')->middleware('permission:imeis.listar|imeis.crear|imeis.editar');

    // EREBOR › IMEIs
    Route::get('/imeis', [ImeiController::class, 'index'])->name('imeis.index')->middleware('permission:imeis.listar');
    Route::post('/imeis', [ImeiController::class, 'store'])->name('imeis.store')->middleware('permission:imeis.crear');
    Route::get('/imeis/{imei}', [ImeiController::class, 'show'])->whereNumber('imei')->name('imeis.show')->middleware('permission:imeis.ver');
    Route::match(['put', 'patch'], '/imeis/{imei}', [ImeiController::class, 'update'])->whereNumber('imei')->name('imeis.update')->middleware('permission:imeis.editar');
    Route::delete('/imeis/{imei}', [ImeiController::class, 'destroy'])->whereNumber('imei')->name('imeis.destroy')->middleware('permission:imeis.eliminar');
    Route::post('/imeis-vincular-telefono/{imei}', [ImeiController::class, 'vincularTelefono'])->whereNumber('imei')->name('imeis.vincular-telefono')->middleware('permission:imeis.crear');

    // EREBOR › Vehículos
    Route::get('/vehiculos', [VehiculoController::class, 'index'])->name('vehiculos.index')->middleware('permission:vehiculos.listar');
    Route::post('/vehiculos', [VehiculoController::class, 'store'])->name('vehiculos.store')->middleware('permission:vehiculos.crear');
    Route::get('/vehiculos/{vehiculo}', [VehiculoController::class, 'show'])->whereNumber('vehiculo')->name('vehiculos.show')->middleware('permission:vehiculos.ver');
    Route::match(['put', 'patch'], '/vehiculos/{vehiculo}', [VehiculoController::class, 'update'])->whereNumber('vehiculo')->name('vehiculos.update')->middleware('permission:vehiculos.editar');
    Route::delete('/vehiculos/{vehiculo}', [VehiculoController::class, 'destroy'])->whereNumber('vehiculo')->name('vehiculos.destroy')->middleware('permission:vehiculos.eliminar');
    Route::post('/vehiculos/{vehiculo}/vincular-persona', [VehiculoController::class, 'vincularPersona'])->whereNumber('vehiculo')->name('vehiculos.vincular-persona')->middleware('permission:vehiculos.vincular');
    Route::delete('/vehiculos/{vehiculo}/desvincular-persona/{persona}', [VehiculoController::class, 'desvincularPersona'])->whereNumber(['vehiculo', 'persona'])->name('vehiculos.desvincular-persona')->middleware('permission:vehiculos.vincular');

    // Importaciones (CSV / Excel)
    Route::get('/mandamientos/importar', [ImportacionController::class, 'indexMandamientos'])->name('importar.mandamientos.index')->middleware('permission:importar.mandamientos');
    Route::post('/mandamientos/importar', [ImportacionController::class, 'storeMandamientos'])->name('importar.mandamientos.importar')->middleware('permission:importar.mandamientos');
    Route::get('/personas/importar', [ImportacionController::class, 'indexPersonas'])->name('personas.importar.index')->middleware('permission:importar.personas');
    Route::post('/personas/importar', [ImportacionController::class, 'storePersonas'])->name('personas.importar.store')->middleware('permission:importar.personas');
    Route::get('/personas/importar/plantilla', [ImportacionController::class, 'plantillaPersonas'])->name('personas.importar.plantilla')->middleware('permission:importar.personas');
    Route::get('/telefonos/importar', [ImportacionController::class, 'indexTelefonos'])->name('telefonos.importar.index')->middleware('permission:importar.telefonos');
    Route::post('/telefonos/importar', [ImportacionController::class, 'storeTelefonos'])->name('telefonos.importar.store')->middleware('permission:importar.telefonos');
    Route::post('/imeis/importar', [ImportacionController::class, 'storeImeis'])->name('imeis.importar.store')->middleware('permission:importar.telefonos');
    Route::get('/vehiculos/importar', [ImportacionController::class, 'indexVehiculos'])->name('vehiculos.importar.index')->middleware('permission:importar.vehiculos');
    Route::post('/vehiculos/importar', [ImportacionController::class, 'storeVehiculos'])->name('vehiculos.importar.store')->middleware('permission:importar.vehiculos');
    Route::post('/vehiculos/carguios/importar', [ImportacionController::class, 'storeCargios'])->name('vehiculos.carguios.importar.store')->middleware('permission:importar.vehiculos');
    Route::post('/vehiculos/inspecciones/importar', [ImportacionController::class, 'storeInspecciones'])->name('vehiculos.inspecciones.importar.store')->middleware('permission:importar.vehiculos');

    // Reportes
    Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index')->middleware('permission:reportes.exportar');
    Route::get('/reportes/exportar', [ReporteController::class, 'exportar'])->name('reportes.exportar')->middleware('permission:reportes.exportar');

    // Seguridad › Auditorías
    Route::get('/auditar-consultas', [AuditarConsultasController::class, 'index'])->name('auditar-consultas.index')->middleware('permission:auditoria.consultas');
    Route::get('/auditar-consultas/{auditarConsulta}', [AuditarConsultasController::class, 'show'])->whereNumber('auditarConsulta')->name('auditar-consultas.show')->middleware('permission:auditoria.consultas');
    Route::get('/auditorias', [AuditoriaController::class, 'index'])->name('auditorias.index')->middleware('permission:auditoria.cambios');
    Route::get('/auditorias/{auditoria}', [AuditoriaController::class, 'show'])->whereNumber('auditoria')->name('auditorias.show')->middleware('permission:auditoria.cambios');

    // Fotos y archivos adjuntos (los quita quien puede editar el registro al que pertenecen).
    Route::delete('/multimedia/{multimedia}', [MultimediaController::class, 'destroy'])->name('multimedia.destroy')->middleware('permission:personas.editar|mandamientos.editar|vehiculos.editar');

    // Seguridad › Usuarios
    Route::get('/usuarios', [UserController::class, 'index'])->name('usuarios.index')->middleware('permission:usuarios.listar');
    Route::post('/usuarios', [UserController::class, 'store'])->name('usuarios.store')->middleware('permission:usuarios.crear');
    Route::put('/usuarios/{usuario}', [UserController::class, 'update'])->name('usuarios.update')->middleware('permission:usuarios.editar');
    Route::delete('/usuarios/{usuario}', [UserController::class, 'destroy'])->name('usuarios.destroy')->middleware('permission:usuarios.eliminar');
    Route::post('/usuarios/{usuario}/toggle-status', [UserController::class, 'toggleStatus'])->name('usuarios.toggle-status')->middleware('permission:usuarios.activar');

    // Seguridad › Roles y permisos (nuevo: roles dinámicos)
    Route::get('/roles', [RolController::class, 'index'])->name('roles.index')->middleware('permission:roles.listar');
    Route::post('/roles', [RolController::class, 'store'])->name('roles.store')->middleware('permission:roles.gestionar');
    Route::put('/roles/{rol}', [RolController::class, 'update'])->name('roles.update')->middleware('permission:roles.gestionar');
    Route::delete('/roles/{rol}', [RolController::class, 'destroy'])->name('roles.destroy')->middleware('permission:roles.gestionar');
});

require __DIR__.'/auth.php';
