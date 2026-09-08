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

];
