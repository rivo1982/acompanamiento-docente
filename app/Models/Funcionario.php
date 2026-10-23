<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEstablecimiento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @host-owned Copied from Vinkured app/Models/Funcionario.php — do not modify here; replace with the host class at integration. */
class Funcionario extends Model
{
    use PerteneceAEstablecimiento;

    protected $table = 'funcionarios';

    /** @var list<string> */
    protected $fillable = ['persona_id', 'fecha_ingreso', 'fecha_termino'];

    /**
     * Funcionarios en ejercicio HOY (fecha local de la zona de la aplicación): `fecha_ingreso` nula
     * o hasta hoy, Y `fecha_termino` nula o desde hoy. Ambos extremos son inclusivos: quien ingresa
     * hoy o termina hoy está en ejercicio. Quien aún no ingresa no tiene roles.
     *
     * @param  Builder<Funcionario>  $consulta
     */
    public function scopeVigentes(Builder $consulta): void
    {
        $hoy = now((string) config('app.timezone'))->toDateString();

        $consulta
            ->where(fn (Builder $q) => $q->whereNull('fecha_ingreso')->orWhere('fecha_ingreso', '<=', $hoy))
            ->where(fn (Builder $q) => $q->whereNull('fecha_termino')->orWhere('fecha_termino', '>=', $hoy));
    }

    /**
     * @return BelongsTo<Persona, $this>
     */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /**
     * @return HasMany<FuncionarioFuncion, $this>
     */
    public function funcionesAsignadas(): HasMany
    {
        return $this->hasMany(FuncionarioFuncion::class);
    }

    /**
     * @return HasMany<AsignacionDocente, $this>
     */
    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionDocente::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['fecha_ingreso' => 'date', 'fecha_termino' => 'date'];
    }
}
