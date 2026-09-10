<?php

namespace App\Services\Notificaciones;

use App\Enums\RolPrioridad;
use App\Models\Inventario;
use App\Models\OrdenTrabajo;
use App\Models\SolicitudInsumoOt;
use App\Models\User;
use App\Notifications\OtNotificacion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Envía los avisos entre roles del flujo de OT (spec 002, Phase 11 / D5). Todo
 * pasa por la campana in-app (canal `database`). Aislado para que el spec 008
 * complete el módulo de notificaciones sin duplicar destinatarios.
 */
class NotificadorOt
{
    public function otCreada(OrdenTrabajo $ot): void
    {
        $this->aRoles([RolPrioridad::JefeDeTaller->value], new OtNotificacion(
            'Nueva OT por planificar',
            "La OT {$ot->numero_ot} — {$ot->cliente?->nombre} fue creada y espera planificación.",
            $this->url($ot),
        ));
    }

    public function solicitudInsumoCreada(OrdenTrabajo $ot, int $lineas): void
    {
        $this->aRoles([RolPrioridad::Almacenista->value], new OtNotificacion(
            'Solicitudes de insumo nuevas',
            "La OT {$ot->numero_ot} generó {$lineas} solicitud(es) de insumo para Bodega.",
            route('insumos-ot'),
        ));
    }

    /**
     * OT liberada por el Jefe de Taller (Phase 12 / D13): avisa a cada técnico
     * con una tarea activa en la OT que ya puede ejecutar su trabajo.
     */
    public function otLiberada(OrdenTrabajo $ot): void
    {
        $ot->loadMissing('tareas.tecnico.usuario');

        $tecnicos = $ot->tareas
            ->where('estado_tarea', '!=', 'cancelada')
            ->map(fn ($t) => $t->tecnico?->usuario)
            ->filter();

        $this->enviar(collect($tecnicos), new OtNotificacion(
            'OT liberada para ejecución',
            "La OT {$ot->numero_ot} — {$ot->cliente?->nombre} fue liberada. Ya puedes ejecutar tus tareas.",
            $this->url($ot),
        ));
    }

    /**
     * Al liberar la OT, si quedan solicitudes de insumo en `pendiente`, avisa a
     * Bodega una sola vez (Phase 12 / D13).
     */
    public function insumosPendientesAlLiberar(OrdenTrabajo $ot): void
    {
        $this->aRoles([RolPrioridad::Almacenista->value], new OtNotificacion(
            'Insumos pendientes de una OT liberada',
            "La OT {$ot->numero_ot} — {$ot->cliente?->nombre} se liberó con solicitudes de insumo por atender.",
            route('insumos-ot'),
        ));
    }

    public function solicitudInsumoEntregada(SolicitudInsumoOt $s): void
    {
        $s->loadMissing('tarea.tecnico.usuario', 'inventario', 'ordenTrabajo');
        $s->tarea?->tecnico?->usuario?->notify(new OtNotificacion(
            'Insumo disponible',
            "Bodega entregó {$this->nfmt($s->cantidad)} de {$s->inventario?->nombre} para «{$s->tarea?->descripcion}» (OT {$s->ordenTrabajo?->numero_ot}).",
            $this->url($s->ordenTrabajo),
        ));
    }

    public function solicitudInsumoRechazada(SolicitudInsumoOt $s): void
    {
        $s->loadMissing('tarea', 'ordenTrabajo.creadoPor');
        $this->aRolesYUsuarios([RolPrioridad::JefeDeTaller->value], [$s->ordenTrabajo?->creadoPor], new OtNotificacion(
            'Insumo rechazado por Bodega',
            "Bodega rechazó el insumo de «{$s->tarea?->descripcion}» (OT {$s->ordenTrabajo?->numero_ot}): {$s->motivo_rechazo}",
            $this->url($s->ordenTrabajo),
            'alerta',
        ));
    }

    public function salidaSolicitada(OrdenTrabajo $ot): void
    {
        $this->aRoles([RolPrioridad::Administrador->value], new OtNotificacion(
            'Salida de equipo por aprobar',
            "La OT {$ot->numero_ot} — {$ot->cliente?->nombre} solicitó la salida del equipo y espera aprobación.",
            $this->url($ot),
        ));
    }

    public function salidaRechazada(OrdenTrabajo $ot): void
    {
        $ot->loadMissing('creadoPor');
        $this->aRolesYUsuarios([RolPrioridad::JefeDeTaller->value], [$ot->creadoPor], new OtNotificacion(
            'Salida de equipo rechazada',
            "El Administrador rechazó la salida de la OT {$ot->numero_ot}: {$ot->salida_motivo_rechazo}. La OT volvió a ejecución.",
            $this->url($ot),
            'alerta',
        ));
    }

    public function otEntregada(OrdenTrabajo $ot): void
    {
        $ot->loadMissing('creadoPor');
        $ot->creadoPor?->notify(new OtNotificacion(
            'OT entregada',
            "La OT {$ot->numero_ot} — {$ot->cliente?->nombre} fue entregada al cliente.",
            $this->url($ot),
        ));
    }

    public function otProximaAVencer(OrdenTrabajo $ot, bool $vencida): void
    {
        $this->aRoles([RolPrioridad::JefeDeTaller->value, RolPrioridad::Administrador->value], new OtNotificacion(
            $vencida ? 'OT vencida' : 'OT próxima a vencer',
            "La OT {$ot->numero_ot} — {$ot->cliente?->nombre} ".($vencida ? 'pasó su tiempo estimado.' : 'está por alcanzar su tiempo estimado.'),
            $this->url($ot),
            'alerta',
        ));
    }

    public function stockBajo(Inventario $item): void
    {
        $this->aRoles([RolPrioridad::Almacenista->value], new OtNotificacion(
            'Stock bajo mínimo',
            "«{$item->nombre}» quedó en {$this->nfmt($item->stock_actual)} (mínimo {$this->nfmt($item->stock_minimo)}).",
            route('inventario.catalogo'),
            'alerta',
        ));
    }

    /** @param  array<int, string>  $roles */
    private function aRoles(array $roles, OtNotificacion $notificacion): void
    {
        $this->enviar(User::query()->where('estado', 'activo')->role($roles)->get(), $notificacion);
    }

    /**
     * @param  array<int, string>  $roles
     * @param  array<int, ?User>  $usuarios
     */
    private function aRolesYUsuarios(array $roles, array $usuarios, OtNotificacion $notificacion): void
    {
        $destinos = User::query()->where('estado', 'activo')->role($roles)->get()
            ->concat(collect($usuarios)->filter());

        $this->enviar($destinos, $notificacion);
    }

    /** @param  Collection<int, User>  $usuarios */
    private function enviar(Collection $usuarios, OtNotificacion $notificacion): void
    {
        $usuarios = $usuarios->unique('id')->values();

        if ($usuarios->isNotEmpty()) {
            Notification::send($usuarios, $notificacion);
        }
    }

    private function url(?OrdenTrabajo $ot): ?string
    {
        return $ot ? route('ordenes-trabajo.detalle', $ot) : null;
    }

    private function nfmt(float|string|null $v): string
    {
        return rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    }
}
