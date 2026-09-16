<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Tecnico extends Model
{
    /** @use HasFactory<\Database\Factories\TecnicoFactory> */
    use HasFactory;

    protected $fillable = [
        'usuario_id',
        'especialidad_id',
        'fecha_ingreso',
        'cargo',
        'tipo_contrato',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'fecha_ingreso' => 'date',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function especialidad(): BelongsTo
    {
        return $this->belongsTo(Especialidad::class);
    }

    /**
     * Histórico de sueldos, más reciente primero (spec 004, FR-009).
     */
    public function sueldos(): HasMany
    {
        return $this->hasMany(SueldoTecnico::class)->orderByDesc('vigente_desde')->orderByDesc('id');
    }

    /**
     * Sueldo mensual vigente a `$fecha` (o a hoy): la fila con mayor
     * `vigente_desde` menor o igual a esa fecha. `null` si no hay ninguna.
     */
    public function sueldoVigente(?CarbonInterface $fecha = null): ?float
    {
        $fecha ??= Carbon::today();

        $registro = $this->sueldos()
            ->whereDate('vigente_desde', '<=', $fecha)
            ->first();

        return $registro ? (float) $registro->sueldo : null;
    }

    /**
     * Valor del día = sueldo vigente / N (spec 004, FR-010; N configurable).
     * Consumido por el costeo de mano de obra propia de la OT (spec 002, FR-016).
     */
    public function valorDia(?CarbonInterface $fecha = null): ?float
    {
        $sueldo = $this->sueldoVigente($fecha);

        if ($sueldo === null) {
            return null;
        }

        return round($sueldo / max(1, (int) config('personal.dias_mes', 30)), 2);
    }

    public function getSueldoActualAttribute(): ?float
    {
        return $this->sueldoVigente();
    }

    public function getValorDiaActualAttribute(): ?float
    {
        return $this->valorDia();
    }

    /**
     * Registra un sueldo nuevo en el histórico solo si difiere del vigente a esa
     * fecha (evita filas duplicadas al guardar el formulario sin cambios).
     */
    public function registrarSueldo(float $valor, CarbonInterface $vigenteDesde, ?User $por = null): ?SueldoTecnico
    {
        if ($this->sueldoVigente($vigenteDesde) === round($valor, 2)) {
            return null;
        }

        return $this->sueldos()->create([
            'sueldo' => round($valor, 2),
            'vigente_desde' => $vigenteDesde->toDateString(),
            'registrado_por' => $por?->id,
        ]);
    }

    /**
     * Técnicos disponibles para asignación de nuevas tareas (spec 004, FR-002).
     * Consumido por el selector de operario de OT (spec 002) una vez integrado.
     */
    public function scopeDisponibles(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * Tareas activas (spec 004, FR-005): mismo criterio de "activa" que el
     * dashboard del técnico — `estado_tarea` en pendiente/en_curso y la OT
     * ya liberada (no en Planificación) y no en un estado terminal.
     */
    public function tareasActivasCount(): int
    {
        return DetalleOt::query()
            ->where('tecnico_id', $this->id)
            ->whereIn('estado_tarea', ['pendiente', 'en_curso'])
            ->with('ordenTrabajo.estado')
            ->get()
            ->filter(fn (DetalleOt $t) => ! $t->ordenTrabajo?->estaEnEstado(EstadoOt::EN_REVISION)
                && ! optional($t->ordenTrabajo?->estado)->es_terminal)
            ->count();
    }
}
