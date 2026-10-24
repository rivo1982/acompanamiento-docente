<?php

namespace App\Support\Importacion;

/**
 * Resultado de validar una planilla: errores del archivo completo (encabezado, vacía, demasiado grande), filas
 * válidas y errores por fila. Si hay errores del archivo no se entregan filas.
 */
final readonly class ResultadoDeValidacion
{
    /**
     * @param  list<string>  $erroresDelArchivo
     * @param  list<FilaDePlanilla>  $filasValidas
     * @param  array<int, list<string>>  $erroresPorFila  mensajes por número de fila de Excel
     */
    public function __construct(
        public array $erroresDelArchivo,
        public array $filasValidas,
        public array $erroresPorFila,
    ) {}

    public static function conErrorDelArchivo(string $mensaje): self
    {
        return new self([$mensaje], [], []);
    }

    public function esValido(): bool
    {
        return $this->erroresDelArchivo === [] && $this->erroresPorFila === [];
    }
}
