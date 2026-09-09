<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Orden de Trabajo (spec 002). El `estado_id` solo lo escribe
 * App\Services\OrdenTrabajo\EstadoOtService (FR-004); ningún componente lo
 * modifica directamente. La numeración `OTSV-00001` la asigna OtNumberGenerator.
 */
class OrdenTrabajo extends Model
{
    /** @use HasFactory<\Database\Factories\OrdenTrabajoFactory> */
    use HasFactory;

    protected $table = 'ordenes_trabajo';

    protected $fillable = [
        'numero_ot',
        'cliente_id',
        'equipo_id',
        'prioridad_id',
        'estado_id',
        'tipo_servicio',
        'descripcion',
        'tiempo_estimado_dias',
        'valor_proyecto',
        'equipo_descripcion',
        'equipo_marca',
        'equipo_modelo',
        'equipo_serie',
        'equipo_estado_ingreso',
        'salida_estado',
        'salida_solicitada_por',
        'salida_solicitada_en',
        'salida_resuelta_por',
        'salida_resuelta_en',
        'salida_motivo_rechazo',
        'firma_cliente_url',
        'creado_por',
        'fecha_finalizacion',
        'fecha_entrega',
        'alertado_vencimiento_en',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'tiempo_estimado_dias' => 'decimal:2',
            'valor_proyecto' => 'decimal:2',
            'salida_solicitada_en' => 'datetime',
            'salida_resuelta_en' => 'datetime',
            'fecha_finalizacion' => 'datetime',
            'fecha_entrega' => 'datetime',
            'alertado_vencimiento_en' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }

    public function prioridad(): BelongsTo
    {
        return $this->belongsTo(Prioridad::class);
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(EstadoOt::class, 'estado_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function tareas(): HasMany
    {
        return $this->hasMany(DetalleOt::class, 'ot_id');
    }

    public function manoObraContratistas(): HasMany
    {
        return $this->hasMany(OtManoObraContratista::class, 'ot_id');
    }

    public function evidencias(): HasMany
    {
        return $this->hasMany(EvidenciaOt::class, 'ot_id');
    }

    public function checklist(): HasMany
    {
        return $this->hasMany(ChecklistOt::class, 'ot_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(OtEvento::class, 'ot_id')->latest('created_at')->latest('id');
    }

    public function solicitudesInsumo(): HasMany
    {
        return $this->hasMany(SolicitudInsumoOt::class, 'ot_id');
    }

    public function herramientas(): HasMany
    {
        return $this->hasMany(OtHerramienta::class, 'ot_id');
    }

    /** ¿Hay herramientas asignadas a la OT que aún no se han devuelto? (Phase 11 / D4) */
    public function tieneHerramientasSinDevolver(): bool
    {
        $rel = $this->relationLoaded('herramientas') ? $this->herramientas : $this->herramientas();

        return $rel->whereNull('devuelta_en')->count() > 0;
    }

    /** El checklist está resuelto cuando existe al menos un ítem y ninguno queda con `cumple` null. */
    public function checklistCompleto(): bool
    {
        $items = $this->relationLoaded('checklist') ? $this->checklist : $this->checklist()->get();

        return $items->isNotEmpty() && $items->every(fn (ChecklistOt $i) => $i->cumple !== null);
    }

    public function estaEnEstado(string $slug): bool
    {
        return optional($this->estado)->slug === $slug;
    }

    public function salidaAprobada(): bool
    {
        return $this->salida_estado === 'aprobada';
    }

    /**
     * La OT está congelada (Phase 11): una vez aprobada la salida del equipo —o
     * ya entregada— no se puede tocar nada; solo queda confirmar la entrega.
     */
    public function estaBloqueada(): bool
    {
        return $this->salidaAprobada() || optional($this->estado)->es_terminal;
    }

    /** Suma de días efectivamente trabajados en las tareas (spec 002, FR-005). */
    public function diasTrabajadosTotales(): float
    {
        $tareas = $this->relationLoaded('tareas') ? $this->tareas : $this->tareas()->get();

        return (float) $tareas->sum(fn (DetalleOt $t) => (float) $t->dias_trabajados);
    }

    /**
     * Comparativo tiempo estimado vs. real (FR-005): días de diferencia
     * (positivo = se pasó del estimado) o null si falta el estimado.
     */
    public function desviacionDias(): ?float
    {
        if ($this->tiempo_estimado_dias === null) {
            return null;
        }

        return round($this->diasTrabajadosTotales() - (float) $this->tiempo_estimado_dias, 2);
    }

    public function registrarEvento(string $tipo, string $descripcion, ?User $usuario = null): OtEvento
    {
        return $this->eventos()->create([
            'usuario_id' => $usuario?->id ?? auth()->id(),
            'tipo' => $tipo,
            'descripcion' => $descripcion,
            'created_at' => now(),
        ]);
    }

    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        if (blank($termino)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($termino) {
            $q->where('numero_ot', 'like', "%{$termino}%")
                ->orWhere('descripcion', 'like', "%{$termino}%")
                ->orWhereHas('cliente', fn (Builder $c) => $c->where('nombre', 'like', "%{$termino}%"));
        });
    }
}
