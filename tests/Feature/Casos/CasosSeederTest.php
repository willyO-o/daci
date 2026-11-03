<?php

namespace Tests\Feature\Casos;

use App\Models\Caso;
use App\Models\Division;
use App\Models\FaltaContravencion;
use App\Models\Persona;
use App\Models\RegistroCriminal;
use App\Models\Rol;
use App\Models\Telefono;
use App\Models\Vehiculo;
use App\Models\VehiculoCaso;
use App\Support\Acceso\CatalogoPermisos;
use App\Support\Casos\MigradorCasos;
use Database\Seeders\CasosSeeder;
use Database\Seeders\PermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Casos, fase 2 (`CasosSeeder` / `casos:migrar`): registra cada caso en `caso` y pone su id en `id_caso`; nulo o vacío
 * → `id_caso` nulo; sólo escribe `id_caso`; idempotente; no pisa lo que puso un usuario; por tabla y con simulación.
 */
class CasosSeederTest extends TestCase
{
    use RefreshDatabase;

    private const TABLAS = ['telefono', 'registro_criminal', 'falta_contravencion', 'vehiculo', 'vehiculo_caso'];

    /**
     * Un caso en cada tabla (el mismo CUD en dos), textos escritos distinto, relleno, nulos y un teléfono eliminado.
     *
     * @return array<string, int>
     */
    private function datosHeredados(): array
    {
        $this->actingAs($this->usuarioConPermisos());
        $persona = Persona::factory()->create();
        $division = Division::create(['division' => 'D'])->id;

        $ids = [
            'tel1' => Telefono::create(['numero_celular' => '70000001', 'caso' => '  caso   keyton ', 'persona_id' => $persona->id])->id,
            'tel2' => Telefono::create(['numero_celular' => '70000002', 'caso' => 'CASO KEYTON'])->id,
            'tel3' => tap(Telefono::create(['numero_celular' => '70000003', 'caso' => 'AUTORIDADES']))->delete()->id,
            'tel_nulo' => Telefono::create(['numero_celular' => '70000004', 'caso' => null])->id,
            'tel_vacio' => Telefono::create(['numero_celular' => '70000005', 'caso' => '   '])->id,
            'tel_guion' => Telefono::create(['numero_celular' => '70000006', 'caso' => ' - '])->id,
            'tel_sn' => Telefono::create(['numero_celular' => '70000007', 'caso' => 'S/N'])->id,
            'registro' => RegistroCriminal::create(['fecha_registro' => '2026-01-01', 'id_persona' => $persona->id, 'id_division' => $division, 'cud' => '201102012600756'])->id,
            'registro_sin' => RegistroCriminal::create(['fecha_registro' => '2026-01-01', 'id_persona' => $persona->id, 'id_division' => $division, 'cud' => null])->id,
            'falta' => FaltaContravencion::create(['fecha_registro' => '2026-01-01', 'id_persona' => $persona->id, 'id_division' => $division, 'cud' => '201102012600756 '])->id,
            'vehiculo' => Vehiculo::create(['placa' => '1234ABC', 'caso_relacionado' => 'robo chino'])->id,
            'persona' => $persona->id,
        ];
        $ids['vinculo'] = VehiculoCaso::create(['vehiculo_id' => $ids['vehiculo'], 'persona_id' => $persona->id, 'tipo' => 'RUAT', 'numero_informacion' => 'CASO KEYTON'])->id;
        DB::table('telefono')->update(['updated_at' => '2020-01-01 10:00:00']);

        return $ids;
    }

