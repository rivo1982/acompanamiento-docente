<?php

namespace App\Models\Scopes;

use App\Exceptions\SinEstablecimientoActivo;
use App\Support\EstablecimientoActual;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limita toda consulta al establecimiento activo. Sin establecimiento activo lanza
 * SinEstablecimientoActivo (falla cerrado); no devuelve todas las filas ni ninguna.
 *
 * @host-owned Copied from Vinkured app/Models/Scopes/EstablecimientoActivoScope.php — do not modify here; replace with the host class at integration.
 */
final class EstablecimientoActivoScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $id = app(EstablecimientoActual::class)->id();

        if ($id === null) {
            throw SinEstablecimientoActivo::para($model::class);
        }

        $builder->where($model->qualifyColumn('establecimiento_id'), $id);
    }
}
