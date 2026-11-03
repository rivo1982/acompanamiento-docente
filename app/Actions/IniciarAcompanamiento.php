<?php

namespace App\Actions;

use App\Models\Acompanamiento;
use App\Models\AsignacionAcompanamiento;
use App\Models\Curso;
use App\Models\Funcionario;
use App\Models\Pauta;
use App\Models\Usuario;
use App\Support\EstablecimientoActual;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Inicia un acompañamiento de aula en el establecimiento ACTIVO: un observador, para un docente que tiene asignado,
 * con una pauta del establecimiento, un curso y una asignatura (AcompanamientoPolicy::crear).
 *
 * - El observador es el funcionario vigente de $observador (su membresía con acceso en el establecimiento activo) que
 *   tiene la asignación con ese docente; si tiene varios asignados, el de menor id (determinista). Se resuelve desde
 *   el usuario dado, nunca desde el contexto de la sesión; si no hay ninguno lanza AuthorizationException.
 * - La pauta debe existir en el establecimiento, no estar eliminada y tener al menos un indicador.
 * - El curso debe ser del establecimiento activo; la asignatura, existir en el catálogo `asignaturas`. No se exige
 *   que coincidan con las asignaciones docentes del profesor.
 * - El inicio (`iniciado_en`) lo fija el servidor al crear: nunca viene de la entrada.
 *
 * Los errores de datos se lanzan como ValidationException con las claves `pauta`, `curso` y `asignatura`.
 */
class IniciarAcompanamiento
{
    public function __construct(private readonly EstablecimientoActual $actual) {}

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
        $observadorId = $this->observadorAsignado($observador, $docenteId);

        return DB::transaction(fn (): Acompanamiento => Acompanamiento::query()->create([
            'pauta_id' => $pautaId,
            'observador_id' => $observadorId,
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
     * Funcionario vigente de $observador en el establecimiento activo que tiene asignado al docente.
     *
     * @throws AuthorizationException si no tiene ninguno
     */
    private function observadorAsignado(Usuario $observador, int $docenteId): int
    {
        $personaId = $observador->membresiasConAcceso()
            ->where('establecimiento_id', $this->actual->id())
            ->value('persona_id');

        $observadorId = $personaId === null ? null : AsignacionAcompanamiento::query()
            ->whereIn('observador_id', Funcionario::query()->vigentes()->where('persona_id', $personaId)->select('id'))
            ->where('docente_id', $docenteId)
            ->min('observador_id');

        if ($observadorId === null) {
            throw new AuthorizationException('El usuario no es un observador asignado a este docente.');
        }

        return (int) $observadorId;
    }
}
