<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEstablecimiento;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Fila de `indicador_pauta`. Es un modelo de pivote para que `attach()` pase por el evento
 * `creating` de PerteneceAEstablecimiento y reciba el establecimiento activo.
 */
class IndicadorPauta extends Pivot
{
    use PerteneceAEstablecimiento;

    protected $table = 'indicador_pauta';

    public $incrementing = true;
}
