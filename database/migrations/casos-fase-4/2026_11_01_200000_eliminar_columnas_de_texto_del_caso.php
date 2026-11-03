<?php

use App\Models\FaltaContravencion;
use App\Models\RegistroCriminal;
use App\Models\Telefono;
use App\Models\Vehiculo;
use App\Models\VehiculoCaso;
use App\Support\Casos\MigradorCasos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Casos, FASE 4 de 4 (D-037): elimina las columnas de texto del caso, ya reemplazadas por `id_caso`. NO se ejecuta con
 * `php artisan migrate` (está fuera de la carpeta de migraciones); se corre a mano cuando las fases 1–3 están en
 * producción y verificadas:
 *
 *     php artisan migrate --path=database/migrations/casos-fase-4 --force
 *
 * Se detiene sin cambios si: queda alguna fila con caso y `id_caso` nulo (correr `casos:migrar`), o el código todavía
 * usa la columna de texto (fase 3 sin desplegar). Antes de borrar, copia CADA valor original (también el relleno como
 * «S/N») a `caso_texto_respaldo` y comprueba la copia. `down()` vuelve a crear las columnas en su lugar con sus valores.
 */
return new class extends Migration
{
    /**
     * Tabla => [columna de texto, largo, columna anterior (para reponerla en su lugar), modelo que no debe usarla].
     *
     * @var array<string, array{0: string, 1: int, 2: string, 3: class-string}>
     */
    private const COLUMNAS = [
        'telefono' => ['caso', 255, 'persona_caso', Telefono::class],
        'registro_criminal' => ['cud', 255, 'peso', RegistroCriminal::class],
        'falta_contravencion' => ['cud', 255, 'peso', FaltaContravencion::class],
        'vehiculo' => ['caso_relacionado', 255, 'responsable', Vehiculo::class],
        'vehiculo_caso' => ['numero_informacion', 140, 'updated_at', VehiculoCaso::class],
    ];

    private const RESPALDO = 'caso_texto_respaldo';

    /** Las pruebas lo apagan para ensayar el borrado con el código actual. */
    public bool $comprobarCodigo = true;

    public function up(): void
    {
        $this->comprobar();

        Schema::create(self::RESPALDO, function (Blueprint $t) {
            $t->id();
            $t->string('tabla', 64);
            $t->unsignedBigInteger('registro_id');
            $t->string('columna', 64);
            $t->text('valor');
            $t->unsignedBigInteger('id_caso')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->unique(['tabla', 'registro_id']);
        });

        try {
            DB::transaction(function () {
                foreach (self::COLUMNAS as $tabla => [$columna]) {
                    $copiadas = DB::affectingStatement(
                        'INSERT INTO `'.self::RESPALDO.'` (tabla, registro_id, columna, valor, id_caso, created_at) '
                        ."SELECT ?, id, ?, `{$columna}`, id_caso, NOW() FROM `{$tabla}` WHERE `{$columna}` IS NOT NULL",
                        [$tabla, $columna],
                    );
                    $esperadas = DB::table($tabla)->whereNotNull($columna)->count();

                    if ($copiadas !== $esperadas) {
                        throw new RuntimeException("Casos (fase 4) detenida: el respaldo de {$tabla}.{$columna} copió {$copiadas} de {$esperadas}.");
                    }
                }
            });
        } catch (Throwable $error) {
            Schema::dropIfExists(self::RESPALDO);

            throw $error;
        }

        // Recién con el respaldo completo y comprobado. Si un borrado falla, se reponen las ya borradas con sus valores.
        try {
            foreach (self::COLUMNAS as $tabla => [$columna]) {
                Schema::table($tabla, fn (Blueprint $t) => $t->dropColumn($columna));
            }
        } catch (Throwable $error) {
            $this->down();

            throw $error;
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNAS as $tabla => [$columna, $largo, $despuesDe]) {
            if (! Schema::hasColumn($tabla, $columna)) {
                Schema::table($tabla, fn (Blueprint $t) => $t->string($columna, $largo)->nullable()->after($despuesDe));
            }

            if (Schema::hasTable(self::RESPALDO)) {
                DB::statement(
                    "UPDATE `{$tabla}` e JOIN `".self::RESPALDO."` r ON r.tabla = ? AND r.registro_id = e.id SET e.`{$columna}` = r.valor",
                    [$tabla],
                );
            }
        }

        Schema::dropIfExists(self::RESPALDO);
    }

    /**
     * Sólo lecturas: nada se pierde al borrar.
     */
    private function comprobar(): void
    {
        if (Schema::hasTable(self::RESPALDO)) {
            throw new RuntimeException('Casos (fase 4) detenida sin cambios: ya existe '.self::RESPALDO.'.');
        }

        foreach (self::COLUMNAS as $tabla => [$columna, , , $modelo]) {
            if (! Schema::hasColumn($tabla, 'id_caso')) {
                throw new RuntimeException("Casos (fase 4) detenida sin cambios: {$tabla} no tiene id_caso (falta la fase 1).");
            }

            if ($this->comprobarCodigo && in_array($columna, (new $modelo)->getFillable(), true)) {
                throw new RuntimeException("Casos (fase 4) detenida sin cambios: el código todavía usa {$tabla}.{$columna} (falta desplegar la fase 3).");
            }

            $pendientes = 0;
            DB::table($tabla)->select(['id', "{$columna} as valor", 'id_caso'])->whereNull('id_caso')->whereNotNull($columna)
                ->chunkById(1000, function ($filas) use (&$pendientes) {
                    $pendientes += $filas->filter(fn ($f) => MigradorCasos::normalizar($f->valor) !== null)->count();
                }, 'id', 'id');

            if ($pendientes > 0) {
                throw new RuntimeException("Casos (fase 4) detenida sin cambios: {$pendientes} fila(s) de {$tabla} tienen caso y id_caso nulo. Correr: php artisan casos:migrar");
            }
        }
    }
};
