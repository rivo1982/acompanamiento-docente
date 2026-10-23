<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEstablecimiento;
use Database\Factories\ObservadorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Funcionario habilitado como observador por el módulo. Directivos y UTP observan por
 * derivación de sus funciones (A3), no por esta tabla.
 */
class Observador extends Model
{
    /** @use HasFactory<ObservadorFactory> */
    use HasFactory, PerteneceAEstablecimiento;

    protected $table = 'observadores';

    /** @var list<string> */
    protected $fillable = ['funcionario_id'];

    /**
     * @return BelongsTo<Funcionario, $this>
     */
    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }
}
