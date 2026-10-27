<?php

namespace App\Console\Commands;

use App\Models\Persona;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Mantenimiento heredado (`/eliminar-duplicados` del sistema anterior): une las personas repetidas por nombres y
 * apellidos en la más antigua, completando sus campos vacíos y pasándole todos los datos relacionados.
 *
 * En el sistema anterior era una URL web cuya clave no se validaba bien (cualquier usuario con sesión podía
 * ejecutarlo). Ahora sólo corre por consola, muestra antes lo que haría y pide confirmación.
 *
 * Igual que el original, primero RESTAURA las personas eliminadas (para unirlas también). La relación con la
 * tabla `documento` no se toca: pertenece a la rama de ciudadano, que este sistema no usa.
 */
#[Signature('personas:eliminar-duplicados {--forzar : No pedir confirmación}')]
#[Description('Une las personas duplicadas (mismos nombres y apellidos) en el registro más antiguo')]
class EliminarPersonasDuplicadas extends Command
{
    /**
     * Tablas y columna que apuntan a una persona.
     *
     * @var array<string, string>
     */
    private const RELACIONES = [
        'falta_contravencion' => 'id_persona',
        'inspeccion_tecnica' => 'persona_id',
        'mandamiento' => 'id_persona',
        'multimedia' => 'id_persona',
        'registro_criminal' => 'id_persona',
        'telefono' => 'persona_id',
        'vehiculo_caso' => 'persona_id',
    ];

    public function handle(): int
    {
        $grupos = Persona::withTrashed()
            ->select('nombres', 'apellidos', DB::raw('COUNT(*) as total'))
            ->whereNotNull('nombres')->where('nombres', '!=', '')
            ->whereNotNull('apellidos')->where('apellidos', '!=', '')
            ->groupBy('nombres', 'apellidos')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('total')
            ->get();

        $eliminadas = Persona::onlyTrashed()->count();
        $sobrantes = $grupos->sum(fn ($grupo) => $grupo->total - 1);

        $this->components->info("Grupos duplicados: {$grupos->count()} · personas que se unirían: {$sobrantes} · eliminadas que se restaurarían: {$eliminadas}.");

        if ($grupos->isEmpty()) {
            return self::SUCCESS;
        }

        if (! $this->option('forzar') && ! $this->confirm('¿Ejecutar la unión? Haga antes un respaldo (php artisan db:backup).')) {
            $this->components->warn('No se hizo ningún cambio.');

            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($grupos) {
                Persona::onlyTrashed()->update(['deleted_at' => null]);
                $campos = (new Persona)->getFillable();

                foreach ($grupos as $grupo) {
                    $personas = Persona::where('nombres', $grupo->nombres)->where('apellidos', $grupo->apellidos)->orderBy('id')->get();
                    $principal = $personas->shift();

                    foreach ($personas as $duplicada) {
                        foreach ($campos as $campo) {
                            if (blank($principal->{$campo}) && filled($duplicada->{$campo})) {
                                $principal->{$campo} = $duplicada->{$campo};
                            }
                        }

                        // Liberar el CI antes de pasárselo a la principal.
                        if (filled($duplicada->ci)) {
                            $duplicada->ci = null;
                            $duplicada->saveQuietly();
                        }

                        foreach (self::RELACIONES as $tabla => $columna) {
                            DB::table($tabla)->where($columna, $duplicada->id)->update([$columna => $principal->id]);
                        }

                        $duplicada->forceDelete();
                    }

                    if ($principal->isDirty()) {
                        $principal->save();
                    }
                }
            });
        } catch (Throwable $e) {
            $this->components->error('Ocurrió un error al eliminar los duplicados: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Duplicados eliminados correctamente.');

        return self::SUCCESS;
    }
}
