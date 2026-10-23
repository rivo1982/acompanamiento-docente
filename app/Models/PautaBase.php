<?php

namespace App\Models;

use Database\Factories\PautaBaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Pauta base del catálogo GLOBAL de la plataforma. Sin filtro por establecimiento.
 */
class PautaBase extends Model
{
    /** @use HasFactory<PautaBaseFactory> */
    use HasFactory;

    protected $table = 'pautas_base';

    /** @var list<string> */
    protected $fillable = ['numero', 'nombre', 'evidencia', 'estandares', 'protocolo', 'advertencia_uso'];

    /**
     * @return HasMany<Ambito, $this>
     */
    public function ambitos(): HasMany
    {
        return $this->hasMany(Ambito::class)->orderBy('posicion');
    }

    /**
     * @return HasManyThrough<Indicador, Ambito, $this>
     */
    public function indicadores(): HasManyThrough
    {
        return $this->hasManyThrough(Indicador::class, Ambito::class);
    }
}
