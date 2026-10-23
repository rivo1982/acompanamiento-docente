<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Entidad de aislamiento (ADR-0002). Es de alcance global por naturaleza:
 * no lleva filtro por establecimiento.
 *
 * @host-owned Copied from Vinkured app/Models/Establecimiento.php — do not modify here; replace with the host class at integration.
 */
class Establecimiento extends Model
{
    protected $table = 'establecimientos';

    /**
     * @return HasMany<Membresia, $this>
     */
    public function membresias(): HasMany
    {
        return $this->hasMany(Membresia::class);
    }
}
