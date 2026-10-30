<?php

namespace App\Actions;

use App\Models\Funcionario;
use App\Models\Observador;
use App\Models\Usuario;
use App\Support\RolesDelModulo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Habilita como observador a un funcionario vigente del establecimiento activo (fila en `observadores`).
 * Solo el administrador del módulo (gate `gestionar-observadores`). Idempotente: habilitar a quien ya está
 * habilitado devuelve la fila existente. Un directivo ya observa por sus funciones (A3): se rechaza, para que
 * la tabla solo guarde las habilitaciones que el administrador puede quitar.
 */
class HabilitarObservador
{
    public function __construct(private readonly RolesDelModulo $roles) {}

    /**
     * @throws AuthorizationException si $administrador no es el administrador del módulo autenticado
     * @throws ModelNotFoundException si el funcionario no es vigente o no es de este establecimiento
     * @throws ValidationException si el funcionario ya es observador automático
     */
    public function habilitar(Usuario $administrador, int $funcionarioId): Observador
    {
        Gate::forUser($administrador)->authorize('gestionar-observadores');

        $funcionario = Funcionario::query()->vigentes()->findOrFail($funcionarioId);

        if ($this->roles->esObservadorAutomatico((int) $funcionario->getKey())) {
            throw ValidationException::withMessages([
                'funcionario' => 'Este funcionario ya observa por ser directivo; no necesita habilitarse.',
            ]);
        }

        // firstOrCreate usa createOrFirst: una habilitación simultánea choca con la unicidad y se relee.
        return Observador::query()->firstOrCreate(['funcionario_id' => $funcionario->getKey()]);
    }
}
