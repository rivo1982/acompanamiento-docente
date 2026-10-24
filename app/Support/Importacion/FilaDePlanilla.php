<?php

namespace App\Support\Importacion;

use App\Support\Rut;

/**
 * Un funcionario de la planilla de carga masiva, ya validado y normalizado.
 */
final readonly class FilaDePlanilla
{
    /**
     * @param  int  $numeroDeFila  número de fila tal como se ve en Excel (el encabezado es la fila 1)
     * @param  string  $correo  recortado y en minúsculas
     * @param  string  $categoriaFuncion  valor de funciones.categoria en Vinkured: docente, directivo, asistente o profesional_apoyo
     */
    public function __construct(
        public int $numeroDeFila,
        public Rut $rut,
        public string $nombres,
        public string $primerApellido,
        public ?string $segundoApellido,
        public string $correo,
        public ?string $telefono,
        public string $categoriaFuncion,
    ) {}
}
