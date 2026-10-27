<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seguro de correr sobre la base de producción: sólo actualiza permisos, roles base y roles predefinidos (pol_110)
 * (idempotente, sólo agrega).
 * Los datos iniciales de una instalación NUEVA están en {@see InstalacionNuevaSeeder} y se llaman a mano.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // RolesSeeder corre antes PermisosSeeder.
        $this->call(RolesSeeder::class);
    }
}
