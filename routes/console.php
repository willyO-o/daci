<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

// Copia de seguridad diaria de la base de datos a medianoche (igual que el sistema anterior).
Schedule::command('db:backup')
    ->dailyAt('00:00')
    ->timezone('America/La_Paz')
    ->withoutOverlapping()
    ->onFailure(fn () => Log::error('El backup de la base de datos falló a las '.now()))
    ->onSuccess(fn () => Log::info('Backup de la base de datos completado exitosamente a las '.now()));
