<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Equipo;
use App\Models\Inventario;
use App\Models\OrdenTrabajo;
use App\Models\SolicitudInsumoOt;
use App\Models\Tecnico;
use App\Models\User;
use App\Services\OrdenTrabajo\AtencionInsumoOtService;
use App\Services\OrdenTrabajo\EstadoOtService;
use App\Services\OrdenTrabajo\OrdenTrabajoService;
use App\Services\OrdenTrabajo\PrestamoHerramientaService;
use App\Services\OrdenTrabajo\SalidaEquipoService;
use Illuminate\Database\Seeder;

/**
 * Datos de demostración del **flujo completo de OT** (spec 002, Phase 11 + 12).
 * Construye OT en todos los estados del ciclo usando los servicios reales, para
 * poder recorrer el proceso con los 5 roles sin cargar todo a mano.
 *
 * Requiere: RolesSeeder, UsuariosDemoSeeder, InventarioDemoSeeder,
 * PrioridadesSeeder, EstadosOtSeeder, CatalogosMaestrosSeeder.
 *
 * Idempotente por bandera: si ya hay OT creadas, no hace nada.
 * Reconstruir: `php artisan db:seed --class=FlujoOtDemoSeeder` sobre una BD sin OT.
 */
class FlujoOtDemoSeeder extends Seeder
{
    private User $jefe;
    private User $almacen;
    private User $admin;
    private Tecnico $carlos;
    private Tecnico $diana;
    private Tecnico $andres;
    private int $prioridadMedia;
    private int $prioridadAlta;