    public function test_registra_cada_caso_y_pone_su_id_sin_cambiar_ningun_otro_dato(): void
    {
        $ids = $this->datosHeredados();
        $antes = $this->sinIdCaso();

        $this->seed(CasosSeeder::class);

        $this->assertSame($antes, $this->sinIdCaso(), 'Cambió algo además de id_caso (también updated_at).');
        $this->assertEqualsCanonicalizing(['CASO KEYTON', 'AUTORIDADES', '201102012600756', 'ROBO CHINO'], Caso::pluck('codigo')->all());

        $keyton = Caso::where('codigo', 'CASO KEYTON')->value('id');
        $cud = Caso::where('codigo', '201102012600756')->value('id');
        $this->assertSame($keyton, $this->idCaso('telefono', $ids['tel1']));
        $this->assertSame($keyton, $this->idCaso('telefono', $ids['tel2']), 'Si el caso ya existe, se toma su id.');
        $this->assertSame($keyton, $this->idCaso('vehiculo_caso', $ids['vinculo']), 'El mismo caso en otra tabla, mismo id.');
        $this->assertSame($cud, $this->idCaso('registro_criminal', $ids['registro']));
        $this->assertSame($cud, $this->idCaso('falta_contravencion', $ids['falta']));
        $this->assertNotNull($this->idCaso('telefono', $ids['tel3']), 'Las filas eliminadas también se migran.');
        $this->assertSame('ROBO CHINO', Vehiculo::find($ids['vehiculo'])->casoVinculado->codigo);

        foreach (['tel_nulo', 'tel_vacio', 'tel_guion', 'tel_sn'] as $sinCaso) {
            $this->assertNull($this->idCaso('telefono', $ids[$sinCaso]), "{$sinCaso}: id_caso debe quedar nulo.");
        }
        $this->assertNull($this->idCaso('registro_criminal', $ids['registro_sin']));
    }

    public function test_es_idempotente_no_pisa_lo_de_un_usuario_y_recoge_lo_nuevo(): void
    {
        $ids = $this->datosHeredados();
        app(MigradorCasos::class)->migrar();

        // Un usuario corrige el caso del teléfono 1 (el texto viejo sigue en su columna).
        $corregido = Caso::create(['codigo' => 'CASO CORREGIDO'])->id;
        DB::table('telefono')->where('id', $ids['tel1'])->update(['id_caso' => $corregido]);
        $nuevo = Telefono::create(['numero_celular' => '70000009', 'caso' => 'CASO NUEVO'])->id;
        $antes = DB::table('telefono')->where('id', '<>', $nuevo)->orderBy('id')->get()->toJson();

        $resultado = app(MigradorCasos::class)->migrar();

        $this->assertSame($corregido, $this->idCaso('telefono', $ids['tel1']), 'No se pisa lo que puso un usuario.');
        $this->assertSame($antes, DB::table('telefono')->where('id', '<>', $nuevo)->orderBy('id')->get()->toJson());
        $this->assertSame(1, $resultado['telefono']['asignadas']);
        $this->assertSame(1, $resultado['telefono']['no_coinciden']);
        $this->assertSame('CASO NUEVO', Telefono::find($nuevo)->casoVinculado->codigo);
        $this->assertSame(0, collect($resultado)->except('telefono')->sum('asignadas'));
    }

    public function test_por_tabla_y_simulacion(): void
    {
        $ids = $this->datosHeredados();

        $this->artisan('casos:migrar', ['--simular' => true])->expectsOutputToContain('SIMULACIÓN')->assertSuccessful();
        $this->assertSame(0, Caso::count());
        $this->assertNull($this->idCaso('telefono', $ids['tel1']));

        $this->artisan('casos:migrar', ['--tabla' => 'registro_criminal'])->assertSuccessful();
        $this->assertNotNull($this->idCaso('registro_criminal', $ids['registro']));
        $this->assertNull($this->idCaso('telefono', $ids['tel1']), 'Sólo la tabla pedida.');
        $this->assertSame(['201102012600756'], Caso::pluck('codigo')->all());
    }

    public function test_informa_el_relleno(): void
    {
        $this->datosHeredados();

        $resultado = app(MigradorCasos::class)->migrar();

        $this->assertSame(['-' => 1, 'S/N' => 1], $resultado['telefono']['omitidos']);
        $this->assertSame(2, $resultado['telefono']['sin_caso']);
        $this->assertSame(2, $resultado['telefono']['relleno']);
    }

