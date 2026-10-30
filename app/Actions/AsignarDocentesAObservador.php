<?php

namespace App\Actions;

use App\Exceptions\AsignacionAcompanamientoInvalida;
use App\Models\AsignacionAcompanamiento;
use App\Models\Observador;
use App\Models\Usuario;
use App\Support\RolesDelModulo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Deja asignados a un observador del establecimiento activo exactamente los docentes elegidos: crea los que
 * faltan y borra los no elegidos. Solo el administrador del módulo (gate `gestionar-observadores`; cada par
 * nuevo pasa además por AsignacionAcompanamientoPolicy::crear y cada borrado por ::eliminar).
 *
 * Todo o nada, en UNA transacción que bloquea la habilitación del observador: si un docente nuevo no forma un par elegible (no es docente vigente, es de
 * otro establecimiento o es el propio observador) se lanza AsignacionAcompanamientoInvalida y no cambia nada.
 * Las asignaciones que se mantienen no se revalidan: el docente que dejó de ser observable sale cuando el
 * administrador lo desmarca.
 */
class AsignarDocentesAObservador
{
    public function __construct(private readonly RolesDelModulo $roles) {}

    /**
     * @param  array<int|string>  $docenteIds
     * @return array{agregados: list<int>, quitados: list<int>}
     *
     * @throws AuthorizationException si $administrador no es el administrador del módulo autenticado
     * @throws AsignacionAcompanamientoInvalida si el observador no puede observar o un par nuevo no es elegible
     */
    public function sincronizar(Usuario $administrador, int $observadorId, array $docenteIds): array
    {
        $gate = Gate::forUser($administrador);
        $gate->authorize('gestionar-observadores');

        $elegidos = array_values(array_unique(array_map('intval', $docenteIds)));

        return DB::transaction(function () use ($gate, $observadorId, $elegidos): array {
            // R3-001: bloquea la fila de `observadores` y revisa al observador DENTRO de la transacción. Un
            // QuitarObservador simultáneo espera a que esta termine (y luego borra también los pares nuevos), o ya
            // la borró y aquí se rechaza: nunca quedan pares de un observador quitado.
            Observador::query()->where('funcionario_id', $observadorId)->lockForUpdate()->get(['id']);

            if (! $this->roles->puedeObservar($observadorId)) {
                throw AsignacionAcompanamientoInvalida::observadorNoHabilitado($observadorId);
            }

            $actuales = AsignacionAcompanamiento::query()
                ->where('observador_id', $observadorId)
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (AsignacionAcompanamiento $asignacion): int => (int) $asignacion->docente_id);

            $agregados = array_values(array_diff($elegidos, $actuales->keys()->all()));
            sort($agregados);

            foreach ($agregados as $docenteId) {
                if ($docenteId === $observadorId) {
                    throw AsignacionAcompanamientoInvalida::mismoFuncionario($docenteId);
                }

                if (! AsignacionAcompanamiento::esParElegible($observadorId, $docenteId)) {
                    throw AsignacionAcompanamientoInvalida::docenteNoHabilitado($docenteId);
                }

                $gate->authorize('crear', [AsignacionAcompanamiento::class, $observadorId, $docenteId]);
                AsignacionAcompanamiento::query()->createOrFirst(['observador_id' => $observadorId, 'docente_id' => $docenteId]);
            }

            $quitados = [];

            foreach ($actuales as $docenteId => $asignacion) {
                if (in_array($docenteId, $elegidos, true)) {
                    continue;
                }

                $gate->authorize('eliminar', $asignacion);
                $asignacion->delete();
                $quitados[] = $docenteId;
            }

            sort($quitados);

            return ['agregados' => $agregados, 'quitados' => $quitados];
        });
    }
}
