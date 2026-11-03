<?php

namespace App\Actions;

use App\Models\Acompanamiento;
use App\Models\RespuestaAcompanamiento;
use App\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Elimina un acompañamiento EN CURSO y sus respuestas. Solo su observador (AcompanamientoPolicy::actualizar); uno
 * finalizado no se descarta nunca. Bloquea la fila y vuelve a autorizar dentro de la transacción, así un
 * acompañamiento finalizado entretanto se niega sin borrar nada.
 */
class DescartarAcompanamiento
{
    /**
     * @throws ModelNotFoundException si el acompañamiento no existe en el establecimiento activo
     * @throws AuthorizationException si $observador no es su observador o ya está finalizado
     */
    public function descartar(Usuario $observador, int $acompanamientoId): void
    {
        $gate = Gate::forUser($observador);
        $gate->authorize('actualizar', Acompanamiento::query()->findOrFail($acompanamientoId));

        DB::transaction(function () use ($gate, $acompanamientoId): void {
            $acompanamiento = Acompanamiento::query()->lockForUpdate()->findOrFail($acompanamientoId);
            $gate->authorize('actualizar', $acompanamiento);

            // Las respuestas restringen el borrado (FK): primero ellas, cada una por su guarda del modelo.
            RespuestaAcompanamiento::query()->where('acompanamiento_id', $acompanamientoId)->get()
                ->each(fn (RespuestaAcompanamiento $respuesta) => $respuesta->delete());

            $acompanamiento->delete();
        });
    }
}
