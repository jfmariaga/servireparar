<?php

namespace Tests\Feature\Reportes;

use App\Enums\RolPrioridad;
use App\Models\CategoriaInventario;
use App\Models\EstadoOt;
use App\Models\Inventario;
use App\Models\OrdenTrabajo;
use App\Models\Tecnico;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Spec 007, US3 (FR-006/FR-007): exportar a Excel y PDF los listados de OT e
 * Inventario, respetando exactamente los filtros aplicados en pantalla.
 */
class ExportacionTest extends TestCase
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

    public function test_exportar_excel_de_ot_respeta_el_filtro_de_estado(): void
    {
        $abierta = OrdenTrabajo::factory()->enEstado(EstadoOt::EN_CURSO)->create(['descripcion' => 'Cambio de aceite']);
        OrdenTrabajo::factory()->enEstado(EstadoOt::FINALIZADA)->create(['descripcion' => 'Cambio de llantas']);

        $comp = Volt::actingAs($this->administrador())
            ->test('ordenes-trabajo.tablero')
            ->set('estado', 'en_curso')
            ->call('exportarExcel');

        $comp->assertFileDownloaded();

        $contenido = base64_decode(data_get($comp->effects, 'download.content'));
        $archivoTemp = tempnam(sys_get_temp_dir(), 'ot').'.xlsx';
        file_put_contents($archivoTemp, $contenido);

        $filas = IOFactory::load($archivoTemp)->getActiveSheet()->toArray();
        unlink($archivoTemp);

        $this->assertCount(2, $filas); // encabezado + 1 OT
        $this->assertSame($abierta->numero_ot, $filas[1][0]);
    }

    public function test_exportar_pdf_de_ot_devuelve_un_pdf_descargable(): void
    {
        OrdenTrabajo::factory()->enEstado(EstadoOt::EN_CURSO)->create();

        Volt::actingAs($this->administrador())
            ->test('ordenes-trabajo.tablero')
            ->call('exportarPdf')
            ->assertFileDownloaded(contentType: 'application/pdf');
    }

    public function test_exportar_ot_filtra_tambien_por_tecnico(): void
    {
        $tecnicoA = Tecnico::factory()->create();
        $tecnicoB = Tecnico::factory()->create();

        $otA = OrdenTrabajo::factory()->enEstado(EstadoOt::EN_CURSO)->create();
        $otA->tareas()->create(['descripcion' => 'Tarea A', 'tecnico_id' => $tecnicoA->id]);

        $otB = OrdenTrabajo::factory()->enEstado(EstadoOt::EN_CURSO)->create();
        $otB->tareas()->create(['descripcion' => 'Tarea B', 'tecnico_id' => $tecnicoB->id]);

        $comp = Volt::actingAs($this->administrador())
            ->test('ordenes-trabajo.tablero')
            ->set('tecnico', (string) $tecnicoA->id)
            ->call('exportarExcel');

        $contenido = base64_decode(data_get($comp->effects, 'download.content'));
        $archivoTemp = tempnam(sys_get_temp_dir(), 'ot').'.xlsx';
        file_put_contents($archivoTemp, $contenido);
        $filas = IOFactory::load($archivoTemp)->getActiveSheet()->toArray();
        unlink($archivoTemp);

        $this->assertCount(2, $filas);
        $this->assertSame($otA->numero_ot, $filas[1][0]);
    }

    public function test_exportar_excel_de_inventario_respeta_el_filtro_de_tipo(): void
    {
        $categoria = CategoriaInventario::factory()->create();
        $herramienta = Inventario::factory()->create(['categoria_id' => $categoria->id, 'tipo' => 'herramienta', 'nombre' => 'Taladro']);
        Inventario::factory()->create(['categoria_id' => $categoria->id, 'tipo' => 'consumible', 'nombre' => 'Aceite']);

        $comp = Volt::actingAs($this->administrador())
            ->test('inventario.catalogo')
            ->set('filtroTipo', 'herramienta')
            ->call('exportarExcel');

        $comp->assertFileDownloaded();

        $contenido = base64_decode(data_get($comp->effects, 'download.content'));
        $archivoTemp = tempnam(sys_get_temp_dir(), 'inv').'.xlsx';
        file_put_contents($archivoTemp, $contenido);
        $filas = IOFactory::load($archivoTemp)->getActiveSheet()->toArray();
        unlink($archivoTemp);

        $this->assertCount(2, $filas);
        $this->assertSame($herramienta->codigo, $filas[1][0]);
    }

    public function test_exportar_pdf_de_inventario_devuelve_un_pdf_descargable(): void
    {
        $categoria = CategoriaInventario::factory()->create();
        Inventario::factory()->create(['categoria_id' => $categoria->id]);

        Volt::actingAs($this->administrador())
            ->test('inventario.catalogo')
            ->call('exportarPdf')
            ->assertFileDownloaded(contentType: 'application/pdf');
    }
}
