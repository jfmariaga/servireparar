<?php

namespace Tests\Feature\Cotizaciones;

use App\Enums\RolPrioridad;
use App\Mail\CotizacionEnviada;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Servicio;
use App\Models\User;
use App\Services\Cotizaciones\CotizacionService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Spec 006, US2 (FR-003/FR-004): guardar como borrador no notifica al
 * cliente; enviar genera el PDF, cambia el estado y guarda el Message-ID del
 * envío para el seguimiento del hilo (US3).
 */
class EnvioCotizacionTest extends TestCase
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

    public function test_guardar_como_borrador_no_envia_correo(): void
    {
        Mail::fake();
        $cotizacion = Cotizacion::factory()->create();
        $servicio = Servicio::factory()->create();

        app(CotizacionService::class)->guardarItems($cotizacion, [
            ['tipo_item' => 'servicio', 'servicio_id' => $servicio->id, 'cantidad' => 1],
        ]);

        $this->assertSame('en_revision', $cotizacion->fresh()->estado);
        Mail::assertNothingSent();
    }

    public function test_enviar_genera_pdf_y_cambia_estado_a_cotizada(): void
    {
        Mail::fake();
        $cliente = Cliente::factory()->create(['correo' => 'cliente@ejemplo.com']);
        $cotizacion = Cotizacion::factory()->create(['cliente_id' => $cliente->id]);
        $servicio = Servicio::factory()->create(['costo_unitario' => 30000]);
        app(CotizacionService::class)->guardarItems($cotizacion, [
            ['tipo_item' => 'servicio', 'servicio_id' => $servicio->id, 'cantidad' => 1],
        ]);

        app(CotizacionService::class)->enviar($cotizacion->fresh(), $this->administrador());

        $this->assertSame('cotizada', $cotizacion->fresh()->estado);
        Mail::assertSent(CotizacionEnviada::class, fn ($m) => $m->hasTo('cliente@ejemplo.com'));

        $mensaje = $cotizacion->fresh()->mensajes()->latest('id')->first();
        $this->assertNotNull($mensaje->message_id_correo);
    }

    public function test_enviar_sin_cliente_asignado_falla(): void
    {
        Mail::fake();
        $cotizacion = Cotizacion::factory()->create(['cliente_id' => null]);
        $servicio = Servicio::factory()->create();
        app(CotizacionService::class)->guardarItems($cotizacion, [
            ['tipo_item' => 'servicio', 'servicio_id' => $servicio->id, 'cantidad' => 1],
        ]);

        $this->expectException(ValidationException::class);

        app(CotizacionService::class)->enviar($cotizacion->fresh(), $this->administrador());
    }

    public function test_enviar_sin_items_falla(): void
    {
        Mail::fake();
        $cliente = Cliente::factory()->create(['correo' => 'x@y.test']);
        $cotizacion = Cotizacion::factory()->create(['cliente_id' => $cliente->id]);

        $this->expectException(ValidationException::class);

        app(CotizacionService::class)->enviar($cotizacion, $this->administrador());
    }
}
