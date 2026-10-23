<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEstablecimiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vínculo de un funcionario con una función del catálogo (tabla `funcionario_funciones`).
 *
 * @host-owned Copied from Vinkured app/Models/FuncionarioFuncion.php — do not modify here; replace with the host class at integration.
 */
class FuncionarioFuncion extends Model
{
    use PerteneceAEstablecimiento;

    protected $table = 'funcionario_funciones';

    /** @var list<string> */
    protected $fillable = ['funcionario_id', 'funcion_id'];

    /**
     * @return BelongsTo<Funcionario, $this>
     */
    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }

    /**
     * @return BelongsTo<Funcion, $this>
     */
    public function funcion(): BelongsTo
    {
        return $this->belongsTo(Funcion::class);
    }
}
