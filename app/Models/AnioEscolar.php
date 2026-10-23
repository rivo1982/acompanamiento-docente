<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEstablecimiento;
use Illuminate\Database\Eloquent\Model;

/**
 * Año escolar de un establecimiento. Su `anio` es el AAAA del número visible de un Caso.
 *
 * @host-owned Copied from Vinkured app/Models/AnioEscolar.php — do not modify here; replace with the host class at integration.
 */
class AnioEscolar extends Model
{
    use PerteneceAEstablecimiento;

    protected $table = 'anios_escolares';

    /** @var list<string> */
    protected $fillable = ['anio'];
}
