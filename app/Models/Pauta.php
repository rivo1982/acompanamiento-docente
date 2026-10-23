<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEstablecimiento;
use Database\Factories\PautaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pauta del establecimiento, armada con indicadores del catálogo.
 */
class Pauta extends Model
{
    /** @use HasFactory<PautaFactory> */
    use HasFactory, PerteneceAEstablecimiento, SoftDeletes;

    protected $table = 'pautas';

    /** @var list<string> */
    protected $fillable = ['nombre'];

    /**
     * @return BelongsToMany<Indicador, $this, IndicadorPauta>
     */
    public function indicadores(): BelongsToMany
    {
        return $this->belongsToMany(Indicador::class, 'indicador_pauta')
            ->using(IndicadorPauta::class)
            ->withPivot('posicion')
            ->withTimestamps()
            ->orderByPivot('posicion');
    }

    /**
     * @return HasMany<Acompanamiento, $this>
     */
    public function acompanamientos(): HasMany
    {
        return $this->hasMany(Acompanamiento::class);
    }

    /**
     * Copia congelada del contenido de la pauta, que se guarda en el acompañamiento al iniciarlo.
     *
     * @return array{id: int, nombre: string, indicadores: list<array{id: int, numero: int, nombre: string, niveles: array<string, string>, retro_que_significa: string, retro_para_logrado: string, retro_para_destacado: ?string, fuentes: string}>}
     */
    public function toSnapshot(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'indicadores' => $this->indicadores()->get()->map(fn (Indicador $indicador): array => [
                'id' => $indicador->id,
                'numero' => $indicador->numero,
                'nombre' => $indicador->nombre,
                'niveles' => [
                    'en_inicio' => $indicador->nivel_en_inicio,
                    'en_desarrollo' => $indicador->nivel_en_desarrollo,
                    'logrado' => $indicador->nivel_logrado,
                    'destacado' => $indicador->nivel_destacado,
                ],
                'retro_que_significa' => $indicador->retro_que_significa,
                'retro_para_logrado' => $indicador->retro_para_logrado,
                'retro_para_destacado' => $indicador->retro_para_destacado,
                'fuentes' => $indicador->fuentes,
            ])->values()->all(),
        ];
    }
}
