<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use App\Support\Acceso\CatalogoPermisos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles DINÁMICOS: se crean, editan y eliminan desde Seguridad › Roles y permisos. Los permisos son los del catálogo
 * ({@see CatalogoPermisos}). Los 6 roles heredados no se eliminan ni se renombran; el superadmin no se edita (tiene
 * todo por `Gate::before`).
 */
class RolController extends Controller
{
    public function index(): Response
    {
        $roles = Rol::query()
            ->withCount('users')
            ->with('permissions:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (Rol $rol) => [
                'id' => $rol->id,
                'name' => $rol->name,
                'descripcion' => $rol->descripcion,
                'usuarios' => $rol->users_count,
                'permisos' => $rol->permissions->pluck('name')->values(),
                'es_base' => in_array($rol->name, CatalogoPermisos::rolesBase(), true),
                'es_superadmin' => $rol->name === CatalogoPermisos::ROL_SUPERADMIN,
            ]);

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'modulos' => CatalogoPermisos::modulos(),
            'totalPermisos' => count(CatalogoPermisos::permisos()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['name' => $this->normalizarNombre($request->input('name'))]);
        $datos = $this->validar($request);

        DB::transaction(function () use ($datos) {
            $rol = Rol::create([
                'name' => $datos['name'],
                'guard_name' => CatalogoPermisos::GUARD,
                'descripcion' => $datos['descripcion'] ?? null,
            ]);
            $rol->syncPermissions($datos['permisos'] ?? []);
        });

        return back()->with('success', 'Rol creado correctamente.');
    }

    public function update(Request $request, Rol $rol): RedirectResponse
    {
        abort_if($rol->name === CatalogoPermisos::ROL_SUPERADMIN, 403, 'El rol superadmin tiene todos los permisos y no se edita.');

        $esBase = in_array($rol->name, CatalogoPermisos::rolesBase(), true);

        // Un rol base conserva su nombre: el sistema y la migración de roles lo identifican por él.
        $request->merge(['name' => $esBase ? $rol->name : $this->normalizarNombre($request->input('name'))]);
        $datos = $this->validar($request, $rol);

        DB::transaction(function () use ($rol, $datos) {
            $rol->update([
                'name' => $datos['name'],
                'descripcion' => $datos['descripcion'] ?? null,
            ]);
            $rol->syncPermissions($datos['permisos'] ?? []);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', 'Rol actualizado correctamente.');
    }

    public function destroy(Rol $rol): RedirectResponse
    {
        if (in_array($rol->name, CatalogoPermisos::rolesBase(), true)) {
            return back()->with('error', 'Los roles base del sistema no se pueden eliminar.');
        }

        if ($rol->users()->exists()) {
            return back()->with('error', 'El rol tiene usuarios asignados. Cámbielos de rol antes de eliminarlo.');
        }

        $rol->delete();

        return back()->with('success', 'Rol eliminado correctamente.');
    }

    /**
     * @return array{name: string, descripcion?: ?string, permisos?: list<string>}
     */
    private function validar(Request $request, ?Rol $rol = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('roles', 'name')->where('guard_name', CatalogoPermisos::GUARD)->ignore($rol),
            ],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'permisos' => ['array'],
            'permisos.*' => ['string', Rule::in(CatalogoPermisos::permisos())],
        ], [
            'name.required' => 'El nombre del rol es obligatorio.',
            'name.unique' => 'Ya existe un rol con ese nombre.',
            'permisos.*.in' => 'Uno de los permisos elegidos no existe.',
        ]);
    }

    /**
     * Los nombres de rol siguen el formato heredado: minúsculas y guion bajo (`tecnico_daci`).
     */
    private function normalizarNombre(?string $nombre): string
    {
        return Str::slug((string) $nombre, '_');
    }
}
