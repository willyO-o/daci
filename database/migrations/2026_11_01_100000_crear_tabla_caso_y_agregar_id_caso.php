<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Casos, FASE 1 de 4 (D-037, `docs/bitacora/PROPUESTA-CASOS.md`): crea la tabla `caso` y agrega la columna `id_caso`
 * (nula) a cada tabla que guarda el caso o CUD como texto:
 *
 *   telefono.caso                     + telefono.id_caso
 *   registro_criminal.cud             + registro_criminal.id_caso
 *   falta_contravencion.cud           + falta_contravencion.id_caso
 *   vehiculo.caso_relacionado         + vehiculo.id_caso
 *   vehiculo_caso.numero_informacion  + vehiculo_caso.id_caso
 *
 * NO cambia ni borra ningún dato ni columna existente: las columnas de texto se quedan como están (se eliminan recién
 * en la fase 4, aparte). `id_caso` es nula: una fila sin caso queda con `id_caso` nulo. La llena `CasosSeeder` (fase 2).
 *
 * Antes de tocar nada comprueba (sólo lecturas) que `caso` no exista, que ninguna tabla tenga ya `id_caso` y que todas
 * sean InnoDB; si algo no calza, se detiene sin cambios. Si falla a mitad, deshace lo que alcanzó a hacer. `down()`
 * quita sólo lo agregado (las columnas de texto nunca se tocaron).
 */
return new class extends Migration
{
    /** Tablas que reciben `id_caso`. */
    private const TABLAS = ['telefono', 'registro_criminal', 'falta_contravencion', 'vehiculo', 'vehiculo_caso'];

    public function up(): void
    {
        // Con `migrate --pretend` no se ejecuta ninguna consulta (tampoco las de lectura): se muestra el SQL sin comprobar.
        if (! DB::pretending()) {
            $this->comprobar();
        }

        try {
            Schema::create('caso', function (Blueprint $t) {
                $t->id();
                // El caso o CUD como se escribía, con espacios unificados y en MAYÚSCULA. Único: el mismo texto es el
                // mismo caso en todas las tablas.
                $t->string('codigo')->unique();
                $t->text('descripcion')->nullable();
                $t->string('estado', 20)->default('ACTIVO');
                $t->foreignId('user_id')->nullable()->constrained('users');
                $t->timestamps();
                $t->softDeletes();
            });

            foreach (self::TABLAS as $tabla) {
                Schema::table($tabla, function (Blueprint $t) {
                    // Al final de la tabla y nula: MariaDB la agrega sin reescribir los datos. La clave foránea impide
                    // borrar un caso que todavía use alguna fila.
                    $t->foreignId('id_caso')->nullable()->constrained('caso');
                });
            }
        } catch (Throwable $error) {
            $this->down();

            throw $error;
        }
    }

    public function down(): void
    {
        foreach (self::TABLAS as $tabla) {
            if (Schema::hasColumn($tabla, 'id_caso')) {
                Schema::table($tabla, function (Blueprint $t) use ($tabla) {
                    if ($this->tieneClaveForanea($tabla)) {
                        $t->dropForeign(['id_caso']);
                    }
                    $t->dropColumn('id_caso');
                });
            }
        }

        Schema::dropIfExists('caso');
    }

    /**
     * Sólo lecturas. La base de producción es otra y puede diferir: si algo no calza, se detiene sin cambios y dice qué.
     */
    private function comprobar(): void
    {
        if (Schema::hasTable('caso')) {
            throw new RuntimeException('Casos (fase 1) detenida sin cambios: la tabla caso ya existe.');
        }

        $faltan = collect([...self::TABLAS, 'users'])->reject(fn (string $tabla) => Schema::hasTable($tabla));
        if ($faltan->isNotEmpty()) {
            throw new RuntimeException('Casos (fase 1) detenida sin cambios: faltan las tablas '.$faltan->implode(', ').'.');
        }

        $conColumna = collect(self::TABLAS)->filter(fn (string $tabla) => Schema::hasColumn($tabla, 'id_caso'));
        if ($conColumna->isNotEmpty()) {
            throw new RuntimeException('Casos (fase 1) detenida sin cambios: ya tienen id_caso '.$conColumna->implode(', ').'.');
        }

        $noInnodb = DB::table('information_schema.TABLES')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->whereIn('TABLE_NAME', [...self::TABLAS, 'users'])
            ->where('ENGINE', '<>', 'InnoDB')
            ->pluck('TABLE_NAME');
        if ($noInnodb->isNotEmpty()) {
            throw new RuntimeException('Casos (fase 1) detenida sin cambios: no son InnoDB (no admiten clave foránea) '.$noInnodb->implode(', ').'.');
        }
    }

    private function tieneClaveForanea(string $tabla): bool
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $tabla)
            ->where('COLUMN_NAME', 'id_caso')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();
    }
};
