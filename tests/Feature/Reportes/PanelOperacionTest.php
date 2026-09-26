<?php

namespace Tests\Feature\Reportes;

use App\Enums\RolPrioridad;
use App\Models\Cliente;
use App\Models\DetalleOt;
use App\Models\EstadoOt;
use App\Models\Inventario;
use App\Models\OrdenTrabajo;
use App\Models\PrestamoHerramienta;
use App\Models\SolicitudDespacho;
use App\Models\Tecnico;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Drill-downs del panel de operación embebido en el dashboard: tarjetas de OT
 * clicables (modal de listado) y desempeño por técnico inline (sin navegar a
 * /personal/desempeno), incluidas las tareas activas del técnico seleccionado.
 */
class PanelOperacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->assignRole(RolPrioridad::Administrador->value);

        return $admin;
    }

    public function test_abrir_ot_muestra_el_listado_de_la_categoria_y_cierra(): void
    {
        // En planificación (EN_REVISION, default de la factory): cuenta como
        // "abierta" pero no aparece en las tarjetas del piso, para no chocar
        // con esa otra sección al comprobar que el modal es lo único que la muestra.
        $ot = OrdenTrabajo::factory()->create(['tiempo_estimado_dias' => 30]);

        Volt::actingAs($this->actingAsAdmin())
            ->test('reportes.panel-operacion')
            ->assertDontSee($ot->numero_ot)
            ->call('abrirOt', 'abiertas')
            ->assertSee($ot->numero_ot)
            ->call('cerrarModal')
            ->assertDontSee($ot->numero_ot);
    }

    public function test_abrir_desempeno_lista_tecnicos_y_ver_tecnico_muestra_sus_tareas(): void
    {
        $usuario = User::factory()->create(['name' => 'Ana Técnica', 'estado' => 'activo']);
        $usuario->assignRole(RolPrioridad::Tecnico->value);
        $tecnico = Tecnico::factory()->for($usuario, 'usuario')->create();

        DetalleOt::factory()->create([
            'tecnico_id' => $tecnico->id,
            'estado_tarea' => 'finalizada',
            'dias_trabajados' => 2,
            'fecha_fin' => now(),
        ]);

        $otActiva = OrdenTrabajo::factory()->enEstado(EstadoOt::EN_CURSO)->create();
        DetalleOt::factory()->for($otActiva, 'ordenTrabajo')->create([
            'tecnico_id' => $tecnico->id,
            'estado_tarea' => 'en_curso',
            'descripcion' => 'Revisión de motor',
        ]);

        Volt::actingAs($this->actingAsAdmin())
            ->test('reportes.panel-operacion')
            ->call('abrirDesempeno')
            ->assertSee('Ana Técnica')
            ->call('verTecnico', $tecnico->id)
            ->assertSee('Ana Técnica')
            ->assertSee('Revisión de motor')
            ->assertSee('Tareas actuales')
            ->call('volverListaTecnicos')
            ->assertDontSee('Tareas actuales');
    }

    public function test_solo_administrador_y_jefe_de_taller_pueden_abrir_el_panel(): void
    {
        foreach ([RolPrioridad::Almacenista, RolPrioridad::Vendedor, RolPrioridad::Tecnico] as $rol) {
            $user = User::factory()->create(['estado' => 'activo']);
            $user->assignRole($rol->value);

            Volt::actingAs($user)->test('reportes.panel-operacion')->assertForbidden();
        }
    }

    public function test_jefe_de_taller_no_ve_el_boton_de_desempeno_ni_puede_abrirlo(): void
    {
        $jefe = User::factory()->create(['estado' => 'activo']);
        $jefe->assignRole(RolPrioridad::JefeDeTaller->value);

        Volt::actingAs($jefe)
            ->test('reportes.panel-operacion')
            ->assertDontSee('Ver desempeño por técnico')
            ->call('abrirDesempeno')
            ->assertDontSee('Desempeño por técnico');
    }

    public function test_tarjeta_de_herramientas_pendientes_muestra_las_danadas_y_en_mantenimiento(): void
    {
        $danada = Inventario::factory()->herramienta()->create(['nombre' => 'Taladro dañado', 'estado_herramienta' => 'dañada']);
        Inventario::factory()->herramienta()->create(['nombre' => 'Llave disponible', 'estado_herramienta' => 'disponible']);

        Volt::actingAs($this->actingAsAdmin())
            ->test('reportes.panel-operacion')
            ->assertDontSee('Taladro dañado')
            ->call('abrirSimple', 'herramientas')
            ->assertSee('Taladro dañado')
            ->assertDontSee('Llave disponible');
    }

    public function test_tarjeta_de_prestamos_sin_devolver_muestra_las_entregadas_y_no_las_devueltas(): void
    {
        $herramienta = Inventario::factory()->herramienta()->create(['nombre' => 'Multímetro']);
        PrestamoHerramienta::factory()->entregada()->create(['inventario_id' => $herramienta->id]);
        PrestamoHerramienta::factory()->devuelta()->create();

        Volt::actingAs($this->actingAsAdmin())
            ->test('reportes.panel-operacion')
            ->call('abrirSimple', 'prestamos')
            ->assertSee('Multímetro');
    }

    public function test_tarjeta_de_stock_bajo_solo_muestra_consumibles_por_debajo_del_minimo(): void
    {
        Inventario::factory()->create(['nombre' => 'Filtro de aceite', 'tipo' => 'consumible', 'stock_actual' => 2, 'stock_minimo' => 10]);
        Inventario::factory()->create(['nombre' => 'Aceite abundante', 'tipo' => 'consumible', 'stock_actual' => 100, 'stock_minimo' => 10]);

        Volt::actingAs($this->actingAsAdmin())
            ->test('reportes.panel-operacion')
            ->call('abrirSimple', 'stock')
            ->assertSee('Filtro de aceite')
            ->assertDontSee('Aceite abundante');
    }

    public function test_tarjeta_de_despachos_pendientes_incluye_con_mensajero_y_excluye_entregadas(): void
    {
        $cliente = Cliente::factory()->create(['nombre' => 'Avianca Cargo']);
        SolicitudDespacho::factory()->create(['numero' => 'SD-00001', 'cliente_id' => $cliente->id, 'estado' => 'remisionada']);
        SolicitudDespacho::factory()->create(['numero' => 'SD-00002', 'estado' => 'despachada']);
        SolicitudDespacho::factory()->create(['numero' => 'SD-00003', 'estado' => 'entregada']);
        SolicitudDespacho::factory()->create(['numero' => 'SD-00004', 'estado' => 'anulada']);

        Volt::actingAs($this->actingAsAdmin())
            ->test('reportes.panel-operacion')
            ->assertDontSee('Avianca Cargo')
            ->call('abrirSimple', 'despachos')
            ->assertSee('Avianca Cargo')
            ->assertSee('SD-00002')
            ->assertDontSee('SD-00003')
            ->assertDontSee('SD-00004');
    }

    public function test_tecnicos_disponibilidad_cuenta_libres_y_ocupados(): void
    {
        $usuarioLibre = User::factory()->create(['estado' => 'activo']);
        $usuarioLibre->assignRole(RolPrioridad::Tecnico->value);
        Tecnico::factory()->for($usuarioLibre, 'usuario')->create(['activo' => true]);

        $usuarioOcupado = User::factory()->create(['estado' => 'activo']);
        $usuarioOcupado->assignRole(RolPrioridad::Tecnico->value);
        $tecnicoOcupado = Tecnico::factory()->for($usuarioOcupado, 'usuario')->create(['activo' => true]);

        $otActiva = OrdenTrabajo::factory()->enEstado(EstadoOt::EN_CURSO)->create();
        DetalleOt::factory()->for($otActiva, 'ordenTrabajo')->create([
            'tecnico_id' => $tecnicoOcupado->id,
            'estado_tarea' => 'en_curso',
        ]);

        Volt::actingAs($this->actingAsAdmin())
            ->test('reportes.panel-operacion')
            ->assertSee('1 ocupado(s)');
    }

    public function test_modal_de_tecnicos_lista_nombres_de_libres_y_ocupados(): void
    {
        $usuarioLibre = User::factory()->create(['name' => 'Carlos Libre', 'estado' => 'activo']);
        $usuarioLibre->assignRole(RolPrioridad::Tecnico->value);
        Tecnico::factory()->for($usuarioLibre, 'usuario')->create(['activo' => true]);

        $usuarioOcupado = User::factory()->create(['name' => 'Bea Ocupada', 'estado' => 'activo']);
        $usuarioOcupado->assignRole(RolPrioridad::Tecnico->value);
        $tecnicoOcupado = Tecnico::factory()->for($usuarioOcupado, 'usuario')->create(['activo' => true]);

        $otActiva = OrdenTrabajo::factory()->enEstado(EstadoOt::EN_CURSO)->create();
        DetalleOt::factory()->for($otActiva, 'ordenTrabajo')->create([
            'tecnico_id' => $tecnicoOcupado->id,
            'estado_tarea' => 'en_curso',
        ]);

        Volt::actingAs($this->actingAsAdmin())
            ->test('reportes.panel-operacion')
            ->assertDontSee('Carlos Libre')
            ->call('abrirSimple', 'tecnicos')
            ->assertSee('Carlos Libre')
            ->assertSee('Bea Ocupada')
            ->assertSee('1 tarea(s) activa(s)');
    }

    public function test_tarjeta_de_ot_estancadas_muestra_tareas_en_curso_sin_avance_reciente(): void
    {
        $otActiva = OrdenTrabajo::factory()->enEstado(EstadoOt::EN_CURSO)->create();
        $tarea = DetalleOt::factory()->for($otActiva, 'ordenTrabajo')->create([
            'estado_tarea' => 'en_curso',
            'descripcion' => 'Cambio de correa',
        ]);
        $tarea->forceFill(['updated_at' => now()->subDays(5)])->saveQuietly();

        Volt::actingAs($this->actingAsAdmin())
            ->test('reportes.panel-operacion')
            ->assertSee('OT sin actividad reciente')
            ->call('abrirSimple', 'estancadas')
            ->assertSee('Cambio de correa');
    }
}
