<?php

namespace App\Support\Importacion;

/**
 * Resultado de validar UN funcionario (una fila de la planilla o la carga unitaria): la fila normalizada si es válida,
 * o sus errores por campo (ValidadorDePlanilla::CAMPOS, más CAMPO_FILA), en el orden de las columnas. Los mensajes son
 * los mismos de la planilla y nunca dicen si una persona o un correo ya existe en VINKURED (ADR-0005).
 */
final readonly class ValidacionDeFuncionario
{
    /** Número de fila de un funcionario que no viene de una planilla (carga unitaria). */
    public const SIN_FILA = 0;

    /**
     * @param  array<string, list<string>>  $erroresPorCampo
     */
    public function __construct(
        public ?FilaDePlanilla $fila,
        public array $erroresPorCampo,
    ) {}

    /**
     * Todos los mensajes en el orden de las columnas (como los guarda la carga masiva).
     *
     * @return list<string>
     */
    public function mensajes(): array
    {
        return array_merge(...array_values($this->erroresPorCampo));
    }
}
