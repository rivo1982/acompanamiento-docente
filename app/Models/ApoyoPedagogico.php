<?php

namespace App\Models;

use Database\Factories\ApoyoPedagogicoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ficha de apoyo pedagógico de un indicador del catálogo GLOBAL (una por indicador), del anexo de apoyo pedagógico:
 * foco de mejora, acciones metodológica, procedimental y formativa, evidencia de avance y referencias. Las
 * orientaciones generales del anexo están en App\Support\ApoyoPedagogico\Orientaciones.
 */
class ApoyoPedagogico extends Model
{
    /** @use HasFactory<ApoyoPedagogicoFactory> */
    use HasFactory;

    protected $table = 'apoyos_pedagogicos';

    /** @var list<string> */
    protected $fillable = [
        'indicador_id', 'contexto', 'foco_mejora', 'accion_metodologica', 'accion_procedimental', 'accion_formativa',
        'evidencia_avance', 'referencias',
    ];

    /**
     * @return BelongsTo<Indicador, $this>
     */
    public function indicador(): BelongsTo
    {
        return $this->belongsTo(Indicador::class);
    }
}
