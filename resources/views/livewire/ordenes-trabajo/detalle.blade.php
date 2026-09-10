<?php

use App\Models\ChecklistOt;
use App\Models\DetalleOt;
use App\Models\Inventario;
use App\Models\OrdenTrabajo;
use App\Models\Prioridad;
use App\Models\Tecnico;
use App\Livewire\Concerns\Notifies;
use App\Models\PrestamoHerramienta;
use App\Services\OrdenTrabajo\EstadoOtService;
use App\Services\OrdenTrabajo\OrdenTrabajoService;
use App\Services\OrdenTrabajo\PrestamoHerramientaService;
use App\Services\OrdenTrabajo\SalidaEquipoService;
use App\Services\OrdenTrabajo\SolicitudInsumoService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layout', ['title' => 'Orden de trabajo'])] class extends Component
{
    use Notifies, WithFileUploads;

    public OrdenTrabajo $ot;

    // Evidencia de proceso (nivel OT)
    public $evidencia = null;
    public string $evidenciaDescripcion = '';

    // Evidencia por tarea (imagen obligatoria antes de finalizar, Phase 13)
    public ?int $evidenciaTareaId = null;
    public $evidenciaTareaFile = null;

    // Checklist
    public string $nuevoItem = '';

    // Salida de equipo
    public string $motivoRechazoSalida = '';
    public string $firmaCliente = '';

    // Préstamo de herramienta que pide el técnico (Phase 12 / D15)
    public ?int $herramientaPrestamoId = null;

    // Cancelación de OT / tarea (Phase 11 / D8)
    public bool $cancelandoOt = false;
    public string $motivoCancelacionOt = '';
    public ?int $cancelandoTareaId = null;
    public string $motivoCancelacionTarea = '';

    // Corrección (US4)
    public bool $editandoCabecera = false;
    public ?int $prioridadId = null;
    public string $descripcion = '';
    public string $tipoServicio = 'taller';
    public string $direccionServicio = '';
    public string $tiempoEstimadoDias = '';
    // Alta / edición de tareas en una OT existente (US4 / FR-009)
    public bool $agregandoTarea = false;
    /** @var array<string, mixed> */
    public array $tareaForm = ['descripcion' => '', 'tecnico_id' => null, 'dias_cumplimiento' => '', 'insumos' => [], 'prerrequisitos' => []];
    public ?int $editandoTareaId = null;

    public function mount(OrdenTrabajo $ordenTrabajo): void
    {
        $this->ot = $ordenTrabajo;
        Gate::authorize('view', $this->ot);
        $this->syncCabecera();
    }

    private function syncCabecera(): void
    {
        $this->prioridadId = $this->ot->prioridad_id;
        $this->descripcion = $this->ot->descripcion;
        $this->tipoServicio = $this->ot->tipo_servicio;
        $this->direccionServicio = (string) ($this->ot->direccion_servicio ?? '');
        $this->tiempoEstimadoDias = (string) ($this->ot->tiempo_estimado_dias ?? '');
    }

    public function with(): array
    {
        $this->ot->load([
            'cliente', 'equipo', 'prioridad', 'estado', 'creadoPor',
            'tareas' => fn ($q) => $q->orderBy('orden')->orderBy('id'),
            'tareas.tecnico.usuario', 'tareas.insumos.inventario', 'tareas.insumos.solicitud', 'tareas.prerrequisitos', 'tareas.evidencias',
            'evidencias.subidaPor', 'checklist', 'eventos.usuario',
            'manoObraContratistas.contratista',
        ]);

        $tecnicoActual = auth()->user()->tecnico;

        $comprometido = \App\Models\SolicitudInsumoOt::comprometidas()
            ->selectRaw('inventario_id, SUM(cantidad) total')->groupBy('inventario_id')->pluck('total', 'inventario_id');

        return [
            'prioridades' => Prioridad::orderBy('nivel')->get(['id', 'nombre']),
            'insumos' => Inventario::activos()->where('tipo', 'consumible')->orderBy('nombre')
                ->get(['id', 'nombre', 'codigo', 'stock_actual'])
                ->map(fn (Inventario $i) => [
                    'id' => $i->id,
                    'nombre' => $i->nombre,
                    'codigo' => $i->codigo,
                    'disponible' => (float) $i->stock_actual - (float) ($comprometido[$i->id] ?? 0),
                ]),
            'tecnicos' => Tecnico::disponibles()->with('usuario:id,name')->get()
                ->map(fn (Tecnico $t) => ['id' => $t->id, 'nombre' => $t->usuario?->name ?? 'Técnico #'.$t->id]),
            'tecnicoActual' => $tecnicoActual,
            'herramientasParaPrestamo' => $tecnicoActual
                ? Inventario::activos()->where('tipo', 'herramienta')->where('estado_herramienta', 'disponible')
                    ->orderBy('nombre')->get(['id', 'nombre', 'codigo'])
                : collect(),
            'misPrestamos' => $tecnicoActual
                ? PrestamoHerramienta::where('tecnico_id', $tecnicoActual->id)
                    ->whereIn('estado', ['solicitada', 'entregada'])
                    ->with('inventario:id,nombre')->latest('id')->get()
                : collect(),
            'puedeGestionar' => $puedeGestionar = Gate::allows('update', $this->ot),
            'puedeEjecutar' => Gate::allows('executeTareas', $this->ot),
            'puedeAprobarSalida' => Gate::allows('approveEquipmentExit', $this->ot),
            'puedeSolicitarSalida' => Gate::allows('requestEquipmentExit', $this->ot),
            'puedeVerCosteo' => $puedeVerCosteo = Gate::allows('viewCosteo', $this->ot),
            'puedeFinalizar' => app(EstadoOtService::class)->puedeFinalizar($this->ot),
            // Vista reducida del técnico (Phase 13 / D19): solo sus tareas, sin
            // checklist, sin trazabilidad, sin enlaces a Bodega/herramientas.
            'vistaTecnico' => $tecnicoActual && ! $puedeGestionar && ! $puedeVerCosteo,
        ];
    }

    // --- Liberar OT (Phase 12 / D12) ---

    public function liberar(EstadoOtService $estados): void
    {
        Gate::authorize('update', $this->ot);
        try {
            $estados->liberar($this->ot, auth()->user());
        } catch (ValidationException $e) {
            $this->notifyError($e->getMessage());

            return;
        }
        $this->ot->refresh();
        $this->notifySuccess('OT liberada. Los técnicos ya pueden ejecutar sus tareas.');
    }

    /** @deprecated Alias de liberar() para compatibilidad. */
    public function planificar(EstadoOtService $estados): void
    {
        $this->liberar($estados);
    }

    // --- US2: ejecución de tareas ---

    public function iniciarTarea(int $tareaId, EstadoOtService $estados): void
    {
        Gate::authorize('executeTareas', $this->ot);

        $tarea = $this->ot->tareas()->with('prerrequisitos')->find($tareaId);
        $pendientes = $tarea?->prerrequisitosPendientes() ?? collect();

        if ($pendientes->isNotEmpty()) {
            $this->notifyError(sprintf(
                'La tarea «%s» requiere finalizar antes: %s.',
                str($tarea->descripcion)->limit(40),
                $pendientes->map(fn ($t) => '«'.str($t->descripcion)->limit(30).'»')->implode(', '),
            ));

            return;
        }

        $afectadas = DetalleOt::where('id', $tareaId)
            ->where('ot_id', $this->ot->id)
            ->where('estado_tarea', 'pendiente')
            ->update(['estado_tarea' => 'en_curso', 'fecha_inicio' => now()]);

        if (! $afectadas) {
            $this->notifyError('La tarea ya fue iniciada o no está pendiente.');

            return;
        }

        $estados->recalcular($this->ot->fresh(), auth()->user());
        $this->ot->refresh();
        $this->notifySuccess('Tarea iniciada.');
    }

    /**
     * El operario finaliza la tarea. Los días trabajados los calcula el sistema
     * automáticamente desde la fecha de inicio (Phase 13); no se piden al técnico.
     */
    public function finalizarTarea(int $tareaId, OrdenTrabajoService $servicio): void
    {
        Gate::authorize('executeTareas', $this->ot);

        $tarea = $this->ot->tareas()->findOrFail($tareaId);

        if ($tarea->estado_tarea !== 'en_curso') {
            $this->notifyError('Inicia la tarea antes de finalizarla.');

            return;
        }

        try {
            $tarea = $servicio->marcarTareaListaParaFinalizar($tarea, auth()->user());
        } catch (ValidationException $e) {
            $this->notifyError($e->getMessage());

            return;
        }

        $this->ot->refresh();
        $this->notifySuccess($tarea->finalizacionPendiente()
            ? 'Tarea marcada lista para finalizar. Falta la confirmación del Jefe (insumos sin entregar).'
            : 'Tarea finalizada.');
    }

    /** Sube una imagen de evidencia asociada a una tarea (obligatoria para finalizarla). */
    public function subirEvidenciaTarea(int $tareaId): void
    {
        Gate::authorize('executeTareas', $this->ot);
        $tarea = $this->ot->tareas()->findOrFail($tareaId);

        $this->validate([
            'evidenciaTareaFile' => 'required|image|max:10240',
        ], [], ['evidenciaTareaFile' => 'imagen']);

        $ruta = $this->evidenciaTareaFile->store('evidencias-ot', 'public');
        $this->ot->evidencias()->create([
            'detalle_ot_id' => $tarea->id,
            'tipo_registro' => 'proceso',
            'tipo_archivo' => $this->evidenciaTareaFile->getMimeType(),
            'url_archivo' => $ruta,
            'descripcion' => 'Evidencia de la tarea: '.str($tarea->descripcion)->limit(60),
            'subida_por' => auth()->id(),
            'fecha_subida' => now(),
        ]);
        $this->ot->registrarEvento('evidencia', sprintf('Evidencia de la tarea «%s» cargada.', str($tarea->descripcion)->limit(40)), auth()->user());

        $this->reset('evidenciaTareaFile', 'evidenciaTareaId');
        $this->ot->refresh();
        $this->notifySuccess('Evidencia de la tarea cargada.');
    }

    public function confirmarFinalizacionJefe(int $tareaId, OrdenTrabajoService $servicio): void
    {
        Gate::authorize('update', $this->ot);
        $tarea = $this->ot->tareas()->findOrFail($tareaId);

        try {
            $servicio->confirmarFinalizacionTarea($tarea, auth()->user());
        } catch (ValidationException $e) {
            $this->notifyError($e->getMessage());

            return;
        }

        $this->ot->refresh();
        $this->notifySuccess('Finalización de la tarea confirmada.');
    }

    public function subirEvidencia(): void
    {
        Gate::authorize('view', $this->ot);

        if ($this->ot->estaBloqueada()) {
            $this->notifyError('La OT está bloqueada (salida aprobada o entregada): no admite más cambios.');

            return;
        }

        $this->validate([
            'evidencia' => 'required|file|max:10240',
            'evidenciaDescripcion' => 'nullable|string|max:255',
        ], [], ['evidencia' => 'archivo']);

        $ruta = $this->evidencia->store('evidencias-ot', 'public');
        $this->ot->evidencias()->create([
            'tipo_registro' => 'proceso',
            'tipo_archivo' => $this->evidencia->getMimeType(),
            'url_archivo' => $ruta,
            'descripcion' => $this->evidenciaDescripcion ?: null,
            'subida_por' => auth()->id(),
            'fecha_subida' => now(),
        ]);
        $this->ot->registrarEvento('evidencia', 'Evidencia de proceso cargada.', auth()->user());
        $this->reset('evidencia', 'evidenciaDescripcion');
        $this->notifySuccess('Evidencia cargada.');
    }

    // --- US3: checklist ---

    public function agregarItemChecklist(): void
    {
        Gate::authorize('update', $this->ot);
        $this->validate(['nuevoItem' => 'required|string|max:255'], [], ['nuevoItem' => 'ítem']);
        $this->ot->checklist()->create(['item' => $this->nuevoItem]);
        $this->nuevoItem = '';
        $this->notifySuccess('Ítem agregado al checklist.');
    }

    public function responderChecklist(int $itemId, bool $cumple, EstadoOtService $estados): void
    {
        Gate::authorize('update', $this->ot);

        if (! $this->ot->tareasActivasFinalizadas()) {
            $this->notifyError('El checklist de cierre se habilita cuando todas las tareas de la OT están finalizadas.');

            return;
        }

        ChecklistOt::where('ot_id', $this->ot->id)->where('id', $itemId)->update(['cumple' => $cumple]);
        $estados->recalcular($this->ot->fresh(), auth()->user());
        $this->ot->refresh();
    }

    public function eliminarItemChecklist(int $itemId): void
    {
        Gate::authorize('update', $this->ot);
        ChecklistOt::where('ot_id', $this->ot->id)->where('id', $itemId)->delete();
    }

    // --- US3: salida de equipo ---

    public function solicitarSalida(SalidaEquipoService $salida): void
    {
        Gate::authorize('requestEquipmentExit', $this->ot);
        try {
            $salida->solicitar($this->ot, auth()->user());
        } catch (ValidationException $e) {
            $this->notifyError($e->getMessage());

            return;
        }
        $this->ot->refresh();
        $this->notifySuccess('Salida de equipo solicitada. A la espera de aprobación del Administrador.');
    }

    public function aprobarSalida(SalidaEquipoService $salida): void
    {
        Gate::authorize('approveEquipmentExit', $this->ot);
        try {
            $salida->aprobar($this->ot, auth()->user());
        } catch (ValidationException $e) {
            $this->notifyError($e->getMessage());

            return;
        }
        $this->ot->refresh();
        $this->notifySuccess('Salida aprobada.');
    }

    public function rechazarSalida(SalidaEquipoService $salida): void
    {
        Gate::authorize('approveEquipmentExit', $this->ot);
        $this->validate(['motivoRechazoSalida' => 'required|string|max:500'], [], ['motivoRechazoSalida' => 'motivo']);
        try {
            $salida->rechazar($this->ot, auth()->user(), $this->motivoRechazoSalida);
        } catch (ValidationException $e) {
            $this->notifyError($e->getMessage());

            return;
        }
        $this->motivoRechazoSalida = '';
        $this->ot->refresh();
        $this->notifySuccess('Salida rechazada. La OT vuelve a estar en curso.');
    }

    public function confirmarEntrega(SalidaEquipoService $salida): void
    {
        Gate::authorize('requestEquipmentExit', $this->ot);
        try {
            $salida->confirmarEntrega($this->ot, auth()->user(), $this->firmaCliente ?: null);
        } catch (ValidationException $e) {
            $this->notifyError($e->getMessage());

            return;
        }
        $this->firmaCliente = '';
        $this->ot->refresh();
        $this->notifySuccess('Entrega confirmada. OT '.$this->ot->numero_ot.' entregada.');
    }

    // --- Préstamo de herramienta que pide el técnico (Phase 12 / D15) ---

    public function solicitarPrestamo(PrestamoHerramientaService $svc): void
    {
        Gate::authorize('view', $this->ot);
        $tecnico = auth()->user()->tecnico;

        if (! $tecnico) {
            $this->notifyError('Solo un técnico puede pedir herramientas en préstamo.');

            return;
        }

        $this->validate(['herramientaPrestamoId' => 'required|exists:inventario,id'], [], ['herramientaPrestamoId' => 'herramienta']);

        $tareaPropia = $this->ot->tareas()->where('tecnico_id', $tecnico->id)->first();

        try {
            $svc->solicitar($tecnico, Inventario::findOrFail($this->herramientaPrestamoId), $tareaPropia);
        } catch (ValidationException $e) {
            $this->notifyError($e->getMessage());

            return;
        }

        $this->herramientaPrestamoId = null;
        $this->notifySuccess('Préstamo solicitado. Bodega debe entregarte la herramienta.');
    }

    // --- US4: correcciones ---

    public function editarCabecera(): void
    {
        Gate::authorize('update', $this->ot);
        $this->editandoCabecera = true;
        $this->syncCabecera();
    }

    public function guardarCabecera(OrdenTrabajoService $servicio): void
    {
        Gate::authorize('update', $this->ot);
        $datos = $this->validate([
            'prioridadId' => 'required|exists:prioridades,id',
            'descripcion' => 'required|string|max:2000',
            'tipoServicio' => 'required|in:taller,domicilio',
            'direccionServicio' => 'nullable|string|max:255|required_if:tipoServicio,domicilio',
            'tiempoEstimadoDias' => 'nullable|numeric|min:0',
        ], [
            'direccionServicio.required_if' => 'La dirección del servicio es obligatoria para OT a domicilio.',
        ], ['prioridadId' => 'prioridad', 'direccionServicio' => 'dirección del servicio']);

        try {
            $servicio->corregir($this->ot, auth()->user(), [
                'prioridad_id' => (int) $datos['prioridadId'],
                'descripcion' => $datos['descripcion'],
                'tipo_servicio' => $datos['tipoServicio'],
                'direccion_servicio' => $datos['tipoServicio'] === 'domicilio' ? ($datos['direccionServicio'] ?: null) : null,
                'tiempo_estimado_dias' => $datos['tiempoEstimadoDias'] !== '' ? (float) $datos['tiempoEstimadoDias'] : null,
            ]);
        } catch (ValidationException $e) {
            $this->notifyError($e->getMessage());

            return;
        }

        $this->editandoCabecera = false;
        $this->ot->refresh();
        $this->notifySuccess('OT corregida.');
    }

    // --- US4: alta / edición / baja de tareas en una OT existente ---

    private function resetTareaForm(): void
    {
        $this->tareaForm = ['descripcion' => '', 'tecnico_id' => null, 'dias_cumplimiento' => '', 'insumos' => [], 'prerrequisitos' => []];
    }

    /** Sube o baja una tarea en el orden de la lista (persiste `detalle_ot.orden`). */
    public function moverTarea(int $tareaId, string $direccion): void
    {
        Gate::authorize('update', $this->ot);

        $tareas = $this->ot->tareas()->orderBy('orden')->orderBy('id')->get();
        $pos = $tareas->search(fn ($t) => $t->id === $tareaId);
        $destino = $direccion === 'subir' ? $pos - 1 : $pos + 1;

        if ($pos === false || $destino < 0 || $destino >= $tareas->count()) {
            return;
        }

        $a = $tareas[$pos];
        $b = $tareas[$destino];
        [$ordenA, $ordenB] = [$a->orden, $b->orden];
        // Si el orden viene sin poblar (todo 0), normaliza con la posición.
        if ($ordenA === $ordenB) {
            [$ordenA, $ordenB] = [$pos + 1, $destino + 1];
        }
        $a->update(['orden' => $ordenB]);
        $b->update(['orden' => $ordenA]);
        $this->ot->refresh();
    }

    public function agregarInsumoForm(): void
    {
        $this->tareaForm['insumos'][] = ['inventario_id' => null, 'cantidad' => ''];
    }

    public function quitarInsumoForm(int $i): void
    {
        unset($this->tareaForm['insumos'][$i]);
        $this->tareaForm['insumos'] = array_values($this->tareaForm['insumos']);
    }

    public function nuevaTarea(): void
    {
        Gate::authorize('update', $this->ot);
        $this->editandoTareaId = null;
        $this->resetTareaForm();
        $this->agregandoTarea = true;
    }

    public function editarTarea(int $tareaId): void
    {
        Gate::authorize('update', $this->ot);
        $tarea = $this->ot->tareas()->with('insumos', 'prerrequisitos')->findOrFail($tareaId);

        if ($tarea->estado_tarea !== 'pendiente') {
            $this->notifyError('Solo se puede editar una tarea que aún no se ha iniciado.');

            return;
        }

        $this->agregandoTarea = false;
        $this->editandoTareaId = $tareaId;
        $this->tareaForm = [
            'descripcion' => $tarea->descripcion,
            'tecnico_id' => $tarea->tecnico_id,
            'dias_cumplimiento' => (string) ($tarea->dias_cumplimiento ?? ''),
            'insumos' => $tarea->insumos
                ->map(fn ($l) => ['inventario_id' => $l->inventario_id, 'cantidad' => (string) $l->cantidad])
                ->values()
                ->all(),
            'prerrequisitos' => $tarea->prerrequisitos->pluck('id')->map(fn ($id) => (string) $id)->all(),
        ];
    }

    public function cancelarTarea(): void
    {
        $this->agregandoTarea = false;
        $this->editandoTareaId = null;
        $this->resetTareaForm();
    }

    public function guardarTarea(OrdenTrabajoService $servicio): void
    {
        Gate::authorize('update', $this->ot);

        $datos = $this->validate([
            'tareaForm.descripcion' => 'required|string|max:1000',
            'tareaForm.tecnico_id' => 'required|exists:tecnicos,id',
            'tareaForm.dias_cumplimiento' => 'nullable|numeric|min:0.5',
            'tareaForm.insumos' => 'array',
            'tareaForm.insumos.*.inventario_id' => 'required|exists:inventario,id',
            'tareaForm.insumos.*.cantidad' => 'required|numeric|min:0.01',
            'tareaForm.prerrequisitos' => 'array',
            'tareaForm.prerrequisitos.*' => 'integer',
        ], [], [
            'tareaForm.descripcion' => 'descripción',
            'tareaForm.tecnico_id' => 'técnico',
            'tareaForm.dias_cumplimiento' => 'plazo de la tarea',
            'tareaForm.insumos.*.inventario_id' => 'insumo',
            'tareaForm.insumos.*.cantidad' => 'cantidad de insumo',
        ])['tareaForm'];

        $datos['prerrequisitos'] = array_map('intval', $datos['prerrequisitos'] ?? []);

        try {
            if ($this->editandoTareaId) {
                $tarea = $this->ot->tareas()->with('tecnico.usuario')->findOrFail($this->editandoTareaId);
                $servicio->actualizarTarea($tarea, auth()->user(), $datos);
                $msg = 'Tarea actualizada.';
            } else {
                $servicio->agregarTarea($this->ot, auth()->user(), $datos);
                $msg = 'Tarea agregada.';
            }
        } catch (ValidationException $e) {
            $this->notifyError($e->getMessage());

            return;
        }

        $this->cancelarTarea();
        $this->ot->refresh();
        $this->notifySuccess($msg);
    }

    public function quitarTarea(int $tareaId, OrdenTrabajoService $servicio): void
    {
        Gate::authorize('update', $this->ot);
        $tarea = $this->ot->tareas()->with('solicitudesInsumo')->findOrFail($tareaId);

        try {
            $servicio->quitarTarea($tarea, auth()->user());
        } catch (ValidationException $e) {
            $this->notifyError($e->getMessage());

            return;
        }

        $this->ot->refresh();
        $this->notifySuccess('Tarea eliminada.');
    }

    // --- Cancelación (Phase 11 / D8) ---

    public function cancelarTareaConfirmar(OrdenTrabajoService $servicio): void
    {
        Gate::authorize('update', $this->ot);
        $this->validate(['motivoCancelacionTarea' => 'required|string|max:500'], [], ['motivoCancelacionTarea' => 'motivo']);
        $tarea = $this->ot->tareas()->findOrFail($this->cancelandoTareaId);

        try {
            $servicio->cancelarTarea($tarea, auth()->user(), $this->motivoCancelacionTarea);
        } catch (ValidationException $e) {
            $this->notifyError($e->getMessage());

            return;
        }

        $this->cancelandoTareaId = null;
        $this->motivoCancelacionTarea = '';
        $this->ot->refresh();
        $this->notifySuccess('Tarea cancelada.');
    }

    public function cancelarOtConfirmar(OrdenTrabajoService $servicio): void
    {
        Gate::authorize('update', $this->ot);
        $this->validate(['motivoCancelacionOt' => 'required|string|max:500'], [], ['motivoCancelacionOt' => 'motivo']);

        try {
            $servicio->cancelarOt($this->ot, auth()->user(), $this->motivoCancelacionOt);
        } catch (ValidationException $e) {
            $this->notifyError($e->getMessage());

            return;
        }

        $this->cancelandoOt = false;
        $this->motivoCancelacionOt = '';
        $this->ot->refresh();
        $this->notifySuccess('OT '.$this->ot->numero_ot.' cancelada.');
    }
}; ?>

