<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo GLOBAL de grados (de sala cuna a 4° medio). Sin filtro por establecimiento.
 *
 * @host-owned Copied from Vinkured app/Models/Grado.php — do not modify here; replace with the host class at integration.
 */
class Grado extends Model
{
    protected $table = 'grados';

    /** @var list<string> */
    protected $fillable = ['nombre'];
}
