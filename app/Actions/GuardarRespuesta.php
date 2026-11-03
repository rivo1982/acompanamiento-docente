<?php

namespace App\Actions;

use App\Enums\NivelLogro;
use App\Models\Acompanamiento;
use App\Models\RespuestaAcompanamiento;
use App\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Guarda (crea o reemplaza) el nivel de un indicador en un acompañamiento en curso, con una retroalimentación
 * personal opcional. Solo su observador (AcompanamientoPolicy::responder).
 *
 * - Nivel: uno de los cuatro de NivelLogro.
 * - Retroalimentación personal: se recortan los extremos; vacía queda null; hasta RETROALIMENTACION_MAXIMA caracteres.
 * - Indicador: debe estar congelado en el snapshot del acompañamiento.
 *
 * Bloquea la fila del acompañamiento y vuelve a autorizar dentro de la transacción: si se finalizó entretanto, se
 * niega. Los errores de datos se lanzan como ValidationException con las claves `nivel`,
 * `retroalimentacion_personal` e `indicador`.
 */
class GuardarRespuesta
{
    public const RETROALIMENTACION_MAXIMA = 2000;

    /**
     * @throws ModelNotFoundException si el acompañamiento no existe en el establecimiento activo
     * @throws AuthorizationException si $observador no es su observador o ya está finalizado
     * @throws ValidationException si el nivel, la retroalimentación o el indicador no son válidos
     */
    public function guardar(Usuario $observador, int $acompanamientoId, int $indicadorId, string $nivel, ?string $retroalimentacionPersonal): RespuestaAcompanamiento
    {
        $gate = Gate::forUser($observador);
        $acompanamiento = Acompanamiento::query()->findOrFail($acompanamientoId);
        $gate->authorize('responder', $acompanamiento);

        [$nivelLogro, $retroalimentacion] = $this->validar($acompanamiento, $indicadorId, $nivel, $retroalimentacionPersonal);

        return DB::transaction(function () use ($gate, $acompanamientoId, $indicadorId, $nivelLogro, $retroalimentacion): RespuestaAcompanamiento {
            $gate->authorize('responder', Acompanamiento::query()->lockForUpdate()->findOrFail($acompanamientoId));

            return RespuestaAcompanamiento::query()->updateOrCreate(
                ['acompanamiento_id' => $acompanamientoId, 'indicador_id' => $indicadorId],
                ['nivel' => $nivelLogro, 'retroalimentacion_personal' => $retroalimentacion],
            );
        });
    }

    /**
     * @return array{0: NivelLogro, 1: ?string}
     *
     * @throws ValidationException
     */
    private function validar(Acompanamiento $acompanamiento, int $indicadorId, string $nivel, ?string $retroalimentacionPersonal): array
    {
        $errores = [];
        $nivelLogro = NivelLogro::tryFrom($nivel);

        if ($nivelLogro === null) {
            $errores['nivel'] = 'Elegir uno de los niveles de la pauta.';
        }

        $retroalimentacion = trim((string) $retroalimentacionPersonal);
        $retroalimentacion = $retroalimentacion === '' ? null : $retroalimentacion;

        if ($retroalimentacion !== null && mb_strlen($retroalimentacion) > self::RETROALIMENTACION_MAXIMA) {
            $errores['retroalimentacion_personal'] = 'La retroalimentación puede tener hasta '.self::RETROALIMENTACION_MAXIMA.' caracteres.';
        }

        if ($acompanamiento->indicadorDelSnapshot($indicadorId) === null) {
            $errores['indicador'] = 'El indicador no forma parte de la pauta de este acompañamiento.';
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        return [$nivelLogro, $retroalimentacion];
    }
}
