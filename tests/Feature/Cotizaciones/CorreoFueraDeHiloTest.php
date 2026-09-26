<?php

namespace Tests\Feature\Cotizaciones;

use App\Contracts\MensajeCorreoEntrante;
use App\Contracts\ProveedorCorreoEntrante;
use App\Enums\RolPrioridad;
use App\Models\Cotizacion;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeProveedorCorreoEntrante;
use Tests\TestCase;

/**
 * Spec 006, US1/US3 (FR-011): un correo que no sigue la plantilla esperada, o
 * que dice ser respuesta pero no referencia ningún hilo conocido, notifica al
 * Administrador en vez de crear un caso nuevo o descartarse silenciosamente.
 */
class CorreoFueraDeHiloTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    private function administrador(): User
    {
        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->assignRole(RolPrioridad::Administrador->value);

        return $admin;
    }

    public function test_correo_sin_asunto_reconocible_no_crea_cotizacion_y_notifica(): void
    {
        $admin = $this->administrador();

        $fake = new FakeProveedorCorreoEntrante();
        $fake->encolar(new MensajeCorreoEntrante(
            messageId: '<msg-x@spam.test>',
            remitente: 'promos@spam.test',
            asunto: 'Oferta especial de hoy',
            cuerpo: 'Compre ya.',
        ));
        $this->app->instance(ProveedorCorreoEntrante::class, $fake);

        $this->artisan('cotizaciones:procesar-correo');

        $this->assertSame(0, Cotizacion::count());
        $this->assertSame(1, $admin->fresh()->unreadNotifications()->count());
        $this->assertSame('Correo de cotización no reconocido', $admin->fresh()->notifications()->first()->data['titulo']);
    }

    public function test_respuesta_fuera_de_hilo_no_crea_cotizacion_nueva_y_notifica(): void
    {
        $admin = $this->administrador();

        $fake = new FakeProveedorCorreoEntrante();
        $fake->encolar(new MensajeCorreoEntrante(
            messageId: '<msg-y@cliente.test>',
            remitente: 'cliente@cliente.test',
            asunto: 'RE: su cotización',
            cuerpo: 'La acepto.',
            referencias: ['<no-existe@nadie.test>'],
        ));
        $this->app->instance(ProveedorCorreoEntrante::class, $fake);

        $this->artisan('cotizaciones:procesar-correo');

        $this->assertSame(0, Cotizacion::count());
        $this->assertSame(1, $admin->fresh()->unreadNotifications()->count());
        $this->assertSame('Respuesta de cotización fuera de hilo', $admin->fresh()->notifications()->first()->data['titulo']);
    }
}
