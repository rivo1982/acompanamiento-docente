<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEstablecimiento;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Persona de un establecimiento (ADR-0004: la misma persona real en dos colegios son dos filas).
 *
 * @host-owned Copied from Vinkured app/Models/Persona.php — do not modify here; replace with the host class at integration.
 */
class Persona extends Model
{
    use PerteneceAEstablecimiento;

    protected $table = 'personas';

    /** @var list<string> */
    protected $fillable = [
        'tipo_documento', 'numero_documento', 'nombres', 'primer_apellido', 'segundo_apellido',
        'fecha_nacimiento', 'nacionalidad_id', 'correo', 'telefono', 'direccion',
    ];

    /**
     * Nombres y apellidos en una sola línea, sin espacios sobrantes.
     *
     * @return Attribute<string, never>
     */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(fn (): string => trim(implode(' ', array_filter(
            [$this->nombres, $this->primer_apellido, $this->segundo_apellido],
            fn ($parte) => $parte !== null && $parte !== '',
        ))));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['fecha_nacimiento' => 'date'];
    }
}
