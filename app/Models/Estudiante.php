<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEstablecimiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** @host-owned Copied from Vinkured app/Models/Estudiante.php — do not modify here; replace with the host class at integration. */
class Estudiante extends Model
{
    use PerteneceAEstablecimiento;

    protected $table = 'estudiantes';

    /** @var list<string> */
    protected $fillable = ['persona_id', 'fecha_ingreso', 'fecha_retiro'];

    /**
     * @return BelongsTo<Persona, $this>
     */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /**
     * @return HasMany<Matricula, $this>
     */
    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    /**
     * Matrícula vigente (sin fecha de retiro); si hubiera más de una, la más reciente.
     *
     * @return HasOne<Matricula, $this>
     */
    public function matriculaVigente(): HasOne
    {
        return $this->hasOne(Matricula::class)->whereNull('fecha_retiro')->latest('fecha_matricula')->latest('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['fecha_ingreso' => 'date', 'fecha_retiro' => 'date'];
    }
}
