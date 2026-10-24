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

    /*
    |--------------------------------------------------------------------------
    | Result page polling limit (minutes)
    |--------------------------------------------------------------------------
    |
    | The result page of a carga masiva refreshes itself while the import is
    | pending or processing, but only until this many minutes after it was
    | uploaded. After that it stops and tells the user to reload later or to
    | contact support (a stuck queue must not keep a page polling forever).
    |
    */

    'minutos_de_seguimiento' => (int) env('IMPORTACIONES_MINUTOS_DE_SEGUIMIENTO', 10),

];
