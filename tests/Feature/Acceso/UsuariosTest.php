<?php

namespace Tests\Feature\Acceso;

use App\Models\Rol;
use App\Models\User;
use App\Support\Acceso\CatalogoPermisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UsuariosTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_permiso_no_entra_al_listado(): void
    {
        $this->actingAs($this->usuarioConPermisos(['consultas.mandamientos']))
            ->get(route('usuarios.index'))
            ->assertForbidden();
    }

    public function test_el_listado_oculta_al_superadmin(): void
    {
        $admin = $this->usuarioConPermisos(['usuarios.listar'], ['name' => 'Admin Listado']);
        $this->superadmin()->update(['name' => 'Super Oculto']);

        $this->actingAs($admin)
            ->get(route('usuarios.index'))
            ->assertInertia(fn (Assert $pagina) => $pagina
                ->component('Usuarios/Index')
                ->where('usuarios.total', 1)
                ->where('usuarios.data.0.name', 'Admin Listado')
                ->where('roles', fn ($roles) => collect($roles)->doesntContain('name', CatalogoPermisos::ROL_SUPERADMIN)));
    }

    public function test_crea_un_usuario_con_su_rol(): void
    {
        $admin = $this->usuarioConPermisos(['usuarios.crear']);
        $rol = Rol::findByName(CatalogoPermisos::ROL_TECNICO_DACI);

        $this->actingAs($admin)
            ->post(route('usuarios.store'), [
                'name' => 'Nuevo Técnico',
                'email' => 'tecnico@felcc.bo',
                'password' => 'secreto1',
                'password_confirmation' => 'secreto1',
                'role_id' => $rol->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Usuario creado correctamente.');

        $creado = User::where('email', 'tecnico@felcc.bo')->firstOrFail();
        $this->assertSame(CatalogoPermisos::ROL_TECNICO_DACI, $creado->nombreRol());
        $this->assertTrue($creado->is_active);
    }

    public function test_no_se_puede_asignar_el_rol_superadmin(): void
    {
        $admin = $this->usuarioConPermisos(['usuarios.crear']);

        $this->actingAs($admin)
            ->post(route('usuarios.store'), [
                'name' => 'Intruso',
                'email' => 'intruso@felcc.bo',
                'password' => 'secreto1',
                'password_confirmation' => 'secreto1',
                'role_id' => Rol::findByName(CatalogoPermisos::ROL_SUPERADMIN)->id,
            ])
            ->assertSessionHasErrors(['role_id' => 'El rol seleccionado no es válido.']);
    }

    public function test_nadie_se_elimina_ni_se_desactiva_a_si_mismo(): void
    {
        $admin = $this->usuarioConPermisos(['usuarios.eliminar', 'usuarios.activar']);

        $this->actingAs($admin)->delete(route('usuarios.destroy', $admin))
            ->assertSessionHas('error', 'No puedes eliminar tu propia cuenta.');
        $this->actingAs($admin)->post(route('usuarios.toggle-status', $admin))
            ->assertSessionHas('error', 'No puedes deshabilitar tu propia cuenta.');

        $this->assertNotSoftDeleted($admin);
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_un_usuario_desactivado_no_puede_iniciar_sesion(): void
    {
        $usuario = User::factory()->create(['is_active' => false]);

        $this->post('/login', ['email' => $usuario->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Tu cuenta ha sido desactivada. Por favor, contacta con el administrador.']);

        $this->assertGuest();
    }

    public function test_el_superadmin_no_se_modifica_desde_usuarios(): void
    {
        $admin = $this->usuarioConPermisos(['usuarios.activar']);
        $super = $this->superadmin();

        $this->actingAs($admin)->post(route('usuarios.toggle-status', $super))->assertForbidden();
    }
}
