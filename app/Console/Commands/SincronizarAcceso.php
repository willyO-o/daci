<?php

namespace App\Console\Commands;

use App\Support\Acceso\SincronizadorAcceso;
use Database\Seeders\PermisosSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Atajo de {@see PermisosSeeder}: pone la base al día con el catálogo de permisos. Sólo agrega; nunca borra ni quita
 * permisos a un rol. Detalle en {@see SincronizadorAcceso}.
 */
#[Signature('acceso:sincronizar')]
#[Description('Crea los permisos y roles base que falten, da todo al superadmin y repara users.role_id (sólo agrega)')]
class SincronizarAcceso extends Command
{
    public function handle(): int
    {
        $this->call('db:seed', ['--class' => PermisosSeeder::class, '--force' => true]);

        return self::SUCCESS;
    }
}