    public function run(): void
    {
        if (OrdenTrabajo::exists()) {
            $this->command?->warn('FlujoOtDemoSeeder: ya hay OT en la base, no se crean datos de demo.');

            return;
        }

        $this->jefe = User::where('email', 'jefe@servireparar.com')->firstOrFail();
        $this->almacen = User::where('email', 'almacen@servireparar.com')->firstOrFail();
        $this->admin = User::where('email', 'admin@servireparar.com')->firstOrFail();
        $this->carlos = Tecnico::whereRelation('usuario', 'email', 'tecnico1@servireparar.com')->firstOrFail();
        $this->diana = Tecnico::whereRelation('usuario', 'email', 'tecnico2@servireparar.com')->firstOrFail();
        $this->andres = Tecnico::whereRelation('usuario', 'email', 'tecnico3@servireparar.com')->firstOrFail();

        $this->prioridadMedia = \App\Models\Prioridad::where('nombre', 'Media')->value('id');
        $this->prioridadAlta = \App\Models\Prioridad::where('nombre', 'Alta')->value('id');

        $talma = Cliente::where('nombre', 'like', 'TALMA%')->firstOrFail();
        $avianca = Cliente::where('nombre', 'like', 'Avianca%')->firstOrFail();

        // Equipos del cliente (spec 005) para enlazar en algunas OT.
        $escalera = Equipo::firstOrCreate(
            ['cliente_id' => $talma->id, 'serie' => 'ESC-TALMA-014'],
            ['tipo' => 'Escalera de plataforma', 'marca' => 'JLG', 'modelo' => 'AV-800', 'ubicacion' => 'Rampa 3', 'estado' => 'operativo'],
        );
        Equipo::firstOrCreate(
            ['cliente_id' => $avianca->id, 'serie' => 'CMP-AV-221'],
            ['tipo' => 'Compresor industrial', 'marca' => 'Atlas Copco', 'modelo' => 'GA-30', 'ubicacion' => 'Bodega carga', 'estado' => 'operativo', 'periodicidad_mantenimiento_dias' => 90],
        );

        $electrodo = Inventario::where('codigo', 'CON-00003')->firstOrFail(); // Electrodo 6011
        $grasa = Inventario::where('codigo', 'CON-00001')->firstOrFail();     // Grasa EP2
        $disco = Inventario::where('codigo', 'CON-00002')->firstOrFail();     // Disco de corte
        $rodamiento = Inventario::where('codigo', 'REP-00001')->firstOrFail();

        $torquimetro = Inventario::where('codigo', 'HER-00001')->firstOrFail();
        $pulidora = Inventario::where('codigo', 'HER-00002')->firstOrFail();

        $ots = new OrdenTrabajoService();
        $estados = new EstadoOtService();
        $insumos = new AtencionInsumoOtService();
        $salida = new SalidaEquipoService();
        $prestamos = new PrestamoHerramientaService();

        // ── OT A · "Planificación" (recién creada, sin liberar) ──────────────
        $a = $ots->crear($this->jefe, [
            'cliente_id' => $talma->id,
            'equipo_id' => $escalera->id,
            'prioridad_id' => $this->prioridadAlta,
            'tipo_servicio' => 'taller',
            'descripcion' => 'Reparación estructural de escalera de plataforma: cambio de rodamientos y refuerzo de baranda.',
            'tiempo_estimado_dias' => 6,
            'valor_proyecto' => 4_200_000,
            'equipo_estado_ingreso' => 'Baranda con fisura, rodamientos con juego axial.',
        ], [
            ['uid' => 't1', 'descripcion' => 'Desmontaje y limpieza de conjunto rodante', 'tecnico_id' => $this->carlos->id,
                'dias_cumplimiento' => 2, 'insumos' => [['inventario_id' => $disco->id, 'cantidad' => 4]]],
            ['uid' => 't2', 'descripcion' => 'Cambio de rodamientos', 'tecnico_id' => $this->diana->id,
                'dias_cumplimiento' => 2,
                'insumos' => [['inventario_id' => $rodamiento->id, 'cantidad' => 2], ['inventario_id' => $grasa->id, 'cantidad' => 1]],
                'prerrequisitos' => ['t1']],
            ['uid' => 't3', 'descripcion' => 'Refuerzo y soldadura de baranda', 'tecnico_id' => $this->carlos->id,
                'dias_cumplimiento' => 2,
                'insumos' => [['inventario_id' => $electrodo->id, 'cantidad' => 3]],
                'prerrequisitos' => ['t2']],
        ]);

        // ── OT B · Liberada (Pendiente) con insumos en cola de Bodega ────────
        $b = $ots->crear($this->jefe, [
            'cliente_id' => $avianca->id,
            'prioridad_id' => $this->prioridadMedia,
            'tipo_servicio' => 'domicilio',
            'direccion_servicio' => 'Aeropuerto El Dorado, Bogotá — Bodega de carga',
            'descripcion' => 'Mantenimiento correctivo de compresor: fuga de aceite y ruido en acople.',
            'tiempo_estimado_dias' => 3,
            'valor_proyecto' => 2_600_000,
            'equipo_descripcion' => 'Compresor Atlas Copco GA-30',
            'equipo_marca' => 'Atlas Copco',
            'equipo_serie' => 'CMP-AV-221',
        ], [
            ['descripcion' => 'Sellado de fuga y cambio de empaques', 'tecnico_id' => $this->diana->id,
                'dias_cumplimiento' => 2, 'insumos' => [['inventario_id' => $grasa->id, 'cantidad' => 2]]],
            ['descripcion' => 'Alineación y ajuste de acople', 'tecnico_id' => $this->andres->id, 'dias_cumplimiento' => 1],
        ]);
        $estados->liberar($b->fresh(['estado']), $this->jefe);

        // ── OT C · En curso (insumos entregados, una tarea iniciada) ─────────
        $c = $ots->crear($this->jefe, [
            'cliente_id' => $talma->id,
            'prioridad_id' => $this->prioridadMedia,
            'tipo_servicio' => 'taller',
            'descripcion' => 'Fabricación de soporte metálico para banda transportadora.',
            'tiempo_estimado_dias' => 4,
            'valor_proyecto' => 3_100_000,
        ], [
            ['uid' => 'c1', 'descripcion' => 'Corte y armado de estructura', 'tecnico_id' => $this->carlos->id,
                'dias_cumplimiento' => 2,
                'insumos' => [['inventario_id' => $disco->id, 'cantidad' => 3], ['inventario_id' => $electrodo->id, 'cantidad' => 2]]],
            ['uid' => 'c2', 'descripcion' => 'Pintura y acabado', 'tecnico_id' => $this->andres->id,
                'dias_cumplimiento' => 2, 'prerrequisitos' => ['c1']],
        ]);
        $estados->liberar($c->fresh(['estado']), $this->jefe);
        foreach (SolicitudInsumoOt::where('ot_id', $c->id)->where('estado', 'pendiente')->get() as $sol) {
            $insumos->entregar($sol, $this->almacen);
        }
        // c1 lleva 4 días abierta con un plazo de 2 → aparece "atrasada".
        $tareaC1 = $c->tareas()->where('descripcion', 'like', 'Corte%')->first();
        $tareaC1->update(['estado_tarea' => 'en_curso', 'fecha_inicio' => now()->subDays(4)]);
        $this->evidenciaTarea($tareaC1->fresh()); // ya tiene evidencia → su botón "Finalizar" está habilitado en el demo
        $estados->recalcular($c->fresh(['estado', 'tareas', 'checklist']), $this->jefe);

        // ── OT D · Finalizada + salida SOLICITADA (espera al Administrador) ──
        $d = $ots->crear($this->jefe, [
            'cliente_id' => $avianca->id,
            'prioridad_id' => $this->prioridadAlta,
            'tipo_servicio' => 'taller',
            'descripcion' => 'Overhaul de motor eléctrico 20 HP: rebobinado (contratista) y cambio de rodamientos.',
            'tiempo_estimado_dias' => 5,
            'valor_proyecto' => 7_800_000,
        ], [
            ['descripcion' => 'Desmontaje e inspección', 'tecnico_id' => $this->andres->id],
            ['descripcion' => 'Montaje y pruebas', 'tecnico_id' => $this->diana->id,
                'insumos' => [['inventario_id' => $rodamiento->id, 'cantidad' => 2]]],
        ]);
        $estados->liberar($d->fresh(['estado']), $this->jefe);
        foreach (SolicitudInsumoOt::where('ot_id', $d->id)->where('estado', 'pendiente')->get() as $sol) {
            $insumos->entregar($sol, $this->almacen);
        }
        $d->manoObraContratistas()->create([
            'contratista_id' => \App\Models\Contratista::first()?->id,
            'especialidad' => 'Rebobinado de motores',
            'cantidad' => 1,
            'valor' => 1_450_000,
        ]);
        $this->finalizarTodo($d, $estados, dias: 4);
        $salida->solicitar($d->fresh(['estado']), $this->jefe);

        // ── OT E · Entregada (ciclo completo cerrado) ───────────────────────
        $e = $ots->crear($this->jefe, [
            'cliente_id' => $talma->id,
            'equipo_id' => $escalera->id,
            'prioridad_id' => $this->prioridadMedia,
            'tipo_servicio' => 'taller',
            'descripcion' => 'Cambio de ruedas y engrase general de escalera de plataforma.',
            'tiempo_estimado_dias' => 2,
            'valor_proyecto' => 1_900_000,
        ], [
            ['descripcion' => 'Cambio de ruedas', 'tecnico_id' => $this->carlos->id,
                'insumos' => [['inventario_id' => $grasa->id, 'cantidad' => 1]]],
        ]);
        $estados->liberar($e->fresh(['estado']), $this->jefe);
        foreach (SolicitudInsumoOt::where('ot_id', $e->id)->where('estado', 'pendiente')->get() as $sol) {
            $insumos->entregar($sol, $this->almacen);
        }
        $this->finalizarTodo($e, $estados, dias: 2);
        $salida->solicitar($e->fresh(['estado']), $this->jefe);
        $salida->aprobar($e->fresh(['estado']), $this->admin);
        $salida->confirmarEntrega($e->fresh(['estado']), $this->jefe, 'Firma conforme — Sr. Pérez');

        // ── OT F · Cancelada ────────────────────────────────────────────────
        $f = $ots->crear($this->jefe, [
            'cliente_id' => $avianca->id,
            'prioridad_id' => $this->prioridadMedia,
            'tipo_servicio' => 'domicilio',
            'direccion_servicio' => 'Zona Franca, Bogotá — Planta 2',
            'descripcion' => 'Diagnóstico de tablero eléctrico — el cliente canceló el servicio.',
            'tiempo_estimado_dias' => 1,
        ], [
            ['descripcion' => 'Visita de diagnóstico', 'tecnico_id' => $this->andres->id],
        ]);
        $ots->cancelarOt($f->fresh(['estado']), $this->jefe, 'El cliente resolvió internamente y canceló la solicitud.');

        // ── Préstamos de herramienta (fuera del ciclo de la OT) ─────────────
        $prestamos->solicitar($this->carlos, $torquimetro, $a->tareas()->first()); // queda "solicitada"
        $pDiana = $prestamos->solicitar($this->diana, $pulidora, $c->tareas()->first());
        $prestamos->entregar($pDiana->fresh(), $this->almacen); // queda "entregada"

        $this->command?->info('FlujoOtDemoSeeder: 6 OT de demo (A–F) + 2 préstamos de herramienta creados.');
    }

