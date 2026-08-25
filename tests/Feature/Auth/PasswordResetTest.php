<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    public function test_solicitar_enlace_muestra_mensaje_generico_exista_o_no_el_correo(): void
    {
        User::factory()->create(['email' => 'existe@servireparar.com']);

        Volt::test('auth.forgot-password')
            ->set('email', 'existe@servireparar.com')
            ->call('enviar')
            ->assertSet('enviado', true);

        Volt::test('auth.forgot-password')
            ->set('email', 'no-existe@servireparar.com')
            ->call('enviar')
            ->assertSet('enviado', true);
    }

    public function test_reseteo_exitoso_permite_iniciar_sesion_con_la_nueva_contrasena(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@servireparar.com',
            'password' => 'password-vieja',
            'estado' => 'activo',
        ]);

        $token = Password::broker()->createToken($user);

        Volt::test('auth.reset-password', ['token' => $token])
            ->assertSet('email', '')
            ->set('email', 'reset@servireparar.com')
            ->set('password', 'password-nueva')
            ->set('password_confirmation', 'password-nueva')
            ->call('restablecer')
            ->assertRedirect(route('login'));

        $this->assertTrue(auth()->attempt(['email' => 'reset@servireparar.com', 'password' => 'password-nueva']));
    }

    public function test_enlace_invalido_es_rechazado(): void
    {
        User::factory()->create(['email' => 'invalido@servireparar.com']);

        Volt::test('auth.reset-password', ['token' => 'token-que-no-existe'])
            ->set('email', 'invalido@servireparar.com')
            ->set('password', 'password-nueva')
            ->set('password_confirmation', 'password-nueva')
            ->call('restablecer')
            ->assertSet('errorMessage', 'El enlace no es válido o ya expiró. Solicita uno nuevo.');
    }

    public function test_enlace_ya_usado_no_puede_reutilizarse(): void
    {
        $user = User::factory()->create(['email' => 'usado@servireparar.com']);
        $token = Password::broker()->createToken($user);

        Volt::test('auth.reset-password', ['token' => $token])
            ->set('email', 'usado@servireparar.com')
            ->set('password', 'primera-nueva')
            ->set('password_confirmation', 'primera-nueva')
            ->call('restablecer')
            ->assertRedirect(route('login'));

        // Reintentar el mismo token ya consumido debe fallar.
        Volt::test('auth.reset-password', ['token' => $token])
            ->set('email', 'usado@servireparar.com')
            ->set('password', 'segunda-nueva')
            ->set('password_confirmation', 'segunda-nueva')
            ->call('restablecer')
            ->assertSet('errorMessage', 'El enlace no es válido o ya expiró. Solicita uno nuevo.');
    }
}
