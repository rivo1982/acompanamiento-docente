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

    /*
    |--------------------------------------------------------------------------
    | Maximum spreadsheet size (kilobytes)
    |--------------------------------------------------------------------------
    |
    | Checked by the "Carga masiva" screen before the file is stored. 2 MB is
    | far above a 2,000-row staff spreadsheet (the row limit of the validator).
    |
    */

    'tamano_maximo_kb' => (int) env('IMPORTACIONES_TAMANO_MAXIMO_KB', 2048),

];