@php
    $estadoTono = match ($ot->estado?->slug) {
        'entregada' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
        'finalizada' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
        'en_curso' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
        default => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    };
    $nfmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
@endphp

<div class="w-full flex flex-col gap-6" x-data>
    @if (session('ok'))
        <div class="bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-300 text-sm rounded-lg px-4 py-2.5">{{ session('ok') }}</div>
    @endif

    @if ($ot->estaBloqueada() && $ot->estado?->slug !== 'entregada')
        <div class="bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300 text-sm rounded-lg px-4 py-2.5">
            OT congelada: la salida del equipo fue aprobada. No admite más cambios; solo queda confirmar la entrega al cliente.
        </div>
    @endif

    {{-- Barra superior --}}
    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ route('ordenes-trabajo.tablero') }}" wire:navigate class="text-sm text-slate-400 hover:text-brand-blue">← Tablero</a>
        <h1 class="text-xl font-bold font-display">{{ $ot->numero_ot }}</h1>
        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold {{ $estadoTono }}">{{ $ot->estado?->nombre }}</span>
        <div class="ml-auto flex gap-2">
            @if ($puedeVerCosteo)
                <a href="{{ route('ordenes-trabajo.costeo', $ot) }}" wire:navigate class="inline-flex items-center h-9 px-3.5 rounded-lg border border-slate-200 dark:border-slate-700 text-[12.5px] font-semibold hover:bg-slate-50 dark:hover:bg-slate-800">Costeo y utilidad</a>
            @endif
            @if ($puedeGestionar && $ot->estado?->slug === 'en_revision')
                <button wire:click="liberar" class="inline-flex items-center h-9 px-3.5 rounded-lg bg-brand-blue text-white text-[12.5px] font-semibold hover:bg-brand-blue-dark">Liberar OT</button>
            @endif
            @if ($puedeGestionar && ! $editandoCabecera)
                <button wire:click="editarCabecera" class="inline-flex items-center h-9 px-3.5 rounded-lg border border-slate-200 dark:border-slate-700 text-[12.5px] font-semibold hover:bg-slate-50 dark:hover:bg-slate-800">Corregir OT</button>
                <button wire:click="$set('cancelandoOt', true)" class="inline-flex items-center h-9 px-3.5 rounded-lg border border-brand-red/40 text-brand-red text-[12.5px] font-semibold hover:bg-red-50 dark:hover:bg-red-900/20">Cancelar OT</button>
            @endif
        </div>
    </div>

    @if ($cancelandoOt)
        <div class="bg-white dark:bg-slate-900 border border-brand-red/40 rounded-2xl p-4 flex flex-col gap-2">
            <p class="text-sm font-semibold text-brand-red">Cancelar la OT {{ $ot->numero_ot }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">Se liberan las reservas de insumo pendientes. La OT queda cerrada (estado terminal) y no se puede reabrir.</p>
            <textarea wire:model="motivoCancelacionOt" rows="2" placeholder="Motivo de la cancelación" class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/60 px-3 py-2 text-xs outline-none focus:border-brand-blue focus:ring-4 focus:ring-brand-blue/10"></textarea>
            @error('motivoCancelacionOt') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
            <div class="flex gap-2">
                <button wire:click="cancelarOtConfirmar" class="inline-flex items-center h-9 px-4 rounded-lg bg-brand-red text-white text-[12.5px] font-semibold hover:opacity-90">Confirmar cancelación</button>
                <button wire:click="$set('cancelandoOt', false)" class="inline-flex items-center h-9 px-4 rounded-lg border border-slate-200 dark:border-slate-700 text-[12.5px] font-semibold">Volver</button>
            </div>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3 items-start">

        {{-- ===================== Columna principal ===================== --}}
        <div class="xl:col-span-2 flex flex-col gap-6 min-w-0">

            {{-- Descripción / equipo --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex flex-col gap-4">
                @if ($editandoCabecera)
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field label="Descripción" class="sm:col-span-2">
                            <x-textarea wire:model="descripcion" rows="3" />
                            @error('descripcion') <x-slot:error>{{ $message }}</x-slot:error> @enderror
                        </x-field>
                        <x-field label="Prioridad">
                            <x-select wire:model="prioridadId" :placeholder="null">
                                @foreach ($prioridades as $p)<option value="{{ $p->id }}">{{ $p->nombre }}</option>@endforeach
                            </x-select>
                        </x-field>
                        <x-field label="Tipo de servicio">
                            <x-select wire:model.live="tipoServicio" :placeholder="null">
                                <option value="taller">Taller</option>
                                <option value="domicilio">Domicilio</option>
                            </x-select>
                        </x-field>
                        <x-field label="Tiempo estimado (días)">
                            <x-input type="number" step="0.5" min="0" wire:model="tiempoEstimadoDias" />
                        </x-field>
                        <x-field label="Dirección del servicio" class="sm:col-span-2" x-show="$wire.tipoServicio === 'domicilio'" x-cloak>
                            <x-input wire:model="direccionServicio" placeholder="Dónde se presta el servicio" />
                            @error('direccionServicio') <x-slot:error>{{ $message }}</x-slot:error> @enderror
                        </x-field>
                        <div class="sm:col-span-2 flex gap-2">
                            <button wire:click="guardarCabecera" class="inline-flex items-center h-10 px-4 rounded-xl bg-brand-blue hover:bg-brand-blue-dark text-white text-[13px] font-semibold">Guardar</button>
                            <button wire:click="$set('editandoCabecera', false)" class="inline-flex items-center h-10 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-[13px] font-semibold">Cancelar</button>
                        </div>
                    </div>
                @else
                    <div>
                        <h2 class="text-[13px] font-bold uppercase tracking-wide text-slate-400 mb-1.5">Servicio solicitado</h2>
                        <p class="text-sm leading-relaxed">{{ $ot->descripcion }}</p>
                        <p class="text-xs text-slate-400 mt-1.5">
                            <span class="capitalize">{{ $ot->tipo_servicio }}</span>@if ($ot->tipo_servicio === 'domicilio' && $ot->direccion_servicio) · {{ $ot->direccion_servicio }}@endif
                        </p>
                    </div>
                    @if ($ot->equipo_marca || $ot->equipo_descripcion || $ot->equipo_id || $ot->equipo_estado_ingreso)
                        <div class="grid gap-2 sm:grid-cols-2 text-sm border-t border-slate-100 dark:border-slate-800 pt-4">
                            <div><span class="text-slate-400">Equipo:</span> {{ $ot->equipo_descripcion ?? $ot->equipo?->tipo ?? '—' }}</div>
                            <div><span class="text-slate-400">Marca / Modelo:</span> {{ $ot->equipo_marca ?? $ot->equipo?->marca ?? '—' }} {{ $ot->equipo_modelo ?? $ot->equipo?->modelo }}</div>
                            <div><span class="text-slate-400">Serie:</span> {{ $ot->equipo_serie ?? $ot->equipo?->serie ?? '—' }}</div>
                            <div><span class="text-slate-400">Estado de ingreso:</span> {{ $ot->equipo_estado_ingreso ?? '—' }}</div>
                        </div>
                    @endif
                @endif
            </div>

            {{-- Tareas --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex flex-col gap-3">
                @php $tareasVista = $vistaTecnico ? $ot->tareas->where('tecnico_id', $tecnicoActual?->id)->values() : $ot->tareas; @endphp
                <div class="flex items-center justify-between">
                    <h2 class="font-bold text-sm">{{ $vistaTecnico ? 'Mis tareas' : 'Tareas' }} <span class="text-slate-400 font-normal">({{ $tareasVista->count() }})</span>
                        <span class="text-xs text-slate-400 font-normal">· {{ $tareasVista->where('estado_tarea', 'finalizada')->count() }} finalizadas</span>
                    </h2>
                    @if ($puedeGestionar && $ot->estado?->slug !== 'entregada' && ! $agregandoTarea && ! $editandoTareaId)
                        <button wire:click="nuevaTarea"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-brand-blue/30 bg-brand-blue-tint dark:bg-brand-navy-active px-2.5 py-1.5 text-[12px] font-semibold text-brand-blue dark:text-white hover:bg-brand-blue/15">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                            Agregar tarea
                        </button>
                    @endif
                </div>

                @error('tareaForm.descripcion') <p class="text-xs text-brand-red">{{ $message }}</p> @enderror

                {{-- Formulario de alta / edición de tarea --}}
                @if ($agregandoTarea || $editandoTareaId)
                    <div class="rounded-xl border border-brand-blue/30 bg-brand-blue-tint/40 dark:bg-brand-navy-active/40 p-4 flex flex-col gap-3">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-brand-blue dark:text-white">{{ $editandoTareaId ? 'Editar tarea' : 'Nueva tarea' }}</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-field label="Descripción" required class="sm:col-span-2">
                                <x-input wire:model="tareaForm.descripcion" placeholder="Qué se va a hacer" />
                                @error('tareaForm.descripcion') <x-slot:error>{{ $message }}</x-slot:error> @enderror
                            </x-field>
                            <x-field label="Técnico" required>
                                <x-select wire:model="tareaForm.tecnico_id" :reset-key="'tf-tec-'.($editandoTareaId ?? 'new')">
                                    @foreach ($tecnicos as $t)<option value="{{ $t['id'] }}">{{ $t['nombre'] }}</option>@endforeach
                                </x-select>
                                @error('tareaForm.tecnico_id') <x-slot:error>{{ $message }}</x-slot:error> @enderror
                            </x-field>
                            <x-field label="Plazo de la tarea (días)" hint="Debe caber en el tiempo estimado de la OT.">
                                <x-input type="number" step="0.5" min="0.5" wire:model="tareaForm.dias_cumplimiento" placeholder="Ej. 2" />
                                @error('tareaForm.dias_cumplimiento') <x-slot:error>{{ $message }}</x-slot:error> @enderror
                            </x-field>
                            <div class="sm:col-span-2 flex flex-col gap-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Insumos (opcional)</span>
                                    <button type="button" wire:click="agregarInsumoForm" class="text-[11px] font-semibold text-brand-blue hover:underline">+ Agregar insumo</button>
                                </div>
                                @foreach (($tareaForm['insumos'] ?? []) as $li => $linea)
                                    <div wire:key="tf-ins-{{ $li }}" class="grid grid-cols-[1fr_7rem_auto] gap-2 items-start">
                                        <x-select wire:model="tareaForm.insumos.{{ $li }}.inventario_id" :reset-key="'tf-ins-'.($editandoTareaId ?? 'new').'-'.$li">
                                            @foreach ($insumos as $ins)<option value="{{ $ins['id'] }}">{{ $ins['nombre'] }} — disp. {{ $nfmt($ins['disponible']) }}</option>@endforeach
                                        </x-select>
                                        <x-input type="number" step="0.01" min="0.01" placeholder="Cantidad" wire:model="tareaForm.insumos.{{ $li }}.cantidad" />
                                        <button type="button" wire:click="quitarInsumoForm({{ $li }})" class="h-10 px-2 text-slate-400 hover:text-brand-red text-sm">✕</button>
                                        @error('tareaForm.insumos.'.$li.'.inventario_id') <p class="col-span-3 text-xs text-brand-red">{{ $message }}</p> @enderror
                                        @error('tareaForm.insumos.'.$li.'.cantidad') <p class="col-span-3 text-xs text-brand-red">{{ $message }}</p> @enderror
                                    </div>
                                @endforeach
                                @if (empty($tareaForm['insumos'] ?? []))
                                    <p class="text-[11px] text-slate-400">Sin insumos. La tarea no generará solicitudes a Bodega.</p>
                                @endif
                            </div>
                            @php $candidatasPrereq = $ot->tareas->where('id', '!=', $editandoTareaId)->where('estado_tarea', '!=', 'cancelada'); @endphp
                            @if ($candidatasPrereq->isNotEmpty())
                                <div class="sm:col-span-2 flex flex-col gap-1.5">
                                    <span class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Depende de (finalizar antes)</span>
                                    <div class="flex flex-wrap gap-3">
                                        @foreach ($candidatasPrereq as $cand)
                                            <label wire:key="prereq-opt-{{ $cand->id }}" class="inline-flex items-center gap-1.5 text-xs">
                                                <input type="checkbox" value="{{ $cand->id }}" wire:model="tareaForm.prerrequisitos" class="rounded border-slate-300 dark:border-slate-600 text-brand-blue focus:ring-brand-blue/30">
                                                <span>{{ str($cand->descripcion)->limit(40) }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('prerrequisitos') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                                </div>
                            @endif
                        </div>
                        <div class="flex gap-2">
                            <button wire:click="guardarTarea" class="inline-flex items-center h-9 px-4 rounded-lg bg-brand-blue hover:bg-brand-blue-dark text-white text-[12.5px] font-semibold">Guardar</button>
                            <button wire:click="cancelarTarea" class="inline-flex items-center h-9 px-4 rounded-lg border border-slate-200 dark:border-slate-700 text-[12.5px] font-semibold">Cancelar</button>
                        </div>
                    </div>
                @endif

                <div class="grid gap-3 md:grid-cols-2">
                    @forelse ($tareasVista as $tarea)
                        @php
                            $prereqPend = $tarea->prerrequisitosPendientes();
                            $bloqueadaPrereq = $tarea->estado_tarea === 'pendiente' && $prereqPend->isNotEmpty();
                        @endphp
                        <div wire:key="tarea-{{ $tarea->id }}" class="border border-slate-200 dark:border-slate-800 rounded-xl p-4 flex flex-col gap-2 text-sm {{ $editandoTareaId === $tarea->id ? 'ring-2 ring-brand-blue/40' : '' }}">
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-medium leading-snug">{{ $tarea->descripcion }}</p>
                                <span class="shrink-0 inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold
                                    {{ $tarea->estado_tarea === 'finalizada' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : ($tarea->estado_tarea === 'en_curso' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' : ($tarea->estado_tarea === 'cancelada' ? 'bg-red-100 text-brand-red dark:bg-red-900/30' : 'bg-slate-100 dark:bg-slate-800')) }}">
                                    {{ str($tarea->estado_tarea)->replace('_', ' ')->ucfirst() }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-400">
                                {{ $tarea->tecnico?->usuario?->name ?? 'Técnico #'.$tarea->tecnico_id }}
                                @if ($tarea->estado_tarea === 'finalizada')<br>Días trabajados: {{ $nfmt($tarea->dias_trabajados) }}@endif
                            </p>
                            @if ($tarea->dias_cumplimiento !== null && ! in_array($tarea->estado_tarea, ['finalizada', 'cancelada'], true))
                                <p class="text-[11px] {{ $tarea->estaAtrasada() ? 'text-brand-red font-semibold' : 'text-slate-400' }}">
                                    Plazo: {{ $nfmt($tarea->dias_cumplimiento) }} día(s)@if ($tarea->fechaLimitePlazo()) · vence {{ $tarea->fechaLimitePlazo()->format('d/m/Y') }}@endif
                                    @if ($tarea->estaAtrasada()) · <span class="uppercase">Atrasada</span>@endif
                                </p>
                            @endif
                            @if ($tarea->prerrequisitos->isNotEmpty())
                                <p class="text-[11px] {{ $bloqueadaPrereq ? 'text-amber-600 dark:text-amber-400 font-semibold' : 'text-slate-400' }}">
                                    @if ($bloqueadaPrereq)Bloqueada — requiere finalizar: @else Requiere: @endif
                                    {{ $tarea->prerrequisitos->map(fn ($p) => '«'.\Illuminate\Support\Str::limit($p->descripcion, 30).'»')->implode(', ') }}
                                </p>
                            @endif
                            @if ($tarea->finalizacionPendiente())
                                <p class="text-[11px] text-amber-600 dark:text-amber-400 font-semibold">Lista para finalizar ({{ $nfmt($tarea->dias_trabajados) }} día(s)) — espera confirmación del Jefe.</p>
                            @endif
                            @foreach ($tarea->insumos as $linea)
                                @php $sol = $linea->solicitud; @endphp
                                <p class="text-[11px] flex items-start gap-1
                                    {{ $sol?->estado === 'entregada' ? 'text-emerald-600 dark:text-emerald-400' : ($sol && in_array($sol->estado, ['rechazada','cancelada'], true) ? 'text-brand-red' : 'text-amber-600 dark:text-amber-400') }}">
                                    <span class="font-semibold">{{ $linea->inventario?->nombre }}</span>
                                    <span>({{ $nfmt($linea->cantidad) }})</span>
                                    @if ($sol)
                                        —
                                        @if ($sol->estado === 'entregada') entregado por Bodega
                                        @elseif ($sol->estado === 'rechazada') rechazado por Bodega: {{ $sol->motivo_rechazo }}
                                        @elseif ($sol->estado === 'cancelada') línea cancelada
                                        @else pendiente en Bodega
                                        @endif
                                    @endif
                                </p>
                            @endforeach

                            {{-- Evidencia de la tarea (imagen obligatoria para finalizar, Phase 13) --}}
                            @if ($tarea->evidencias->isNotEmpty() || ($puedeEjecutar && $tarea->estado_tarea === 'en_curso'))
                                <div class="flex flex-col gap-1.5 pt-1">
                                    <span class="text-[11px] font-bold uppercase tracking-wide {{ $tarea->tieneEvidenciaImagen() || $tarea->estado_tarea !== 'en_curso' ? 'text-slate-400' : 'text-brand-red' }}">
                                        Evidencia {{ $tarea->tieneEvidenciaImagen() || $tarea->estado_tarea !== 'en_curso' ? '' : '(obligatoria para finalizar)' }}
                                    </span>
                                    @if ($tarea->evidencias->isNotEmpty())
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach ($tarea->evidencias as $ev)
                                                @php $u = \Illuminate\Support\Facades\Storage::disk('public')->url($ev->url_archivo); @endphp
                                                <a href="{{ $u }}" target="_blank" class="block w-12 h-12 rounded-lg overflow-hidden border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800">
                                                    @if (str_starts_with((string) $ev->tipo_archivo, 'image/'))
                                                        <img src="{{ $u }}" alt="" class="w-full h-full object-cover">
                                                    @else
                                                        <span class="flex items-center justify-center w-full h-full text-[10px] text-slate-400">arch.</span>
                                                    @endif
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                    @if ($puedeEjecutar && $tarea->estado_tarea === 'en_curso')
                                        <div class="flex flex-wrap items-center gap-2">
                                            <input type="file" accept="image/*" wire:model="evidenciaTareaFile" wire:key="evtf-{{ $tarea->id }}"
                                                   class="text-[11px] text-slate-500 dark:text-slate-400 file:mr-2 file:rounded-md file:border-0 file:bg-brand-blue-tint file:px-2 file:py-1 file:text-[11px] file:font-semibold file:text-brand-blue dark:file:bg-brand-navy-active dark:file:text-white">
                                            <button wire:click="subirEvidenciaTarea({{ $tarea->id }})" wire:loading.attr="disabled" wire:target="subirEvidenciaTarea,evidenciaTareaFile"
                                                    class="text-[11px] font-semibold px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-60">Subir imagen</button>
                                        </div>
                                        <div wire:loading wire:target="evidenciaTareaFile" class="text-[10px] text-slate-400">Cargando…</div>
                                        @error('evidenciaTareaFile') <span class="text-brand-red text-[11px]">{{ $message }}</span> @enderror
                                    @endif
                                </div>
                            @endif

                            <div class="flex flex-wrap items-center gap-2 pt-1">
                                @if ($puedeGestionar && $ot->estado?->slug !== 'entregada' && $ot->tareas->count() > 1)
                                    <span class="inline-flex rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
                                        <button wire:click="moverTarea({{ $tarea->id }}, 'subir')" @disabled($loop->first) class="px-2 py-1.5 text-[12px] text-slate-400 hover:text-brand-blue disabled:opacity-30" title="Subir">↑</button>
                                        <button wire:click="moverTarea({{ $tarea->id }}, 'bajar')" @disabled($loop->last) class="px-2 py-1.5 text-[12px] text-slate-400 hover:text-brand-blue disabled:opacity-30 border-l border-slate-200 dark:border-slate-700" title="Bajar">↓</button>
                                    </span>
                                @endif
                                @if ($puedeEjecutar && $tarea->estado_tarea === 'pendiente')
                                    @if ($bloqueadaPrereq)
                                        <button type="button" disabled title="Finaliza primero: {{ $prereqPend->map(fn ($p) => $p->descripcion)->implode(', ') }}" class="text-[12px] font-semibold px-3 py-1.5 rounded-lg bg-slate-200 text-slate-400 dark:bg-slate-800 cursor-not-allowed">Iniciar</button>
                                    @else
                                        <button wire:click="iniciarTarea({{ $tarea->id }})" class="text-[12px] font-semibold px-3 py-1.5 rounded-lg bg-brand-blue text-white hover:bg-brand-blue-dark">Iniciar</button>
                                    @endif
                                @endif
                                @if ($puedeEjecutar && $tarea->estado_tarea === 'en_curso')
                                    @if (! $tarea->tieneEvidenciaImagen())
                                        <button type="button" disabled title="Sube una imagen de evidencia antes de finalizar" class="text-[12px] font-semibold px-3 py-1.5 rounded-lg bg-slate-200 text-slate-400 dark:bg-slate-800 cursor-not-allowed">Finalizar</button>
                                    @else
                                        <button type="button"
                                                x-on:click="Notify.confirmDanger({
                                                    title: '¿Finalizar esta tarea?',
                                                    text: 'El sistema registrará automáticamente los días trabajados desde el inicio.',
                                                    confirmButtonText: 'Sí, finalizar',
                                                }).then((ok) => ok && $wire.finalizarTarea({{ $tarea->id }}))"
                                                class="text-[12px] font-semibold px-3 py-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Finalizar</button>
                                    @endif
                                @endif
                                @if ($puedeGestionar && $tarea->finalizacionPendiente())
                                    <button wire:click="confirmarFinalizacionJefe({{ $tarea->id }})" class="text-[12px] font-semibold px-3 py-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Confirmar finalización</button>
                                @endif
                                @if ($puedeGestionar && $tarea->estado_tarea === 'pendiente')
                                    <button wire:click="editarTarea({{ $tarea->id }})" class="text-[12px] px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800">Editar</button>
                                @endif
                                @if ($puedeGestionar && $tarea->estado_tarea === 'pendiente' && $ot->tareas->count() > 1)
                                    <button type="button"
                                            x-on:click="Notify.confirmDanger({
                                                title: '¿Quitar esta tarea?',
                                                text: {{ Js::from('Se quitará «'.$tarea->descripcion.'» de la OT.') }},
                                                confirmButtonText: 'Sí, quitar',
                                            }).then((ok) => ok && $wire.quitarTarea({{ $tarea->id }}))"
                                            class="text-[12px] px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-400 hover:text-brand-red hover:border-brand-red/40">Quitar</button>
                                @endif
                                @if ($puedeGestionar && in_array($tarea->estado_tarea, ['pendiente', 'en_curso'], true))
                                    @if ($cancelandoTareaId === $tarea->id)
                                        <div class="flex flex-wrap items-center gap-2 w-full">
                                            <input type="text" wire:model="motivoCancelacionTarea" placeholder="Motivo de la cancelación" class="flex-1 min-w-[12rem] h-9 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/60 px-3 text-xs outline-none focus:border-brand-blue focus:ring-4 focus:ring-brand-blue/10">
                                            <button wire:click="cancelarTareaConfirmar" class="text-[12px] font-semibold px-3 py-1.5 rounded-lg border border-brand-red text-brand-red">Confirmar</button>
                                            <button wire:click="$set('cancelandoTareaId', null)" class="text-[12px] px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700">Cerrar</button>
                                            @error('motivoCancelacionTarea') <span class="text-brand-red text-xs w-full">{{ $message }}</span> @enderror
                                        </div>
                                    @else
                                        <button wire:click="$set('cancelandoTareaId', {{ $tarea->id }})" class="text-[12px] px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-400 hover:text-brand-red hover:border-brand-red/40">Cancelar tarea</button>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 md:col-span-2">{{ $vistaTecnico ? 'No tienes tareas en esta OT.' : 'Sin tareas.' }}</p>
                    @endforelse
                </div>
            </div>

            {{-- Checklist (oculto en la vista del técnico) --}}
            @unless ($vistaTecnico)
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex flex-col gap-3">
                @php
                    $chkSinResponder = $ot->checklist->whereNull('cumple')->count();
                    $tareasActivasListas = $ot->tareasActivasFinalizadas();
                @endphp
                <div class="flex items-center justify-between">
                    <h2 class="font-bold text-sm">Checklist de cierre</h2>
                    @if ($puedeFinalizar)
                        <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">Listo para finalizar</span>
                    @else
                        <span class="text-xs text-amber-600 dark:text-amber-400">Pendiente para poder finalizar</span>
                    @endif
                </div>
                @if ($puedeGestionar && ! $tareasActivasListas)
                    <p class="text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/40 rounded-lg px-3 py-2">
                        El checklist se habilita para responder cuando todas las tareas de la OT estén finalizadas.
                    </p>
                @endif
                @if (! $puedeFinalizar && $tareasActivasListas && $chkSinResponder > 0)
                    <p class="text-xs text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 rounded-lg px-3 py-2">
                        Todas las tareas están listas. Faltan {{ $chkSinResponder }} respuesta(s) del checklist para finalizar la OT.
                    </p>
                @elseif (! $puedeFinalizar && $tareasActivasListas && $ot->checklist->isEmpty())
                    <p class="text-xs text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 rounded-lg px-3 py-2">
                        Todas las tareas están listas. Agrega y responde al menos un ítem de checklist para finalizar la OT.
                    </p>
                @endif
                <div class="flex flex-col">
                    @forelse ($ot->checklist as $item)
                        <div wire:key="chk-{{ $item->id }}" class="flex items-center justify-between gap-3 text-sm border-b border-slate-50 dark:border-slate-800/60 py-2.5">
                            <span class="flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full {{ $item->cumple === null ? 'bg-slate-300' : ($item->cumple ? 'bg-emerald-500' : 'bg-brand-red') }}"></span>
                                {{ $item->item }}
                            </span>
                            <div class="flex items-center gap-2 shrink-0">
                                @if ($puedeGestionar && $tareasActivasListas)
                                    <button wire:click="responderChecklist({{ $item->id }}, true)" class="text-[11px] font-bold px-2.5 py-1 rounded-md {{ $item->cumple === true ? 'bg-emerald-600 text-white' : 'bg-slate-100 dark:bg-slate-800' }}">SÍ</button>
                                    <button wire:click="responderChecklist({{ $item->id }}, false)" class="text-[11px] font-bold px-2.5 py-1 rounded-md {{ $item->cumple === false ? 'bg-brand-red text-white' : 'bg-slate-100 dark:bg-slate-800' }}">NO</button>
                                @else
                                    <span class="text-xs font-semibold">{{ $item->cumple === null ? 'Pendiente' : ($item->cumple ? 'Sí' : 'No') }}</span>
                                @endif
                                @if ($puedeGestionar)
                                    <button wire:click="eliminarItemChecklist({{ $item->id }})" class="text-[11px] text-slate-400 hover:text-brand-red">✕</button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 py-2">Sin ítems de checklist todavía.</p>
                    @endforelse
                </div>
                @if ($puedeGestionar)
                    <div class="flex gap-2 pt-1">
                        <x-input wire:model="nuevoItem" placeholder="Nuevo ítem de checklist" class="flex-1" wire:keydown.enter="agregarItemChecklist" />
                        <button wire:click="agregarItemChecklist" class="inline-flex items-center h-11 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-[12.5px] font-semibold hover:bg-slate-50 dark:hover:bg-slate-800">Agregar</button>
                    </div>
                    @error('nuevoItem') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                @endif
            </div>
            @endunless

            {{-- Evidencias --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex flex-col gap-4">
                <h2 class="font-bold text-sm">Evidencias</h2>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    @forelse ($ot->evidencias as $ev)
                        @php $esImg = str((string) $ev->tipo_archivo)->startsWith('image/'); @endphp
                        <div wire:key="ev-{{ $ev->id }}" class="rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden flex flex-col">
                            <div class="aspect-square bg-slate-50 dark:bg-slate-800/60 flex items-center justify-center overflow-hidden">
                                @if ($esImg)
                                    <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($ev->url_archivo) }}" target="_blank">
                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($ev->url_archivo) }}" alt="{{ $ev->descripcion }}" class="w-full h-full object-cover">
                                    </a>
                                @else
                                    <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($ev->url_archivo) }}" target="_blank" class="flex flex-col items-center gap-1 text-slate-400 text-xs p-2">
                                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M14 3v5h5M14 3H6a2 2 0 00-2 2v14a2 2 0 002 2h12a2 2 0 002-2V8l-6-5z"/></svg>
                                        Archivo
                                    </a>
                                @endif
                            </div>
                            <div class="p-2 text-[11px] leading-tight">
                                <span class="inline-block rounded bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 font-semibold capitalize">{{ $ev->tipo_registro }}</span>
                                <p class="text-slate-500 dark:text-slate-400 mt-1 line-clamp-2">{{ $ev->descripcion ?? '—' }}</p>
                                <p class="text-slate-400 mt-0.5">{{ $ev->fecha_subida?->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 col-span-full">Sin evidencias cargadas.</p>
                    @endforelse
                </div>

                <div class="border-t border-slate-100 dark:border-slate-800 pt-4 flex flex-col sm:flex-row sm:items-start gap-3" @if ($ot->estaBloqueada()) hidden @endif>
                    @if ($evidencia)
                        <div class="shrink-0">
                            @if (str((string) $evidencia->getMimeType())->startsWith('image/'))
                                <img src="{{ $evidencia->temporaryUrl() }}" class="h-24 w-24 rounded-xl object-cover border border-slate-200 dark:border-slate-700">
                            @else
                                <div class="h-24 w-24 rounded-xl border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-400 text-xs">{{ str($evidencia->getClientOriginalName())->limit(14) }}</div>
                            @endif
                        </div>
                    @endif
                    <div class="flex-1 flex flex-col gap-2">
                        <input type="file" wire:model="evidencia"
                               class="w-full text-xs text-slate-500 dark:text-slate-400 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-blue-tint file:px-3 file:py-2 file:text-[12px] file:font-semibold file:text-brand-blue hover:file:bg-brand-blue/15 dark:file:bg-brand-navy-active dark:file:text-white cursor-pointer">
                        <div wire:loading wire:target="evidencia" class="text-xs text-slate-400">Cargando previsualización…</div>
                        <div class="flex gap-2">
                            <x-input wire:model="evidenciaDescripcion" placeholder="Descripción (opcional)" class="flex-1 !h-10 text-xs" />
                            <button wire:click="subirEvidencia" wire:loading.attr="disabled" wire:target="subirEvidencia,evidencia"
                                    class="inline-flex items-center h-10 px-4 rounded-xl bg-brand-blue hover:bg-brand-blue-dark text-white text-[12.5px] font-semibold disabled:opacity-60">Subir</button>
                        </div>
                        @error('evidencia') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================== Columna lateral ===================== --}}
        <div class="flex flex-col gap-6 xl:sticky xl:top-6">

            {{-- Resumen --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex flex-col gap-3 text-sm">
                <h2 class="font-bold text-sm">Resumen</h2>
                <dl class="grid grid-cols-3 gap-y-2.5">
                    <dt class="text-slate-400 col-span-1">Cliente</dt><dd class="col-span-2 font-medium">{{ $ot->cliente?->nombre }}</dd>
                    <dt class="text-slate-400 col-span-1">Tipo</dt><dd class="col-span-2 capitalize">{{ $ot->tipo_servicio }}</dd>
                    <dt class="text-slate-400 col-span-1">Prioridad</dt><dd class="col-span-2">{{ $ot->prioridad?->nombre }}</dd>
                    <dt class="text-slate-400 col-span-1">Estimado</dt><dd class="col-span-2">{{ $ot->tiempo_estimado_dias ? $nfmt($ot->tiempo_estimado_dias).' día(s)' : '—' }}</dd>
                    <dt class="text-slate-400 col-span-1">Trabajado</dt><dd class="col-span-2">{{ $nfmt($ot->diasTrabajadosTotales()) }} día(s)</dd>
                    @if ($ot->desviacionDias() !== null)
                        <dt class="text-slate-400 col-span-1">Desviación</dt>
                        <dd class="col-span-2 font-semibold {{ $ot->desviacionDias() > 0 ? 'text-brand-red' : 'text-emerald-600 dark:text-emerald-400' }}">{{ $ot->desviacionDias() > 0 ? '+' : '' }}{{ $nfmt($ot->desviacionDias()) }} día(s)</dd>
                    @endif
                    <dt class="text-slate-400 col-span-1">Creada</dt><dd class="col-span-2">{{ $ot->created_at?->format('d/m/Y') }} · {{ $ot->creadoPor?->name }}</dd>
                    @if ($ot->fecha_finalizacion)
                        <dt class="text-slate-400 col-span-1">Finalizada</dt><dd class="col-span-2">{{ $ot->fecha_finalizacion->format('d/m/Y') }}</dd>
                    @endif
                    @if ($ot->fecha_entrega)
                        <dt class="text-slate-400 col-span-1">Entregada</dt><dd class="col-span-2">{{ $ot->fecha_entrega->format('d/m/Y') }}</dd>
                    @endif
                    @if ($ot->valor_proyecto !== null)
                        <dt class="text-slate-400 col-span-1">Valor</dt><dd class="col-span-2 font-semibold">{{ \App\Support\Moneda::cop($ot->valor_proyecto) }}</dd>
                    @endif
                </dl>
            </div>

            {{-- Salida de equipo --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex flex-col gap-3 text-sm">
                <h2 class="font-bold text-sm">Salida de equipo y entrega</h2>
                <p class="text-xs">Estado: <span class="font-semibold capitalize">{{ str($ot->salida_estado)->replace('_', ' ') }}</span></p>

                @if ($ot->salida_estado === 'rechazada' && $ot->salida_motivo_rechazo)
                    <p class="text-xs text-brand-red bg-red-50 dark:bg-red-900/20 rounded-lg px-3 py-2">Último rechazo: {{ $ot->salida_motivo_rechazo }}</p>
                @endif

                @if ($ot->estado?->slug !== 'finalizada' && $ot->estado?->slug !== 'entregada' && $ot->salida_estado === 'no_solicitada')
                    <p class="text-xs text-slate-400">Disponible cuando la OT esté finalizada.</p>
                @endif

                <div class="flex flex-col gap-2">
                    @if ($puedeSolicitarSalida && in_array($ot->salida_estado, ['no_solicitada', 'rechazada'], true) && $ot->estado?->slug === 'finalizada')
                        <button wire:click="solicitarSalida" class="w-full inline-flex items-center justify-center h-10 rounded-xl bg-brand-blue text-white text-[12.5px] font-semibold hover:bg-brand-blue-dark">Solicitar salida</button>
                    @endif

                    @if ($puedeAprobarSalida && $ot->salida_estado === 'solicitada')
                        <button wire:click="aprobarSalida" class="w-full inline-flex items-center justify-center h-10 rounded-xl bg-emerald-600 text-white text-[12.5px] font-semibold hover:bg-emerald-700">Aprobar salida</button>
                        <input type="text" wire:model="motivoRechazoSalida" placeholder="Motivo del rechazo" class="w-full h-10 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/60 px-3 text-xs outline-none focus:border-brand-blue focus:ring-4 focus:ring-brand-blue/10">
                        <button wire:click="rechazarSalida" class="w-full inline-flex items-center justify-center h-10 rounded-xl border border-brand-red text-brand-red text-[12.5px] font-semibold hover:bg-red-50 dark:hover:bg-red-900/20">Rechazar</button>
                        @error('motivoRechazoSalida') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                    @endif

                    @if ($puedeSolicitarSalida && $ot->salida_estado === 'aprobada' && $ot->estado?->slug !== 'entregada')
                        <input type="text" wire:model="firmaCliente" placeholder="Firma / conformidad del cliente (opcional)" class="w-full h-10 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/60 px-3 text-xs outline-none focus:border-brand-blue focus:ring-4 focus:ring-brand-blue/10">
                        <button wire:click="confirmarEntrega" class="w-full inline-flex items-center justify-center h-10 rounded-xl bg-emerald-600 text-white text-[12.5px] font-semibold hover:bg-emerald-700">Confirmar entrega</button>
                    @endif

                    @if ($ot->estado?->slug === 'entregada')
                        <p class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold">✓ Equipo entregado al cliente.</p>
                    @endif
                </div>
            </div>

            {{-- Herramientas en préstamo del técnico (Phase 12 / D15) --}}
            @if ($tecnicoActual)
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex flex-col gap-3 text-sm">
                    <div class="flex items-center justify-between">
                        <h2 class="font-bold text-sm">Mis herramientas en préstamo</h2>
                        @unless ($vistaTecnico)
                            <a href="{{ route('prestamos-herramienta') }}" wire:navigate class="text-[11px] font-semibold text-brand-blue hover:underline">Ver todo →</a>
                        @endunless
                    </div>
                    @forelse ($misPrestamos as $p)
                        <div wire:key="prh-{{ $p->id }}" class="flex items-center justify-between gap-2 border-b border-slate-50 dark:border-slate-800/60 pb-2 last:border-0">
                            <span>{{ $p->inventario?->nombre }}</span>
                            <span class="text-[11px] font-semibold {{ $p->estado === 'entregada' ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400' }}">
                                {{ $p->estado === 'entregada' ? 'en tu poder' : 'solicitada' }} · {{ $p->solicitada_en?->format('d/m/Y') }}
                            </span>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400">No tienes herramientas en préstamo.</p>
                    @endforelse

                    <div class="flex flex-wrap items-center gap-2 pt-1">
                        <x-select wire:model="herramientaPrestamoId" :reset-key="'prh-'.$misPrestamos->count()" class="flex-1 min-w-[10rem]">
                            @foreach ($herramientasParaPrestamo as $hd)<option value="{{ $hd->id }}">{{ $hd->nombre }} ({{ $hd->codigo }})</option>@endforeach
                        </x-select>
                        <button wire:click="solicitarPrestamo" class="text-[12px] font-semibold px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800">Solicitar</button>
                    </div>
                    @error('herramientaPrestamoId') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                </div>
            @endif

            {{-- Insumos de la OT (consolidado) — oculto en la vista del técnico --}}
            @php $lineasOt = $ot->tareas->flatMap(fn ($t) => $t->insumos); @endphp
            @if ($lineasOt->isNotEmpty() && ! $vistaTecnico)
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex flex-col gap-3 text-sm">
                    <div class="flex items-center justify-between">
                        <h2 class="font-bold text-sm">Insumos de la OT</h2>
                        <a href="{{ route('insumos-ot') }}" wire:navigate class="text-[11px] font-semibold text-brand-blue hover:underline">Ver en Bodega →</a>
                    </div>
                    <table class="w-full text-xs">
                        <tbody>
                            @foreach ($lineasOt as $linea)
                                @php $sol = $linea->solicitud; @endphp
                                <tr wire:key="ot-ins-{{ $linea->id }}" class="border-b border-slate-50 dark:border-slate-800/60 last:border-0">
                                    <td class="py-1.5 pr-2">{{ $linea->inventario?->nombre }}</td>
                                    <td class="py-1.5 pr-2 text-right tabular-nums">{{ $nfmt($linea->cantidad) }}</td>
                                    <td class="py-1.5 text-right">
                                        <span class="font-semibold {{ $sol?->estado === 'entregada' ? 'text-emerald-600 dark:text-emerald-400' : ($sol && in_array($sol->estado, ['rechazada','cancelada'], true) ? 'text-brand-red' : 'text-amber-600 dark:text-amber-400') }}">
                                            {{ $sol?->estado ?? 'sin solicitud' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @php $pendientes = $lineasOt->filter(fn ($l) => optional($l->solicitud)->estado === 'pendiente')->count(); @endphp
                    @if ($pendientes > 0)
                        <p class="text-[11px] text-amber-600 dark:text-amber-400">{{ $pendientes }} insumo(s) aún sin entregar por Bodega.</p>
                    @endif
                </div>
            @endif

            {{-- Trazabilidad (oculta en la vista del técnico) --}}
            @unless ($vistaTecnico)
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex flex-col gap-2 text-sm">
                <h2 class="font-bold text-sm">Trazabilidad</h2>
                <div class="flex flex-col gap-0 max-h-96 overflow-y-auto -mx-1 px-1">
                    @foreach ($ot->eventos as $evento)
                        <div wire:key="evt-{{ $evento->id }}" class="border-b border-slate-50 dark:border-slate-800/60 py-2 last:border-0">
                            <div class="flex items-center gap-2 text-[11px]">
                                <span class="font-semibold capitalize">{{ str($evento->tipo)->replace('_', ' ') }}</span>
                                <span class="text-slate-400">{{ $evento->created_at?->format('d/m/Y H:i') }}</span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $evento->descripcion }}@if ($evento->usuario) <span class="text-slate-400">· {{ $evento->usuario->name }}</span>@endif</p>
                        </div>
                    @endforeach
                </div>
            </div>
            @endunless
        </div>
    </div>
</div>
