<?php

namespace App\Models;

use App\Exceptions\SinEstablecimientoActivo;
use App\Support\EstablecimientoActual;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Calendario de días no hábiles. Mezcla filas NACIONALES (`establecimiento_id` nulo, origen
 * `oficial`) con filas de un establecimiento, así que NO usa el rasgo PerteneceAEstablecimiento
 * (que exigiría igualdad con el establecimiento activo y ocultaría los feriados nacionales).
 *
 * Su filtro propio devuelve las filas nacionales MÁS las del establecimiento activo, y falla
 * cerrado (SinEstablecimientoActivo) si no hay uno. Única puerta de escape, buscable por nombre:
 * `DiaNoHabil::paraEstablecimiento($id)` (nacionales + las de ese establecimiento; para trabajos
 * fuera de una petición). Nunca devuelve filas de un tercer establecimiento.
 *
 * Es de solo lectura desde Eloquent: ni `id` ni `establecimiento_id` son asignables en masa.
 *
 * @host-owned Copied from Vinkured app/Models/DiaNoHabil.php — do not modify here; replace with the host class at integration.
 */
class DiaNoHabil extends Model
{
    protected $table = 'dias_no_habiles';

    /** @var list<string> */
    protected $fillable = ['descripcion'];

    protected static function booted(): void
    {
        static::addGlobalScope('dias_visibles', function (Builder $consulta): void {
            $id = app(EstablecimientoActual::class)->id();

            if ($id === null) {
                throw SinEstablecimientoActivo::para(self::class);
            }

            self::limitar($consulta, $consulta->getModel(), $id);
        });
    }

    /**
     * Filas nacionales más las de $establecimientoId, sin depender del establecimiento activo.
     *
     * @return Builder<static>
     */
    public static function paraEstablecimiento(int $establecimientoId): Builder
    {
        $consulta = static::withoutGlobalScope('dias_visibles');

        return self::limitar($consulta, new static, $establecimientoId);
    }

    /**
     * @param  Builder<static>  $consulta
     * @return Builder<static>
     */
    private static function limitar(Builder $consulta, Model $modelo, int $establecimientoId): Builder
    {
        $columna = $modelo->qualifyColumn('establecimiento_id');

        return $consulta->where(function (Builder $grupo) use ($columna, $establecimientoId): void {
            $grupo->whereNull($columna)->orWhere($columna, $establecimientoId);
        });
    }
}
