<?php

use App\Models\ChecklistOt;
use App\Models\DetalleOt;
use App\Models\Inventario;
use App\Models\OrdenTrabajo;
use App\Models\Prioridad;
use App\Models\Tecnico;
use App\Livewire\Concerns\Notifies;
use App\Models\OtHerramienta;
use App\Services\OrdenTrabajo\EstadoOtService;
use App\Services\OrdenTrabajo\OrdenTrabajoService;
use App\Services\OrdenTrabajo\OtHerramientaService;
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

    // Finalizar tarea
    public ?int $finalizandoTareaId = null;
    public string $diasTrabajados = '';

    // Evidencia de proceso
    public $evidencia = null;
    public string $evidenciaDescripcion = '';

    // Checklist
    public string $nuevoItem = '';

    // Salida de equipo
    public string $motivoRechazoSalida = '';
    public string $firmaCliente = '';

    // Herramientas de la OT (Phase 11 / D4)
    public ?int $herramientaAsignarId = null;
    public ?int $devolviendoHerramientaId = null;
    public string $estadoDevolucionHerramienta = 'disponible';

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
    public string $tiempoEstimadoDias = '';
    // Alta / edición de tareas en una OT existente (US4 / FR-009)
    public bool $agregandoTarea = false;
    /** @var array<string, mixed> */
    public array $tareaForm = ['descripcion' => '', 'tecnico_id' => null, 'insumos' => [], 'prerrequisitos' => []];
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
        $this->tiempoEstimadoDias = (string) ($this->ot->tiempo_estimado_dias ?? '');
    }

    public function with(): array
    {
        $this->ot->load([
            'cliente', 'equipo', 'prioridad', 'estado', 'creadoPor',
            'tareas' => fn ($q) => $q->orderBy('orden')->orderBy('id'),
            'tareas.tecnico.usuario', 'tareas.insumos.inventario', 'tareas.insumos.solicitud', 'tareas.prerrequisitos',
            'evidencias.subidaPor', 'checklist', 'eventos.usuario',
            'manoObraContratistas.contratista', 'herramientas.inventario',
        ]);

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
            'herramientasDisponibles' => Inventario::activos()->where('tipo', 'herramienta')
                ->where('estado_herramienta', 'disponible')->orderBy('nombre')->get(['id', 'nombre', 'codigo']),
            'puedeGestionar' => Gate::allows('update', $this->ot),
            'puedeEjecutar' => Gate::allows('executeTareas', $this->ot),
            'puedeAprobarSalida' => Gate::allows('approveEquipmentExit', $this->ot),
            'puedeSolicitarSalida' => Gate::allows('requestEquipmentExit', $this->ot),
            'puedeVerCosteo' => Gate::allows('viewCosteo', $this->ot),
            'puedeFinalizar' => app(EstadoOtService::class)->puedeFinalizar($this->ot),
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

    public function confirmarFinalizarTarea(int $tareaId): void
    {
        Gate::authorize('executeTareas', $this->ot);
        $this->finalizandoTareaId = $tareaId;
        $this->diasTrabajados = '';
    }

    public function finalizarTarea(OrdenTrabajoService $servicio): void
    {
        Gate::authorize('executeTareas', $this->ot);

        $this->validate([
            'diasTrabajados' => 'required|numeric|min:0',
        ], [], ['diasTrabajados' => 'días trabajados']);

        $tarea = $this->ot->tareas()->findOrFail($this->finalizandoTareaId);

        if ($tarea->estado_tarea !== 'en_curso') {
            $this->notifyError('Inicia la tarea antes de finalizarla.');

            return;
        }

        try {
            $tarea = $servicio->marcarTareaListaParaFinalizar($tarea, auth()->user(), (float) $this->diasTrabajados);
        } catch (ValidationException $e) {
            $this->notifyError($e->getMessage());

            return;
        }

        $this->finalizandoTareaId = null;
        $this->diasTrabajados = '';
        $this->ot->refresh();
        $this->notifySuccess($tarea->finalizacionPendiente()
            ? 'Tarea marcada lista para finalizar. Falta la confirmación del Jefe (insumos sin entregar).'
            : 'Tarea finalizada.');
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

    // --- Herramientas de la OT (Phase 11 / D4) ---

    public function asignarHerramienta(OtHerramientaService $svc): void
    {
        Gate::authorize('update', $this->ot);
        $this->validate(['herramientaAsignarId' => 'required|exists:inventario,id'], [], ['herramientaAsignarId' => 'herramienta']);

        try {
            $svc->asignar($this->ot, Inventario::findOrFail($this->herramientaAsignarId), auth()->user());
        } catch (ValidationException $e) {
            $this->notifyError($e->getMessage());

            return;
        }

        $this->herramientaAsignarId = null;
        $this->ot->refresh();
        $this->notifySuccess('Herramienta asignada a la OT.');
    }

    public function devolverHerramienta(OtHerramientaService $svc): void
    {
        Gate::authorize('update', $this->ot);
        $asignacion = OtHerramienta::where('ot_id', $this->ot->id)->findOrFail($this->devolviendoHerramientaId);

        try {
            $svc->devolver($asignacion, auth()->user(), $this->estadoDevolucionHerramienta);
        } catch (ValidationException $e) {
            $this->notifyError($e->getMessage());

            return;
        }

        $this->devolviendoHerramientaId = null;
        $this->estadoDevolucionHerramienta = 'disponible';
        $this->ot->refresh();
        $this->notifySuccess('Herramienta devuelta al inventario.');
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
            'tiempoEstimadoDias' => 'nullable|numeric|min:0',
        ], [], ['prioridadId' => 'prioridad']);

        $servicio->corregir($this->ot, auth()->user(), [
            'prioridad_id' => (int) $datos['prioridadId'],
            'descripcion' => $datos['descripcion'],
            'tipo_servicio' => $datos['tipoServicio'],
            'tiempo_estimado_dias' => $datos['tiempoEstimadoDias'] !== '' ? (float) $datos['tiempoEstimadoDias'] : null,
        ]);

        $this->editandoCabecera = false;
        $this->ot->refresh();
        $this->notifySuccess('OT corregida.');
    }

    // --- US4: alta / edición / baja de tareas en una OT existente ---

    private function resetTareaForm(): void
    {
        $this->tareaForm = ['descripcion' => '', 'tecnico_id' => null, 'insumos' => [], 'prerrequisitos' => []];
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

        if ($tarea->estado_tarea === 'finalizada') {
            $this->notifyError('Una tarea finalizada no se puede editar ni reasignar.');

            return;
        }

        $this->agregandoTarea = false;
        $this->editandoTareaId = $tareaId;
        $this->tareaForm = [
            'descripcion' => $tarea->descripcion,
            'tecnico_id' => $tarea->tecnico_id,
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
            'tareaForm.insumos' => 'array',
            'tareaForm.insumos.*.inventario_id' => 'required|exists:inventario,id',
            'tareaForm.insumos.*.cantidad' => 'required|numeric|min:0.01',
            'tareaForm.prerrequisitos' => 'array',
            'tareaForm.prerrequisitos.*' => 'integer',
        ], [], [
            'tareaForm.descripcion' => 'descripción',
            'tareaForm.tecnico_id' => 'técnico',
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
                            <x-select wire:model="tipoServicio" :placeholder="null">
                                <option value="taller">Taller</option>
                                <option value="domicilio">Domicilio</option>
                            </x-select>
                        </x-field>
                        <x-field label="Tiempo estimado (días)">
                            <x-input type="number" step="0.5" min="0" wire:model="tiempoEstimadoDias" />
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
                <div class="flex items-center justify-between">
                    <h2 class="font-bold text-sm">Tareas <span class="text-slate-400 font-normal">({{ $ot->tareas->count() }})</span>
                        <span class="text-xs text-slate-400 font-normal">· {{ $ot->tareas->where('estado_tarea', 'finalizada')->count() }} finalizadas</span>
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
                    @foreach ($ot->tareas as $tarea)
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
                                    @if ($finalizandoTareaId === $tarea->id)
                                        <div class="flex flex-wrap items-center gap-2">
                                            <input type="number" step="0.5" min="0" wire:model="diasTrabajados" placeholder="Días" class="w-24 h-9 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/60 px-3 text-xs outline-none focus:border-brand-blue focus:ring-4 focus:ring-brand-blue/10">
                                            <button wire:click="finalizarTarea" class="text-[12px] font-semibold px-3 py-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Confirmar</button>
                                            <button wire:click="$set('finalizandoTareaId', null)" class="text-[12px] px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700">Cancelar</button>
                                        </div>
                                        @error('diasTrabajados') <span class="text-brand-red text-xs w-full">{{ $message }}</span> @enderror
                                    @else
                                        <button wire:click="confirmarFinalizarTarea({{ $tarea->id }})" class="text-[12px] font-semibold px-3 py-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Finalizar</button>
                                    @endif
                                @endif
                                @if ($puedeGestionar && $tarea->finalizacionPendiente())
                                    <button wire:click="confirmarFinalizacionJefe({{ $tarea->id }})" class="text-[12px] font-semibold px-3 py-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Confirmar finalización</button>
                                @endif
                                @if ($puedeGestionar && ! in_array($tarea->estado_tarea, ['finalizada', 'cancelada'], true))
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
                    @endforeach
                </div>
            </div>

            {{-- Checklist --}}
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

            {{-- Herramientas asignadas (Phase 11 / D4) --}}
            @if ($puedeGestionar || $ot->herramientas->isNotEmpty())
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex flex-col gap-3 text-sm">
                    <h2 class="font-bold text-sm">Herramientas asignadas</h2>
                    @forelse ($ot->herramientas as $h)
                        <div wire:key="hrr-{{ $h->id }}" class="flex flex-col gap-1.5 border-b border-slate-50 dark:border-slate-800/60 pb-2 last:border-0">
                            <div class="flex items-center justify-between gap-2">
                                <span>{{ $h->inventario?->nombre }}</span>
                                @if ($h->estaDevuelta())
                                    <span class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">devuelta · {{ str($h->estado_devolucion)->replace('_', ' ') }}</span>
                                @else
                                    <span class="text-[11px] font-semibold text-amber-600 dark:text-amber-400">en uso</span>
                                @endif
                            </div>
                            @if (! $h->estaDevuelta() && $puedeGestionar)
                                @if ($devolviendoHerramientaId === $h->id)
                                    <div class="flex flex-wrap items-center gap-2">
                                        <select wire:model="estadoDevolucionHerramienta" class="h-8 rounded-lg border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs px-2">
                                            <option value="disponible">Disponible</option>
                                            <option value="dañada">Dañada</option>
                                            <option value="en_mantenimiento">En mantenimiento</option>
                                        </select>
                                        <button wire:click="devolverHerramienta" class="text-[11px] font-semibold px-2.5 py-1 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Confirmar devolución</button>
                                        <button wire:click="$set('devolviendoHerramientaId', null)" class="text-[11px] px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700">Cancelar</button>
                                    </div>
                                @else
                                    <button wire:click="$set('devolviendoHerramientaId', {{ $h->id }})" class="self-start text-[11px] font-semibold text-brand-blue hover:underline">Devolver</button>
                                @endif
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-slate-400">Sin herramientas asignadas.</p>
                    @endforelse

                    @if ($puedeGestionar && ! $ot->estaBloqueada())
                        <div class="flex flex-wrap items-center gap-2 pt-1">
                            <x-select wire:model="herramientaAsignarId" :reset-key="'hrr-'.$ot->herramientas->count()" class="flex-1 min-w-[10rem]">
                                @foreach ($herramientasDisponibles as $hd)<option value="{{ $hd->id }}">{{ $hd->nombre }} ({{ $hd->codigo }})</option>@endforeach
                            </x-select>
                            <button wire:click="asignarHerramienta" class="text-[12px] font-semibold px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800">Asignar</button>
                        </div>
                        @error('herramientaAsignarId') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                    @endif
                </div>
            @endif

            {{-- Insumos de la OT (consolidado) --}}
            @php $lineasOt = $ot->tareas->flatMap(fn ($t) => $t->insumos); @endphp
            @if ($lineasOt->isNotEmpty())
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

            {{-- Trazabilidad --}}
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
        </div>
    </div>
</div>
