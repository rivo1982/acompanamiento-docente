<?php

namespace App\Models;

use App\Enums\EstadoImportacion;
use App\Models\Concerns\PerteneceAEstablecimiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Carga masiva de funcionarios desde una planilla (Q18), sobre la tabla del anfitrión `importaciones_usuarios`.
 *
 * Clase NUEVA escrita por el módulo para entregarla a Vinkured (Vinkured tiene la tabla pero no el modelo); no es una
 * copia @host-owned. `membresia_id` es la membresía del Administrador que subió la planilla; `archivo` guarda solo el
 * nombre original del archivo, nunca su ruta. `errores` es una lista de {fila: int|null, mensajes: list<string>}
 * (fila null = error del archivo completo) que nunca menciona cuentas de otros establecimientos (ADR-0005).
 *
 * @property int $id
 * @property int $establecimiento_id
 * @property int $membresia_id
 * @property string $archivo
 * @property EstadoImportacion $estado
 * @property int $total
 * @property int $creados
 * @property int $con_error
 * @property list<array{fila: int|null, mensajes: list<string>}>|null $errores
 */
class ImportacionUsuario extends Model
{
    use PerteneceAEstablecimiento;

    protected $table = 'importaciones_usuarios';

    /**
     * Nada es asignable en masa: lo escriben IniciarImportacion y ProcesarImportacion con forceFill.
     *
     * @var list<string>
     */
    protected $fillable = [];

    /** @var array<string, mixed> */
    protected $attributes = [
        'estado' => 'pendiente',
        'total' => 0,
        'creados' => 0,
        'con_error' => 0,
    ];

    /**
     * @return BelongsTo<Membresia, $this>
     */
    public function administrador(): BelongsTo
    {
        return $this->belongsTo(Membresia::class, 'membresia_id');
    }

    public function terminada(): bool
    {
        return in_array($this->estado, [EstadoImportacion::Completada, EstadoImportacion::Fallida], true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoImportacion::class,
            'total' => 'integer',
            'creados' => 'integer',
            'con_error' => 'integer',
            'errores' => 'array',
        ];
    }
}
