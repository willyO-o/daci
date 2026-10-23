<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PerfilController extends Controller
{
    public function index(Request $request): Response
    {
        $usuario = $request->user();

        return Inertia::render('Perfil/Index', [
            'usuario' => [
                'name' => $usuario->name,
                'email' => $usuario->email,
                'rol' => $usuario->nombreRol(),
                'creado' => $usuario->created_at?->format('d/m/Y'),
            ],
        ]);
    }

    public function actualizarClave(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'current_password.required' => 'La contraseña actual es obligatoria.',
            'current_password.current_password' => 'La contraseña actual es incorrecta.',
            'password.required' => 'La nueva contraseña es obligatoria.',
            'password.min' => 'La nueva contraseña debe tener al menos 6 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $request->user()->update(['password' => $request->input('password')]);

        return back()->with('success', 'Contraseña actualizada correctamente.');
    }
}
