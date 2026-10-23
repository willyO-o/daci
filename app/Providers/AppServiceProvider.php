<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Acceso\CatalogoPermisos;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // El superadmin pasa todas las comprobaciones, también las de permisos que se agreguen en el futuro.
        Gate::before(fn (User $usuario) => $usuario->hasRole(CatalogoPermisos::ROL_SUPERADMIN) ? true : null);
    }
}
