<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEstablecimiento;
use Database\Factories\AsignacionAcompanamientoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asignación observador → docente, ambos funcionarios del mismo establecimiento. La base
 * impide mezclar establecimientos (claves compuestas) y que un funcionario se observe a sí mismo.
 */
class AsignacionAcompanamiento extends Model
{
    /** @use HasFactory<AsignacionAcompanamientoFactory> */
    use HasFactory, PerteneceAEstablecimiento;

    protected $table = 'asignaciones_acompanamiento';

    /** @var list<string> */
    protected $fillable = ['observador_id', 'docente_id'];

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
