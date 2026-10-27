<?php

namespace Tests\Feature\Auth;

use App\Models\Rol;
use App\Models\User;
use App\Support\Acceso\CatalogoPermisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Sin «olvidé mi contraseña»: la contraseña la restablece el administrador en Seguridad › Usuarios.
 */
class RecuperarClaveDeshabilitadaTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_login_no_ofrece_recuperar_la_contrasena(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Auth/Login')->missing('canResetPassword'));
    }

    public function test_las_direcciones_de_recuperacion_ya_no_existen(): void
    {
        foreach (['password.request', 'password.email', 'password.reset', 'password.store'] as $nombre) {
            $this->assertFalse(Route::has($nombre), $nombre);
        }

        $this->get('/forgot-password')->assertNotFound();
        $this->post('/forgot-password', ['email' => 'alguien@felcc.bo'])->assertNotFound();
        $this->get('/reset-password/token-cualquiera')->assertNotFound();
        $this->post('/reset-password', ['email' => 'alguien@felcc.bo'])->assertNotFound();
    }

    public function test_el_administrador_restablece_la_contrasena_desde_usuarios(): void
    {
        $this->artisan('acceso:sincronizar');
        $usuario = User::factory()->create(['email' => 'tecnico@felcc.bo']);
        $usuario->asignarRol(Rol::findByName(CatalogoPermisos::ROL_TECNICO_DACI));

        $this->actingAs($this->usuarioConPermisos(['usuarios.editar']))
            ->put(route('usuarios.update', $usuario), [
                'name' => $usuario->name,
                'email' => $usuario->email,
                'role_id' => Rol::findByName(CatalogoPermisos::ROL_TECNICO_DACI)->id,
                'password' => 'nueva123',
                'password_confirmation' => 'nueva123',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('nueva123', $usuario->fresh()->password));
    }
}
