<?php

namespace App\Actions;

use App\Models\Acompanamiento;
use App\Models\AsignacionAcompanamiento;
use App\Models\Curso;
use App\Models\Funcionario;
use App\Models\Pauta;
use App\Models\Usuario;
use App\Support\RolesDelModulo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Inicia un acompañamiento de aula en el establecimiento ACTIVO: un observador, para un docente que tiene asignado,
 * con una pauta del establecimiento, un curso y una asignatura (AcompanamientoPolicy::crear).
 *
 * - El observador es el funcionario vigente del usuario que tiene la asignación con ese docente; si el usuario tiene
 *   varios funcionarios asignados, el de menor id (determinista).
 * - La pauta debe existir en el establecimiento, no estar eliminada y tener al menos un indicador.
 * - El curso debe ser del establecimiento activo; la asignatura, existir en el catálogo `asignaturas`. No se exige
 *   que coincidan con las asignaciones docentes del profesor.
 * - El inicio (`iniciado_en`) lo fija el servidor al crear: nunca viene de la entrada.
 *
 * Los errores de datos se lanzan como ValidationException con las claves `pauta`, `curso` y `asignatura`.
 */
class IniciarAcompanamiento
{
    public function __construct(private readonly RolesDelModulo $roles) {}

    /**
     * @throws ModelNotFoundException si el docente no es funcionario del establecimiento activo
     * @throws AuthorizationException si $observador no es un observador asignado a ese docente
     * @throws ValidationException si la pauta, el curso o la asignatura no son válidos
     */
    public function iniciar(Usuario $observador, int $docenteId, int $pautaId, int $cursoId, int $asignaturaId): Acompanamiento
    {
        $docente = Funcionario::query()->findOrFail($docenteId);
        Gate::forUser($observador)->authorize('crear', [Acompanamiento::class, $docente]);

        $this->validar($pautaId, $cursoId, $asignaturaId);

        return DB::transaction(fn (): Acompanamiento => Acompanamiento::query()->create([
            'pauta_id' => $pautaId,
            'observador_id' => $this->observadorAsignado($docenteId),
            'docente_id' => $docenteId,
            'curso_id' => $cursoId,
            'asignatura_id' => $asignaturaId,
        ]));
    }

    /**
     * @throws ValidationException
     */
    private function validar(int $pautaId, int $cursoId, int $asignaturaId): void
    {
        $errores = [];
        $pauta = Pauta::query()->withCount('indicadores')->find($pautaId);

        if ($pauta === null) {
            $errores['pauta'] = 'La pauta elegida no está disponible.';
        } elseif ($pauta->indicadores_count === 0) {
            $errores['pauta'] = 'La pauta elegida no tiene indicadores.';
        }

        if (! Curso::query()->whereKey($cursoId)->exists()) {
            $errores['curso'] = 'El curso elegido no está disponible.';
        }

        if (! DB::table('asignaturas')->where('id', $asignaturaId)->exists()) {
            $errores['asignatura'] = 'La asignatura elegida no está disponible.';
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
    }

    /**
     * Funcionario del usuario autenticado que tiene asignado al docente. La política ya garantizó que existe.
     */
    private function observadorAsignado(int $docenteId): int
    {
        return (int) AsignacionAcompanamiento::query()
            ->whereIn('observador_id', $this->roles->funcionarios())
            ->where('docente_id', $docenteId)
            ->min('observador_id');
    }
}
