<?php

namespace App\Models\Concerns;

use App\Exceptions\EstablecimientoAjeno;
use App\Exceptions\SinEstablecimientoActivo;
use App\Models\Scopes\EstablecimientoActivoScope;
use App\Support\EstablecimientoActual;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Aislamiento por establecimiento en Eloquent (ADR-0002). Úsese en todo modelo cuya tabla
 * tenga `establecimiento_id`; una prueba de meta lo exige.
 *
 * - Consultas: filtro global por el establecimiento activo. Sin uno activo lanza
 *   SinEstablecimientoActivo.
 * - Creación: `establecimiento_id` sale del establecimiento activo. Si ya trae otro valor
 *   lanza EstablecimientoAjeno. Nunca debe ser asignable en masa. Sin establecimiento
 *   activo lanza SinEstablecimientoActivo, también al crear por `sinEstablecimiento()`.
 * - Actualización: no se puede mover una fila a otro establecimiento.
 *
 * ÚNICA puerta de escape, buscable por nombre: `Modelo::sinEstablecimiento()` devuelve la
 * consulta sin filtro, para seeders, comandos de consola y pruebas. Solo lee; para escribir
 * en un establecimiento concreto fuera de una petición, los seeders usan `DB::table`.
 * Este filtro NO cubre `DB::table` ni SQL crudo (las claves foráneas compuestas protegen
 * la base) ni `insert`/`upsert` masivos del constructor de consultas, que no disparan eventos.
 *
 * @mixin Model
 *
 * @host-owned Copied from Vinkured app/Models/Concerns/PerteneceAEstablecimiento.php — do not modify here; replace with the host class at integration.
 */
trait PerteneceAEstablecimiento
{
    public static function bootPerteneceAEstablecimiento(): void
    {
        static::addGlobalScope(new EstablecimientoActivoScope);

        static::creating(function (Model $modelo): void {
            $activo = app(EstablecimientoActual::class)->id();

            if ($activo === null) {
                throw SinEstablecimientoActivo::para($modelo::class);
            }

            $explicito = $modelo->getAttribute('establecimiento_id');

            if ($explicito !== null && (int) $explicito !== $activo) {
                throw EstablecimientoAjeno::para($modelo::class);
            }

            $modelo->setAttribute('establecimiento_id', $activo);
        });

        static::updating(function (Model $modelo): void {
            if ($modelo->isDirty('establecimiento_id')) {
                throw EstablecimientoAjeno::para($modelo::class);
            }
        });
    }

    /**
     * Consulta SIN el filtro por establecimiento. Única puerta de escape: ver la nota de la clase.
     *
     * @return Builder<static>
     */
    public static function sinEstablecimiento(): Builder
    {
        return static::withoutGlobalScope(EstablecimientoActivoScope::class);
    }
}
