<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Invitation lifetime
    |--------------------------------------------------------------------------
    |
    | Days an invitation link stays valid, counted from the moment its mail is
    | sent (the token is rotated right before sending). Default of 7 days
    | decided by the module team on 2026-10-24, pending confirmation by
    | Vinkured (odd/tasks/carga-masiva-funcionarios.md, task C2).
    |
    */

    'vigencia_dias' => (int) env('INVITACION_VIGENCIA_DIAS', 7),

];
