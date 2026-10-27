<?php

namespace Database\Seeders;

use App\Models\FichaRegistroPlantilla;
use Illuminate\Database\Seeder;

class PlantillaFichaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        // El texto está en el modelo: lo usa también la vista previa de la ficha si la base no tiene plantilla.
        FichaRegistroPlantilla::create([...FichaRegistroPlantilla::TEXTO_ESTANDAR, 'estado' => 'ACTIVO']);
    }
}
