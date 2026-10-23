<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la sesión de un usuario desactivado en su siguiente petición (mismo comportamiento que
 * `EnsureUserIsActive` del sistema anterior).
 */
class AsegurarUsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario !== null && ! $usuario->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('message', 'Tu cuenta ha sido desactivada. Por favor, contacta con el administrador.');
        }

        return $next($request);
    }
}
