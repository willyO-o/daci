<?php

namespace App\Http\Controllers;

use App\Models\Mandamiento;
use App\Models\Persona;
use App\Models\RegistroCriminal;
use App\Models\Vehiculo;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Panel de inicio: los mismos cuatro totales que mostraba el sistema anterior.
     */
    public function index(): Response
    {
        return Inertia::render('Dashboard/Index', [
            'totales' => [
                'mandamientos' => Mandamiento::count(),
                'registros' => RegistroCriminal::count(),
                'personas' => Persona::count(),
                'vehiculos' => Vehiculo::count(),
            ],
        ]);
    }
}
