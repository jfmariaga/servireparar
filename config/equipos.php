<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Checklist técnico digital por defecto (spec 005, FR-007)
    |--------------------------------------------------------------------------
    | Plantilla única genérica (no varía por tipo de equipo/servicio, ver
    | Clarifications de spec 005). Se precarga por cada tarea al crear una OT
    | con equipo asociado; el técnico la responde mientras ejecuta su tarea
    | (mismo momento que las variables técnicas). Dejar el array vacío para no
    | precargar nada.
    */
    'checklist_tecnico_por_defecto' => [
        'Equipo probado y funcionando correctamente',
        'Sin fugas, ruidos ni vibraciones anómalas',
        'Conexiones eléctricas/mecánicas verificadas y ajustadas',
        'Limpieza general del equipo realizada',
        'Etiqueta o placa de identificación legible',
    ],

];
