<?php

namespace Tests\Feature\Evidencias;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * La migración de Evidencias sólo crea sus 4 tablas (con el catálogo inicial): no toca ninguna tabla ni dato existente,
 * su `down()` borra únicamente lo suyo y se detiene sin cambios si alguna de sus tablas ya existe.
 */
class MigracionEvidenciasTest extends TestCase
{
    private const TABLAS = ['tipo_articulo', 'evidencia', 'evidencia_articulo', 'evidencia_archivo'];

    private Migration $migracion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh', ['--force' => true]);
        $this->migracion = require database_path('migrations/2026_11_02_120000_crear_tablas_evidencias.php');
        $this->migracion->down();
    }

    public function test_crea_sus_tablas_y_el_catalogo_sin_tocar_las_existentes(): void
    {
        DB::table('caso')->insert(['codigo' => 'CASO X', 'estado' => 'ACTIVO']);
        $antes = $this->huella();

        $this->migracion->up();

        foreach (self::TABLAS as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), $tabla);
        }
        $this->assertSame($antes, $this->huella(), 'Cambió una tabla existente.');
        $this->assertSame(8, DB::table('tipo_articulo')->count());
        $this->assertSame('CELULAR', DB::table('tipo_articulo')->where('nombre', 'TELÉFONO CELULAR')->value('grupo'));
    }

    public function test_down_borra_solo_sus_tablas(): void
    {
        $this->migracion->up();
        $this->migracion->down();

        foreach (self::TABLAS as $tabla) {
            $this->assertFalse(Schema::hasTable($tabla), $tabla);
        }
        foreach (['caso', 'division', 'users', 'imei', 'multimedia'] as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), $tabla);
        }
    }

    public function test_se_detiene_sin_cambios_si_una_de_sus_tablas_ya_existe(): void
    {
        Schema::create('evidencia_archivo', fn ($t) => $t->id());

        try {
            $this->migracion->up();
            $this->fail('Debió detenerse.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('evidencia_archivo', $error->getMessage());
        }

        $this->assertFalse(Schema::hasTable('evidencia'));
        Schema::drop('evidencia_archivo');
    }

    protected function tearDown(): void
    {
        if (! Schema::hasTable('evidencia')) {
            $this->migracion->up();
        }

        parent::tearDown();
    }

    /**
     * @return array<string, array{columnas: list<string>, filas: string}>
     */
    private function huella(): array
    {
        $huella = [];

        foreach (Schema::getTableListing(schema: DB::getDatabaseName(), schemaQualified: false) as $tabla) {
            if (in_array($tabla, [...self::TABLAS, 'migrations'], true)) {
                continue;
            }
            $huella[$tabla] = ['columnas' => Schema::getColumnListing($tabla), 'filas' => DB::table($tabla)->get()->toJson()];
        }

        return $huella;
    }
}
