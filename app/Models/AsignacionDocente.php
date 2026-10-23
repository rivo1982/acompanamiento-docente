<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEstablecimiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asignación de un funcionario a un curso (jefatura o asignatura). `es_jefatura` define al
 * profesor jefe, así que NO es asignable en masa.
 *
 * @host-owned Copied from Vinkured app/Models/AsignacionDocente.php — do not modify here; replace with the host class at integration.
 */
class AsignacionDocente extends Model
{
    use PerteneceAEstablecimiento;

    protected $table = 'asignaciones_docentes';

    /** @var list<string> */
    protected $fillable = ['funcionario_id', 'curso_id', 'asignatura_id'];

    /**
     * @return BelongsTo<Funcionario, $this>
     */
    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }

    /**
     * @return BelongsTo<Curso, $this>
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['es_jefatura' => 'boolean'];
    }
}
