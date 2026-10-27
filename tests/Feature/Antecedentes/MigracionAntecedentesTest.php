<?php

namespace Tests\Feature\Antecedentes;

use App\Models\Division;
use App\Models\Persona;
use App\Models\RegistroCriminal;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * La migración de Faltas y Contravenciones sólo crea sus 4 tablas: no toca ninguna tabla ni dato existente, su
 * `down()` borra únicamente lo suyo y se detiene sin cambios si alguna de sus tablas ya existe.
 */
class MigracionAntecedentesTest extends TestCase
{
    private const TABLAS = [
        'falta_contravencion', 'foto_falta_contravencion', 'ficha_falta_contravencion', 'ficha_falta_contravencion_plantilla',
    ];

    private Migration $migracion;

    protected function setUp(): void
    {
        parent::setUp();

        // Crea y borra tablas (en MariaDB eso confirma cualquier transacción): se reconstruye la base de pruebas.
        $this->artisan('migrate:fresh', ['--force' => true]);
        $this->migracion = require database_path('migrations/2026_10_26_210000_crear_tablas_faltas_y_contravenciones.php');
        $this->migracion->down();
    }

    public function test_crea_sus_tablas_sin_tocar_los_datos_existentes_y_copia_la_plantilla(): void
    {
        $this->actingAs(User::factory()->create());
        $registro = RegistroCriminal::create([
            'fecha_registro' => '2026-01-01',
            'id_persona' => Persona::factory()->create()->id,
            'id_division' => Division::create(['division' => 'D'])->id,
        ]);
        DB::table('ficha_registro_plantilla')->insert([
            'requerimiento' => 'EN RELACIÓN AL REGISTRO CRIMINAL DE:',
            'resultado_busqueda' => 'RESULTADO {division}',
            'estado' => 'ACTIVO',
        ]);
        $antes = $this->huella();

        $this->migracion->up();

        foreach (self::TABLAS as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), $tabla);
        }
        $this->assertSame($antes, $this->huella(), 'Las tablas existentes cambiaron.');
        $this->assertNotNull($registro->fresh());
        $this->assertSame('EN RELACIÓN AL REGISTRO DE FALTAS Y CONTRAVENCIONES DE:', DB::table('ficha_falta_contravencion_plantilla')->value('requerimiento'));
        $this->assertSame('RESULTADO {division}', DB::table('ficha_falta_contravencion_plantilla')->value('resultado_busqueda'));
    }

    public function test_down_borra_solo_sus_tablas(): void
    {
        $this->migracion->up();
        $this->migracion->down();

        foreach (self::TABLAS as $tabla) {
            $this->assertFalse(Schema::hasTable($tabla), $tabla);
        }
        foreach (['registro_criminal', 'fotos_registro', 'ficha_registro', 'ficha_registro_plantilla', 'persona', 'division', 'users'] as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), $tabla);
        }
    }

    public function test_se_detiene_sin_cambios_si_una_de_sus_tablas_ya_existe(): void
    {
        Schema::create('ficha_falta_contravencion', fn ($t) => $t->id());
        DB::table('ficha_falta_contravencion')->insert(['id' => 7]);

        $error = null;
        try {
            $this->migracion->up();
        } catch (\RuntimeException $capturado) {
            $error = $capturado;
        }

        $this->assertNotNull($error);
        $this->assertStringContainsString('ficha_falta_contravencion', $error->getMessage());
        $this->assertFalse(Schema::hasTable('falta_contravencion'));
        $this->assertSame(1, DB::table('ficha_falta_contravencion')->count());

        Schema::drop('ficha_falta_contravencion');
    }

    protected function tearDown(): void
    {
        // Dejar el esquema migrado para el resto de la suite.
        if (! Schema::hasTable('falta_contravencion')) {
            $this->migracion->up();
        }

        parent::tearDown();
    }

    /**
     * Columnas y cantidad de filas de las tablas que la migración NO debe tocar.
     *
     * @return array<string, array{columnas: list<string>, filas: int}>
     */
    private function huella(): array
    {
        $huella = [];

        foreach (Schema::getTableListing(schema: DB::getDatabaseName(), schemaQualified: false) as $tabla) {
            if (in_array($tabla, self::TABLAS, true) || $tabla === 'migrations') {
                continue;
            }
            $huella[$tabla] = ['columnas' => Schema::getColumnListing($tabla), 'filas' => DB::table($tabla)->count()];
        }

        return $huella;
    }
}
