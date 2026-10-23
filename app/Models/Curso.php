<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEstablecimiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @host-owned Copied from Vinkured app/Models/Curso.php — do not modify here; replace with the host class at integration. */
class Curso extends Model
{
    use PerteneceAEstablecimiento;

    protected $table = 'cursos';

    /** @var list<string> */
    protected $fillable = ['anio_escolar_id', 'grado_id', 'letra'];

    /**
     * @return BelongsTo<Grado, $this>
     */
    public function grado(): BelongsTo
    {
        return $this->belongsTo(Grado::class);
    }

    /**
     * Nombre para mostrar, p. ej. "5° básico A". Requiere la relación `grado` cargada.
     */
    public function nombreCompleto(): string
    {
        return trim($this->grado->nombre.' '.$this->letra);
    }

    /**
     * @return HasMany<Matricula, $this>
     */
    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    /**
     * @return HasMany<AsignacionDocente, $this>
     */
    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionDocente::class);
    }
}
