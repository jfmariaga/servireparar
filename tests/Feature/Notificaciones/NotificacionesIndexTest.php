<?php

namespace Tests\Feature\Notificaciones;

use App\Notifications\OtNotificacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Tests\Feature\OrdenesTrabajo\OtScenario;
use Tests\TestCase;

/**
 * Listado completo de notificaciones (spec 008, T011) — más allá de las
 * últimas 15 de la campana.
 */
class NotificacionesIndexTest extends TestCase
{
    use OtScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOtCatalogos();
    }

    public function test_lista_las_notificaciones_del_usuario_autenticado(): void
    {
        $jefe = $this->jefeDeTaller();
        Notification::send($jefe, new OtNotificacion('Aviso de prueba', 'Cuerpo del aviso', '/ruta', 'info'));

        Volt::actingAs($jefe->fresh())->test('notificaciones.index')
            ->assertSee('Aviso de prueba');
    }

    public function test_filtro_solo_no_leidas_oculta_las_ya_leidas(): void
    {
        $jefe = $this->jefeDeTaller();
        Notification::send($jefe, new OtNotificacion('Ya leída', 'Cuerpo', null, 'info'));
        $jefe->fresh()->unreadNotifications->markAsRead();
        Notification::send($jefe->fresh(), new OtNotificacion('Sin leer', 'Cuerpo', null, 'info'));

        $comp = Volt::actingAs($jefe->fresh())->test('notificaciones.index');
        $comp->assertSee('Ya leída')->assertSee('Sin leer');

        $comp->set('soloNoLeidas', true);
        $comp->assertDontSee('Ya leída')->assertSee('Sin leer');
    }

    public function test_marcar_todas_leidas_deja_el_conteo_en_cero(): void
    {
        $jefe = $this->jefeDeTaller();
        Notification::send($jefe, new OtNotificacion('Aviso 1', 'Cuerpo', null, 'info'));
        Notification::send($jefe->fresh(), new OtNotificacion('Aviso 2', 'Cuerpo', null, 'info'));

        Volt::actingAs($jefe->fresh())->test('notificaciones.index')->call('marcarTodasLeidas');

        $this->assertSame(0, $jefe->fresh()->unreadNotifications()->count());
    }
}
