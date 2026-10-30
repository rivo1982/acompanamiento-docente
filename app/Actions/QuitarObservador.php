<?php

namespace App\Actions;

use App\Models\AsignacionAcompanamiento;
use App\Models\Funcionario;
use App\Models\Observador;
use App\Models\Usuario;
use App\Support\RolesDelModulo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Quita la habilitación de observador a un funcionario del establecimiento activo. Solo el administrador del
 * módulo (gate `gestionar-observadores`). Idempotente: quitar a quien no está habilitado no falla.
 *
 * En UNA transacción borra la fila de `observadores` y TODAS sus asignaciones observador → docente. Las
 * asignaciones no son historial: ningún acompañamiento las referencia (acompanamientos guarda observador y
 * docente directamente), solo autorizan crear acompañamientos nuevos. Conservarlas dejaría pares inválidos que
 * reviven si se lo vuelve a habilitar. Los acompañamientos del ex observador se conservan: el administrador
 * mantiene el historial completo y el ex observador pierde el acceso a los reportes (T3.1 R3-004, ReportePolicy).
 *
 * Un observador automático (directivo, A3) no se puede quitar: observa por sus funciones, no por esta tabla.
 */
class QuitarObservador
{
    public function __construct(private readonly RolesDelModulo $roles) {}

    /**
     * @throws AuthorizationException si $administrador no es el administrador del módulo autenticado
     * @throws ModelNotFoundException si el funcionario no es de este establecimiento
     * @throws ValidationException si el funcionario es observador automático
     */
    public function quitar(Usuario $administrador, int $funcionarioId): void
    {
        Gate::forUser($administrador)->authorize('gestionar-observadores');

        // Sin vigentes(): también se puede quitar a quien ya dejó el establecimiento.
        $funcionario = Funcionario::query()->findOrFail($funcionarioId);

        if ($this->roles->esObservadorAutomatico((int) $funcionario->getKey())) {
            throw ValidationException::withMessages([
                'funcionario' => 'Un directivo observa por sus funciones y no se puede quitar como observador.',
            ]);
        }

        DB::transaction(function () use ($funcionario): void {
            Observador::query()->where('funcionario_id', $funcionario->getKey())->delete();
            AsignacionAcompanamiento::query()->where('observador_id', $funcionario->getKey())->delete();
        });
    }
}
