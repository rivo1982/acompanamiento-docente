<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Uploaded staff spreadsheets (bulk import, Q18)
    |--------------------------------------------------------------------------
    |
    | Disk and directory where an uploaded .xlsx waits until ProcesarImportacion
    | reads it. The file holds personal data: the job deletes it when it ends,
    | successfully or not. The disk must be local (the reader needs a path).
    |
    */

    'disco' => env('IMPORTACIONES_DISCO', 'local'),

    'directorio' => 'importaciones',

];
