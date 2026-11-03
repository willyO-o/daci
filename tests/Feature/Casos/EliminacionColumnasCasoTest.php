<?php

namespace Tests\Feature\Casos;

use App\Support\Casos\MigradorCasos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Casos, fase 4: se niega a borrar si queda un caso sin `id_caso` o si el código todavía usa la columna; respalda cada
 * valor antes de borrar y `down()` repone las columnas con sus valores exactos.
 */
class EliminacionColumnasCasoTest extends TestCase
{
    private const COLUMNAS = [
        'telefono' => 'caso', 'registro_criminal' => 'cud', 'falta_contravencion' => 'cud',
        'vehiculo' => 'caso_relacionado', 'vehiculo_caso' => 'numero_informacion',
    ];

    private Migration $migracion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh', ['--force' => true]);
        $this->migracion = require database_path('migrations/casos-fase-4/2026_11_01_200000_eliminar_columnas_de_texto_del_caso.php');
    }

    public function test_se_niega_si_el_codigo_todavia_usa_la_columna(): void
    {
        $this->assertDetiene('falta desplegar la fase 3');
    }

    public function test_se_niega_si_queda_un_caso_sin_id_caso(): void
    {
        DB::table('telefono')->insert(['numero_celular' => '70000001', 'caso' => 'CASO SIN MIGRAR']);
        $this->migracion->comprobarCodigo = false;

        $this->assertDetiene('Correr: php artisan casos:migrar');
    }

    public function test_respalda_borra_y_down_repone_cada_valor(): void
    {
        DB::table('telefono')->insert([
            ['numero_celular' => '70000001', 'caso' => '  caso   keyton '],
            ['numero_celular' => '70000002', 'caso' => 'S/N'],
            ['numero_celular' => '70000003', 'caso' => null],
        ]);
        DB::table('vehiculo')->insert(['placa' => '1234ABC', 'caso_relacionado' => 'robo chino']);
        app(MigradorCasos::class)->migrar();
        $antes = $this->contenido();
        $this->migracion->comprobarCodigo = false;

        $this->migracion->up();

        foreach (self::COLUMNAS as $tabla => $columna) {
            $this->assertFalse(Schema::hasColumn($tabla, $columna), "{$tabla}.{$columna}");
        }
        $this->assertSame(
            ['  caso   keyton ', 'S/N', 'robo chino'],
            DB::table('caso_texto_respaldo')->orderBy('id')->pluck('valor')->all(),
            'El respaldo guarda el texto original, también el relleno.',
        );
        $this->assertNotNull(DB::table('telefono')->where('numero_celular', '70000001')->value('id_caso'));

        $this->migracion->down();

        $this->assertSame($antes, $this->contenido(), 'down() debe dejar columnas y valores exactamente como estaban.');
        $this->assertFalse(Schema::hasTable('caso_texto_respaldo'));
    }

    protected function tearDown(): void
    {
        // Reconstruir la base para el resto de la suite.
        $this->artisan('migrate:fresh', ['--force' => true]);

        parent::tearDown();
    }

    private function assertDetiene(string $mensaje): void
    {
        try {
            $this->migracion->up();
            $this->fail('Debió detenerse.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString($mensaje, $error->getMessage());
        }

        foreach (self::COLUMNAS as $tabla => $columna) {
            $this->assertTrue(Schema::hasColumn($tabla, $columna), "{$tabla}.{$columna}");
        }
        $this->assertFalse(Schema::hasTable('caso_texto_respaldo'));
    }

    /**
     * Columnas (en orden) y filas de las 5 tablas.
     *
     * @return array<string, array{columnas: list<string>, filas: string}>
     */
    private function contenido(): array
    {
        return collect(self::COLUMNAS)->mapWithKeys(fn ($columna, string $tabla) => [$tabla => [
            'columnas' => Schema::getColumnListing($tabla),
            'filas' => DB::table($tabla)->orderBy('id')->get()->toJson(),
        ]])->all();
    }
}