    public function test_normaliza_nulos_espacios_guiones_y_relleno(): void
    {
        foreach ([null, '', '   ', "\t", "\u{00A0}", '-', '--', '—', '...', '_', 'S/N', 's.n.', 'n/a', 'Sin caso', 'SIN CUD', 'ninguno', '0', '000', 'xxx', '#N/A', '#¿NOMBRE?'] as $valor) {
            $this->assertNull(MigradorCasos::normalizar($valor), var_export($valor, true));
        }
        $this->assertSame('CASO X', MigradorCasos::normalizar(" -  caso\u{00A0}  x - "));
        $this->assertSame('2011-0201-26', MigradorCasos::normalizar('2011-0201-26'));
        $this->assertSame('AUTORIDADES', MigradorCasos::normalizar('autoridades'));
    }

    public function test_eliminar_completo_una_persona_sigue_funcionando_con_casos(): void
    {
        $ids = $this->datosHeredados();
        $this->seed(CasosSeeder::class);
        $this->actingAs($this->usuarioConPermisos(['personas.eliminar']));

        $this->delete(route('personas.destroy', $ids['persona']), ['eliminar_completo' => 1])
            ->assertSessionHas('success', 'Persona eliminada correctamente.');

        $this->assertDatabaseMissing('telefono', ['id' => $ids['tel1']]);
        $this->assertDatabaseMissing('registro_criminal', ['id' => $ids['registro']]);
        $this->assertSame(1, Caso::where('codigo', 'CASO KEYTON')->sole()->telefonos()->count(), 'El caso sigue, con sus otras filas.');
    }

    public function test_permisos_de_casos_solo_para_el_administrador_sin_quitar_los_anteriores(): void
    {
        // Base como la de producción antes de la actualización: todos los permisos menos los de casos.
        $this->artisan('acceso:sincronizar');
        Permission::where('name', 'like', 'casos.%')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $antes = $this->permisosPorRol();

        $this->seed(PermisosSeeder::class);

        $casos = ['casos.crear', 'casos.editar', 'casos.eliminar', 'casos.listar', 'casos.ver'];
        foreach ($this->permisosPorRol() as $rol => $permisos) {
            $esperado = in_array($rol, [CatalogoPermisos::ROL_SUPERADMIN, CatalogoPermisos::ROL_ADMINISTRADOR], true)
                ? [...$antes[$rol], ...$casos] : $antes[$rol];
            sort($esperado);
            $this->assertSame($esperado, $permisos, "Rol {$rol}");
        }

        // Lo cambiado después en la pantalla de roles se respeta: volver a correrlo no repone ni borra nada.
        Rol::findByName(CatalogoPermisos::ROL_ADMINISTRADOR, CatalogoPermisos::GUARD)->revokePermissionTo('casos.eliminar');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $editado = $this->permisosPorRol();

        $this->seed(PermisosSeeder::class);

        $this->assertSame($editado, $this->permisosPorRol());
    }

    private function idCaso(string $tabla, int $id): ?int
    {
        $valor = DB::table($tabla)->where('id', $id)->value('id_caso');

        return $valor === null ? null : (int) $valor;
    }

    /**
     * Todas las columnas menos `id_caso` de las 5 tablas.
     *
     * @return array<string, string>
     */
    private function sinIdCaso(): array
    {
        return collect(self::TABLAS)->mapWithKeys(function (string $tabla) {
            $columnas = array_values(array_diff(Schema::getColumnListing($tabla), ['id_caso']));

            return [$tabla => DB::table($tabla)->select($columnas)->orderBy('id')->get()->toJson()];
        })->all();
    }

    /**
     * @return array<string, list<string>>
     */
    private function permisosPorRol(): array
    {
        return Rol::with('permissions')->get()
            ->mapWithKeys(fn (Rol $rol) => [$rol->name => $rol->permissions->pluck('name')->sort()->values()->all()])
            ->all();
    }
}
