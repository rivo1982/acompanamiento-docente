<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEstablecimiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @host-owned Copied from Vinkured app/Models/Matricula.php — do not modify here; replace with the host class at integration. */
class Matricula extends Model
{
    use PerteneceAEstablecimiento;

    protected $table = 'matriculas';

    /** @var list<string> */
    protected $fillable = ['estudiante_id', 'curso_id', 'fecha_matricula', 'fecha_retiro'];

    /**
     * @return BelongsTo<Estudiante, $this>
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
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
        return ['fecha_matricula' => 'date', 'fecha_retiro' => 'date'];
    }
}
