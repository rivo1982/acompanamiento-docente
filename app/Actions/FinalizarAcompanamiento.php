<?php

namespace App\Actions;

use App\Exceptions\AcompanamientoYaFinalizado;
use App\Mail\AcompanamientoFinalizado;
use App\Models\Acompanamiento;
use App\Models\Funcionario;
use App\Models\Membresia;
use App\Models\Usuario;
use App\Support\FormatoDelPanel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate as GateFacade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Finaliza un acompañamiento en curso y avisa por correo a su docente. Solo su observador
 * (AcompanamientoPolicy::finalizar).
 *
 * - Exige un nivel para CADA indicador del snapshot; si falta alguno lanza ValidationException con la clave
 *   `respuestas` (cuántos y qué números) y no cambia nada.
 * - Bloquea la fila y cierra con Acompanamiento::finalizar() dentro de una transacción.
 * - Tras confirmar la transacción encola UN aviso (AcompanamientoFinalizado) al correo de la cuenta del docente:
 *   el usuario de su membresía con acceso en el establecimiento del acompañamiento. Sin cuenta no hay correo: se
 *   registra en el log y el acompañamiento queda finalizado igual (el docente lo verá al entrar al módulo).
 * - Un segundo intento lanza AcompanamientoYaFinalizado (a quien puede verlo) y no envía nada; la misma regla se
 *   repite sobre la fila bloqueada dentro de la transacción.
 * - Un fallo del aviso tras confirmar se registra (Log::error) y no se propaga: el acompañamiento ya quedó finalizado.
 */
class FinalizarAcompanamiento
{
    /**
     * @throws ModelNotFoundException si el acompañamiento no existe en el establecimiento activo
     * @throws AuthorizationException si $observador no es su observador
     * @throws AcompanamientoYaFinalizado si ya estaba finalizado
     * @throws ValidationException si falta el nivel de algún indicador
     */
    public function finalizar(Usuario $observador, int $acompanamientoId): Acompanamiento
    {
        $gate = GateFacade::forUser($observador);
        $acompanamiento = Acompanamiento::query()->findOrFail($acompanamientoId);

        $this->autorizar($gate, $acompanamiento);

        $acompanamiento = DB::transaction(function () use ($gate, $acompanamientoId): Acompanamiento {
            // Se vuelve a autorizar sobre la fila bloqueada: otro intento pudo cerrarla entretanto.
            $acompanamiento = Acompanamiento::query()->lockForUpdate()->findOrFail($acompanamientoId);
            $this->autorizar($gate, $acompanamiento);
            $this->exigirRespuestasCompletas($acompanamiento);
            $acompanamiento->finalizar();

            return $acompanamiento;
        });

        $this->avisarAlDocente($acompanamiento);

        return $acompanamiento;
    }

    /**
     * Autoriza finalizar. Si ya está cerrado, solo quien puede verlo lo sabe; al resto se le niega como a cualquier otro.
     *
     * @throws AuthorizationException
     * @throws AcompanamientoYaFinalizado
     */
    private function autorizar(Gate $gate, Acompanamiento $acompanamiento): void
    {
        if ($acompanamiento->estaFinalizado()) {
            $gate->authorize('ver', $acompanamiento);

            throw AcompanamientoYaFinalizado::para($acompanamiento);
        }

        $gate->authorize('finalizar', $acompanamiento);
    }

    /**
     * @throws ValidationException
     */
    private function exigirRespuestasCompletas(Acompanamiento $acompanamiento): void
    {
        $respondidos = $acompanamiento->respuestas()->pluck('indicador_id')->map(fn (mixed $id): int => (int) $id)->all();

        $faltantes = collect($acompanamiento->pauta_snapshot['indicadores'] ?? [])
            ->reject(fn (array $indicador): bool => in_array((int) $indicador['id'], $respondidos, true))
            ->pluck('numero')
            ->all();

        if ($faltantes === []) {
            return;
        }

        $cantidad = count($faltantes);
        $mensaje = $cantidad === 1
            ? 'Falta asignar el nivel del indicador '.$faltantes[0].'.'
            : "Falta asignar el nivel de {$cantidad} indicadores: ".implode(', ', $faltantes).'.';

        throw ValidationException::withMessages(['respuestas' => $mensaje]);
    }

    /**
     * El acompañamiento ya quedó finalizado: un fallo del aviso (consulta, ruta o cola) se registra y no se propaga.
     */
    private function avisarAlDocente(Acompanamiento $acompanamiento): void
    {
        try {
            $this->encolarAviso($acompanamiento);
        } catch (Throwable $error) {
            Log::error('Acompañamiento finalizado sin aviso por correo: falló el envío del aviso al docente.', [
                'acompanamiento_id' => $acompanamiento->getKey(),
                'error' => $error->getMessage(),
            ]);
        }
    }

    private function encolarAviso(Acompanamiento $acompanamiento): void
    {
        $docente = Funcionario::query()->with('persona')->find($acompanamiento->docente_id);
        $correo = $docente === null ? null : Membresia::query()
            ->conAcceso()
            ->where('establecimiento_id', $acompanamiento->establecimiento_id)
            ->where('persona_id', $docente->persona_id)
            ->with('usuario:id,correo')
            ->orderBy('id')
            ->first()?->usuario?->correo;

        if ($correo === null || $correo === '') {
            Log::warning('Acompañamiento finalizado sin aviso por correo: el docente no tiene una cuenta con acceso.', [
                'acompanamiento_id' => $acompanamiento->getKey(),
                'docente_id' => $acompanamiento->docente_id,
            ]);

            return;
        }

        $observador = Funcionario::query()->with('persona')->find($acompanamiento->observador_id);

        Mail::to($correo)->queue((new AcompanamientoFinalizado(
            observador: (string) $observador?->persona?->nombre_completo,
            fecha: FormatoDelPanel::fecha($acompanamiento->finalizado_en),
            enlace: route(AcompanamientoFinalizado::RUTA, $acompanamiento),
        ))->afterCommit());
    }
}
