<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Seguro de correr sobre la base de producción: sólo sincroniza permisos y roles base (idempotente).
 * Los datos iniciales de una instalación NUEVA están en {@see InstalacionNuevaSeeder} y se llaman a mano.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('acceso:sincronizar');
    }
}