    /** Finaliza todas las tareas activas y responde el checklist para llevar la OT a "finalizada". */
    private function finalizarTodo(OrdenTrabajo $ot, EstadoOtService $estados, float $dias): void
    {
        foreach ($ot->tareas()->where('estado_tarea', '!=', 'cancelada')->get() as $t) {
            $this->evidenciaTarea($t);
            $t->update([
                'estado_tarea' => 'finalizada',
                'fecha_inicio' => now()->subDays((int) ceil($dias)),
                'fecha_fin' => now(),
                'dias_trabajados' => $dias,
                'finalizacion_solicitada_en' => null,
            ]);
        }

        $ot->checklist()->update(['cumple' => true]);
        $estados->recalcular($ot->fresh(['estado', 'tareas', 'checklist']), $this->jefe);
    }

    /** Adjunta una imagen de evidencia a la tarea (requisito para finalizarla, Phase 13). */
    private function evidenciaTarea(\App\Models\DetalleOt $tarea): void
    {
        $tarea->ordenTrabajo->evidencias()->firstOrCreate(
            ['detalle_ot_id' => $tarea->id, 'tipo_registro' => 'proceso'],
            [
                'tipo_archivo' => 'image/jpeg',
                'url_archivo' => 'evidencias-ot/demo.jpg',
                'descripcion' => 'Evidencia de la tarea (demo)',
                'subida_por' => $tarea->tecnico?->usuario_id ?? $this->jefe->id,
                'fecha_subida' => now(),
            ],
        );
    }
}
