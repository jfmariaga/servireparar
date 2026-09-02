<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Días del mes para el valor del día
    |--------------------------------------------------------------------------
    | El valor del día de un técnico se deriva como sueldo_mensual / dias_mes.
    | Convención de costeo interno del taller (spec 004, FR-010); no es un
    | cálculo de nómina con factor prestacional. Ajustable por entorno.
    */
    'dias_mes' => (int) env('PERSONAL_DIAS_MES', 30),

];
