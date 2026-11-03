<?php

namespace App\Console\Commands;

use App\Support\Casos\MigradorCasos;
use Database\Seeders\CasosSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Atajo de {@see CasosSeeder} (fase 2 de casos) con simulación y por tabla. Detalle en {@see MigradorCasos}.
 */
#[Signature('casos:migrar {--simular : Hace todo y lo deshace, sólo muestra el informe} {--tabla= : Sólo esta tabla (telefono, registro_criminal, falta_contravencion, vehiculo, vehiculo_caso)}')]
#[Description('Registra los casos/CUD de texto en la tabla caso y llena id_caso (sólo donde está nulo)')]
class MigrarCasos extends Command
{
    public function handle(): int
    {
        $seeder = $this->laravel->make(CasosSeeder::class);
        $seeder->simular = (bool) $this->option('simular');
        $seeder->tabla = $this->option('tabla') ?: null;
        $seeder->setContainer($this->laravel)->setCommand($this);

        $seeder->__invoke();

        return self::SUCCESS;
    }
}
