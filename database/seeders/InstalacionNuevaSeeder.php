<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Datos iniciales para una base VACÍA (catálogos de tipos de mandamiento, delitos, juzgados, países, divisiones y la
 * plantilla de ficha de registro). NO correr sobre la base de producción: duplicaría catálogos.
 *
 *     php artisan db:seed --class=InstalacionNuevaSeeder
 */
class InstalacionNuevaSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DatabaseSeeder::class,
            inicialesSeeder::class,
            PlantillaFichaSeeder::class,
        ]);
    }
}
