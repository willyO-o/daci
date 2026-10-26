<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seguro de correr sobre la base de producción: sólo actualiza permisos y roles base (idempotente, sólo agrega).
 * Los datos iniciales de una instalación NUEVA están en {@see InstalacionNuevaSeeder} y se llaman a mano.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermisosSeeder::class);
    }
}
