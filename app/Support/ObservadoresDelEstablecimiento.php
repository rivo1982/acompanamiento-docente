<?php

namespace App\Support;

use App\Models\AsignacionAcompanamiento;
use App\Models\AsignacionDocente;
use App\Models\Funcion;
use App\Models\Funcionario;
use App\Models\FuncionarioFuncion;
use App\Models\Observador;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lecturas de la pantalla de observadores del establecimiento ACTIVO, con los mismos criterios que
 * RolesDelModulo pero en pocas consultas (no una por funcionario). Solo cuenta a funcionarios vigentes y
 * consulta modelos filtrados por el establecimiento activo. No autoriza: quien la usa exige antes el gate
 * `gestionar-observadores`.
 */
final class ObservadoresDelEstablecimiento
{
    /**
     * Observadores automáticos (directivos) y habilitados (`observadores`), una fila por funcionario, ordenados
     * por nombre. Un directivo que además tiene fila en `observadores` sale una vez, como automático.
     * `docentes_asignados` cuenta sus asignaciones observador → docente.
     *
     * @return list<array{funcionario: Funcionario, automatico: bool, docentes_asignados: int}>
     */
    public function observadores(): array
    {
        $automaticos = $this->idsConFuncionDeCategoria(Funcion::CATEGORIA_DIRECTIVO);

        $funcionarios = Funcionario::query()->vigentes()
            ->where(fn (Builder $consulta) => $consulta
                ->whereIn('id', $automaticos)
                ->orWhereIn('id', Observador::query()->select('funcionario_id')))
            ->with('persona')
            ->get();

        $esAutomatico = array_flip(Funcionario::query()->vigentes()->whereIn('id', $automaticos)
            ->pluck('id')->map(fn (mixed $id): int => (int) $id)->all());

        $asignados = AsignacionAcompanamiento::query()
            ->whereIn('observador_id', $funcionarios->modelKeys())
            ->selectRaw('observador_id, count(*) as total')
            ->groupBy('observador_id')
            ->pluck('total', 'observador_id');

        return $funcionarios
            ->sortBy(fn (Funcionario $funcionario): string => mb_strtolower((string) $funcionario->persona?->nombre_completo))
            ->map(fn (Funcionario $funcionario): array => [
                'funcionario' => $funcionario,
                'automatico' => isset($esAutomatico[(int) $funcionario->getKey()]),
                'docentes_asignados' => (int) ($asignados[$funcionario->getKey()] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Docentes vigentes que se pueden asignar a un observador (función de categoría docente o asignación
     * docente, como RolesDelModulo::puedeSerObservado), con su persona, ordenados por nombre.
     *
     * @return Collection<int, Funcionario>
     */
    public function docentesObservables(): Collection
    {
        return Funcionario::query()->vigentes()
            ->where(fn (Builder $consulta) => $consulta
                ->whereIn('id', $this->idsConFuncionDeCategoria(RolesDelModulo::CATEGORIA_DOCENTE))
                ->orWhereIn('id', AsignacionDocente::query()->select('funcionario_id')))
            ->with('persona')
            ->get()
            ->sortBy(fn (Funcionario $funcionario): string => mb_strtolower((string) $funcionario->persona?->nombre_completo))
            ->values();
    }

    /**
     * Ids de los docentes asignados hoy al observador dado.
     *
     * @return list<int>
     */
    public function docentesAsignados(int $observadorId): array
    {
        return AsignacionAcompanamiento::query()
            ->where('observador_id', $observadorId)
            ->orderBy('docente_id')
            ->pluck('docente_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Subconsulta de los funcionarios con alguna función de la categoría dada.
     *
     * @return Builder<FuncionarioFuncion>
     */
    private function idsConFuncionDeCategoria(string $categoria): Builder
    {
        return FuncionarioFuncion::query()
            ->whereIn('funcion_id', Funcion::query()->where('categoria', $categoria)->select('id'))
            ->select('funcionario_id');
    }
}
