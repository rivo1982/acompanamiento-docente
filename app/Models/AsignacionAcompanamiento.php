<?php

namespace App\Models;

use App\Exceptions\AsignacionAcompanamientoInvalida;
use App\Models\Concerns\PerteneceAEstablecimiento;
use App\Support\RolesDelModulo;
use Database\Factories\AsignacionAcompanamientoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asignación observador → docente, ambos funcionarios del mismo establecimiento. La base
 * impide mezclar establecimientos (claves compuestas) y que un funcionario se observe a sí mismo;
 * el modelo exige además que el par sea elegible.
 */
class AsignacionAcompanamiento extends Model
{
    /** @use HasFactory<AsignacionAcompanamientoFactory> */
    use HasFactory, PerteneceAEstablecimiento;

    protected $table = 'asignaciones_acompanamiento';

    /** @var list<string> */
    protected $fillable = ['observador_id', 'docente_id'];

    /**
     * Guarda de elegibilidad (R1-001): el observador debe poder observar y el docente calificar como
     * docente, ambos vigentes en el establecimiento activo, y nunca el mismo funcionario. La base
     * repite lo que puede (mismo establecimiento, par distinto); la elegibilidad vive en RolesDelModulo.
     *
     * @throws AsignacionAcompanamientoInvalida
     */
    protected static function booted(): void
    {
        static::saving(function (AsignacionAcompanamiento $asignacion): void {
            $observadorId = (int) $asignacion->observador_id;
            $docenteId = (int) $asignacion->docente_id;
            $roles = app(RolesDelModulo::class);

            if ($observadorId === $docenteId) {
                throw AsignacionAcompanamientoInvalida::mismoFuncionario($observadorId);
            }

            if (! $roles->puedeObservar($observadorId)) {
                throw AsignacionAcompanamientoInvalida::observadorNoHabilitado($observadorId);
            }

            if (! $roles->puedeSerObservado($docenteId)) {
                throw AsignacionAcompanamientoInvalida::docenteNoHabilitado($docenteId);
            }
        });
    }

    /**
     * Si el par sería una asignación válida en el establecimiento activo.
     */
    public static function esParElegible(int $observadorId, int $docenteId): bool
    {
        $roles = app(RolesDelModulo::class);

        return $observadorId !== $docenteId
            && $roles->puedeObservar($observadorId)
            && $roles->puedeSerObservado($docenteId);
    }

    /**
     * @return BelongsTo<Funcionario, $this>
     */
    public function observador(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class, 'observador_id');
    }

    /**
     * @return BelongsTo<Funcionario, $this>
     */
    public function docente(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class, 'docente_id');
    }
}
