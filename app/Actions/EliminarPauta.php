<?php

namespace App\Actions;

use App\Models\Pauta;
use App\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Elimina (de forma lógica, SoftDeletes) una pauta del establecimiento activo. Solo el administrador del módulo
 * (PautaPolicy::eliminar).
 *
 * Se permite aunque la pauta tenga acompañamientos, también en curso: cada uno congeló el contenido de la pauta
 * en `pauta_snapshot` y responde contra él, y Acompanamiento::pauta() lee la pauta eliminada (withTrashed), así
 * que el historial se conserva entero. La fila y sus `indicador_pauta` siguen en la base (las claves foráneas
 * son restrictivas); la pauta solo deja de listarse y de poder usarse en acompañamientos nuevos
 * (PautaNoDisponible). Su nombre queda libre para una pauta nueva.
 */
class EliminarPauta
{
    /**
     * @throws ModelNotFoundException si la pauta no existe, ya está eliminada o es de otro establecimiento
     * @throws AuthorizationException si $administrador no es el administrador del módulo autenticado
     */
    public function eliminar(Usuario $administrador, int $pautaId): void
    {
        $gate = Gate::forUser($administrador);

        DB::transaction(function () use ($gate, $pautaId): void {
            $pauta = Pauta::query()->lockForUpdate()->findOrFail($pautaId);
            $gate->authorize('eliminar', $pauta);

            $pauta->delete();
        });
    }
}
