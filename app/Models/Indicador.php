<?php

namespace App\Models;

use App\Enums\NivelLogro;
use Database\Factories\IndicadorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Indicador numerado del catálogo GLOBAL, con sus cuatro descriptores de nivel y su retroalimentación.
 */
class Indicador extends Model
{
    /** @use HasFactory<IndicadorFactory> */
    use HasFactory;

    protected $table = 'indicadores';

    /** @var list<string> */
    protected $fillable = [
        'ambito_id', 'numero', 'nombre',
        'nivel_en_inicio', 'nivel_en_desarrollo', 'nivel_logrado', 'nivel_destacado',
        'retro_que_significa', 'retro_para_logrado', 'retro_para_destacado', 'fuentes',
    ];

    /**
     * Descriptor de este indicador para el nivel dado.
     */
    public function descripcionNivel(NivelLogro $nivel): string
    {
        return match ($nivel) {
            NivelLogro::EnInicio => $this->nivel_en_inicio,
            NivelLogro::EnDesarrollo => $this->nivel_en_desarrollo,
            NivelLogro::Logrado => $this->nivel_logrado,
            NivelLogro::Destacado => $this->nivel_destacado,
        };
    }

    /**
     * @return BelongsTo<Ambito, $this>
     */
    public function ambito(): BelongsTo
    {
        return $this->belongsTo(Ambito::class);
    }

    /**
     * @return HasMany<ApoyoPedagogico, $this>
     */
    public function apoyosPedagogicos(): HasMany
    {
        return $this->hasMany(ApoyoPedagogico::class)->orderBy('posicion');
    }
}
