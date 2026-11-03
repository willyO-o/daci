<?php

namespace Tests\Feature\Casos;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Casos, fase 1: la migración crea `caso` y agrega `id_caso` (nula) a las 5 tablas sin cambiar ningún otro dato ni
 * columna; `down()` deja el esquema como estaba; se detiene sin cambios si algo ya existe.
 */
class MigracionCasosTest extends TestCase
{
    private const TABLAS = ['telefono', 'registro_criminal', 'falta_contravencion', 'vehiculo', 'vehiculo_caso'];

    private Migration $migracion;

    /** Evidencias (D-038) apunta a `caso`: se quita antes y se repone al final. */
    private Migration $migracionEvidencias;

    protected function setUp(): void
    {
        parent::setUp();

        // Crea y altera tablas (en MariaDB eso confirma cualquier transacción): se reconstruye la base de pruebas.
        $this->artisan('migrate:fresh', ['--force' => true]);
        $this->migracion = require database_path('migrations/2026_11_01_100000_crear_tabla_caso_y_agregar_id_caso.php');
        $this->migracionEvidencias = require database_path('migrations/2026_11_02_120000_crear_tablas_evidencias.php');
        $this->migracionEvidencias->down();
        $this->migracion->down();
    }

    public function test_agrega_id_caso_nulo_sin_cambiar_ningun_otro_dato(): void
    {
        DB::table('telefono')->insert(['numero_celular' => '70000000', 'caso' => 'CASO X', 'updated_at' => '2020-01-01 00:00:00']);
        $antes = $this->huella();

        $this->migracion->up();

        $this->assertTrue(Schema::hasTable('caso'));
        foreach (self::TABLAS as $tabla) {
            $this->assertTrue(Schema::hasColumn($tabla, 'id_caso'), $tabla);
        }
        $this->assertSame($antes, $this->huella(), 'Cambió una columna o un dato existente.');
        $this->assertNull(DB::table('telefono')->value('id_caso'));
        $this->assertSame('CASO X', DB::table('telefono')->value('caso'));
    }

    public function test_down_deja_el_esquema_como_estaba(): void
    {
        $columnas = collect(self::TABLAS)->mapWithKeys(fn ($t) => [$t => Schema::getColumnListing($t)])->all();

        $this->migracion->up();
        $this->migracion->down();

        $this->assertFalse(Schema::hasTable('caso'));
        $this->assertSame($columnas, collect(self::TABLAS)->mapWithKeys(fn ($t) => [$t => Schema::getColumnListing($t)])->all());
    }

    public function test_se_detiene_sin_cambios_si_ya_existe_algo(): void
    {
        DB::statement('ALTER TABLE vehiculo ADD COLUMN id_caso BIGINT UNSIGNED NULL');

        try {
            $this->migracion->up();
            $this->fail('Debió detenerse.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('vehiculo', $error->getMessage());
        }

        $this->assertFalse(Schema::hasTable('caso'));
        $this->assertFalse(Schema::hasColumn('telefono', 'id_caso'));

        DB::statement('ALTER TABLE vehiculo DROP COLUMN id_caso');
    }

    protected function tearDown(): void
    {
        // Dejar el esquema migrado para el resto de la suite.
        if (! Schema::hasTable('caso')) {
            $this->migracion->up();
        }
        if (! Schema::hasTable('evidencia')) {
            $this->migracionEvidencias->up();
        }

        parent::tearDown();
    }

    /**
     * Columnas (menos `id_caso`) y contenido de todas las tablas, menos las de la migración.
     *
     * @return array<string, array{columnas: list<string>, filas: string}>
     */
    private function huella(): array
    {
        $huella = [];

        foreach (Schema::getTableListing(schema: DB::getDatabaseName(), schemaQualified: false) as $tabla) {
            if (in_array($tabla, ['caso', 'migrations', 'tipo_articulo', 'evidencia', 'evidencia_articulo', 'evidencia_archivo'], true)) {
                continue;
            }
            $columnas = array_values(array_diff(Schema::getColumnListing($tabla), ['id_caso']));
            $huella[$tabla] = ['columnas' => $columnas, 'filas' => DB::table($tabla)->select($columnas)->get()->toJson()];
        }

        return $huella;
    }
}
