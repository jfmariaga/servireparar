<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Umbral de vencimiento de una OT
    |--------------------------------------------------------------------------
    | Días de anticipación respecto al tiempo estimado (en días) para marcar
    | una OT como "próxima a vencer" (spec 002, FR-010). Ajustable por el
    | Administrador sin cambios de código. Spec 008 consume el evento
    | App\Events\OtProximaAVencer que dispara el comando `ot:revisar-vencimientos`.
    */
    'dias_umbral_vencimiento' => (int) env('OT_DIAS_UMBRAL_VENCIMIENTO', 2),

    /*
    |--------------------------------------------------------------------------
    | Refresco de la campana de notificaciones
    |--------------------------------------------------------------------------
    | Cada cuántos segundos la campana del layout consulta si hay avisos nuevos
    | (Phase 11 / D5 — polling, sin websockets).
    */
    'notif_poll_segundos' => (int) env('OT_NOTIF_POLL_SEGUNDOS', 45),

];
