<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reservas de números
    |--------------------------------------------------------------------------
    |
    | reserva_minutos: tiempo que dura una reserva sin pagar. Se guarda en
    | raffle_numbers.expires_at al reservar.
    |
    | liberacion_automatica: si está en true, el comando reservas:liberar
    | (scheduler, cada minuto) devuelve a "free" las reservas vencidas.
    | Queda en false hasta que exista la subida de comprobante (fase 2):
    | sin eso, se liberarían números de gente que ya transfirió.
    |
    */

    'reserva_minutos' => (int) env('SORTEOS_RESERVA_MINUTOS', 15),

    'liberacion_automatica' => (bool) env('SORTEOS_LIBERACION_AUTOMATICA', false),

];
