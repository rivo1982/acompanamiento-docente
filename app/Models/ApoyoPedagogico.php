<?php

namespace App\Models;

use App\Enums\NivelObjetivoApoyo;
use Database\Factories\ApoyoPedagogicoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Apoyo pedagógico de un indicador del catálogo GLOBAL.
 */
class ApoyoPedagogico extends Model
{
    /** @use HasFactory<ApoyoPedagogicoFactory> */
    use HasFactory;

    protected $table = 'apoyos_pedagogicos';

    /** @var list<string> */
    protected $fillable = ['indicador_id', 'posicion', 'herramienta', 'nivel_objetivo', 'como_hacerlo', 'ejemplo', 'duracion', 'senal_de_logro'];

    /**
     * @return BelongsTo<Indicador, $this>
     */
    public function indicador(): BelongsTo
    {
        return $this->belongsTo(Indicador::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['nivel_objetivo' => NivelObjetivoApoyo::class];
    }
}
