<?php

namespace App\Models;

use Database\Factories\AmbitoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ámbito de una pauta base del catálogo GLOBAL.
 */
class Ambito extends Model
{
    /** @use HasFactory<AmbitoFactory> */
    use HasFactory;

    protected $table = 'ambitos';

    /** @var list<string> */
    protected $fillable = ['pauta_base_id', 'nombre', 'posicion'];

    /**
     * @return BelongsTo<PautaBase, $this>
     */
    public function pautaBase(): BelongsTo
    {
        return $this->belongsTo(PautaBase::class);
    }

    /**
     * @return HasMany<Indicador, $this>
     */
    public function indicadores(): HasMany
    {
        return $this->hasMany(Indicador::class)->orderBy('numero');
    }
}
