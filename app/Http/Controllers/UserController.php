<?php

namespace App\Http\Controllers;

use App\Http\Requests\UsuarioRequest;
use App\Models\Rol;
use App\Models\User;
use App\Support\Acceso\CatalogoPermisos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Usuarios del sistema. Igual que el anterior: el superadmin no aparece en el listado ni se puede asignar, y nadie
 * puede eliminarse ni desactivarse a sí mismo.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $usuarios = User::query()
            ->with('roles:id,name')
            ->whereDoesntHave('roles', fn (Builder $q) => $q->where('name', CatalogoPermisos::ROL_SUPERADMIN))
            ->when($request->filled('search'), function (Builder $q) use ($request) {
                $busqueda = $request->string('search');
                $q->where(fn (Builder $q2) => $q2->where('name', 'like', "%{$busqueda}%")->orWhere('email', 'like', "%{$busqueda}%"));
            })
            ->when($request->filled('role_id'), fn (Builder $q) => $q->whereHas('roles', fn (Builder $r) => $r->whereKey($request->integer('role_id'))))
            ->orderByDesc('id')
            ->paginate($this->tamanoPagina($request))
            ->withQueryString()
            ->through(fn (User $usuario) => $this->fila($usuario));

        return Inertia::render('Usuarios/Index', [
            'usuarios' => $usuarios,
            'roles' => $this->rolesAsignables(),
            'filtros' => $request->only(['search', 'role_id']),
        ]);
    }

    public function store(UsuarioRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $usuario = User::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password' => $request->input('password'),
            ]);
            $usuario->syncRoles([Rol::findOrFail($request->integer('role_id'))]);
        });

        return back()->with('success', 'Usuario creado correctamente.');
    }

    public function update(UsuarioRequest $request, User $usuario): RedirectResponse
    {
        $this->asegurarQueNoEsSuperadmin($usuario);

        DB::transaction(function () use ($request, $usuario) {
            $datos = $request->only(['name', 'email']);

            if ($request->filled('password')) {
                $datos['password'] = $request->input('password');
            }

            $usuario->update($datos);
            $usuario->syncRoles([Rol::findOrFail($request->integer('role_id'))]);
        });

        return back()->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(Request $request, User $usuario): RedirectResponse
    {
        $this->asegurarQueNoEsSuperadmin($usuario);

        if ($request->user()->is($usuario)) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        $usuario->delete();

        return back()->with('success', 'Usuario eliminado correctamente.');
    }

    public function toggleStatus(Request $request, User $usuario): RedirectResponse
    {
        $this->asegurarQueNoEsSuperadmin($usuario);

        if ($request->user()->is($usuario)) {
            return back()->with('error', 'No puedes deshabilitar tu propia cuenta.');
        }

        $usuario->update(['is_active' => ! $usuario->is_active]);

        return back()->with('success', $usuario->is_active ? 'Usuario activado correctamente.' : 'Usuario desactivado correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function fila(User $usuario): array
    {
        $rol = $usuario->roles->first();

        return [
            'id' => $usuario->id,
            'name' => $usuario->name,
            'email' => $usuario->email,
            'is_active' => $usuario->is_active,
            'created_at' => $usuario->created_at?->toDateString(),
            'rol' => $rol ? ['id' => $rol->id, 'name' => $rol->name] : null,
        ];
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    private function rolesAsignables()
    {
        return Rol::query()
            ->where('name', '!=', CatalogoPermisos::ROL_SUPERADMIN)
            ->orderBy('id')
            ->get(['id', 'name']);
    }

    private function asegurarQueNoEsSuperadmin(User $usuario): void
    {
        abort_if($usuario->isSuperAdmin(), 403, 'No se puede modificar al superadministrador desde aquí.');
    }
}
