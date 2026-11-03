<?php

namespace App\Support\ApoyoPedagogico;

use JsonException;

/**
 * Orientaciones generales del anexo de apoyo pedagógico (`orientaciones` de apoyo_pedagogico.json): cómo usar las
 * fichas, el ciclo sugerido de acompañamiento, la nota de uso, la nota de protocolo y el plan de mejora. Son textos
 * fijos del anexo, no datos del establecimiento: se leen del archivo una sola vez por proceso.
 */
final class Orientaciones
{
    private static ?self $delAnexo = null;

    /**
     * @param  array{como_usar: string, ciclo_sugerido: list<string>, fundamento_ciclo: string, nota_de_uso: string, nota_protocolo: string, descriptores_con_protocolo: list<int>, plan_de_mejora: string}  $datos
     */
    public function __construct(private readonly array $datos) {}

    public static function archivo(): string
    {
        return database_path('seeders/data/apoyo_pedagogico.json');
    }

    /**
     * @throws JsonException
     */
    public static function delAnexo(): self
    {
        return self::$delAnexo ??= new self(
            json_decode((string) file_get_contents(self::archivo()), true, flags: JSON_THROW_ON_ERROR)['orientaciones'],
        );
    }

    public function comoUsar(): string
    {
        return $this->datos['como_usar'];
    }

    /**
     * Pasos del ciclo sugerido, en orden.
     *
     * @return list<string>
     */
    public function cicloSugerido(): array
    {
        return $this->datos['ciclo_sugerido'];
    }

    public function fundamentoCiclo(): string
    {
        return $this->datos['fundamento_ciclo'];
    }

    public function notaDeUso(): string
    {
        return $this->datos['nota_de_uso'];
    }

    /**
     * Nota que acompaña a los descriptores con protocolo (requiereProtocolo).
     */
    public function notaProtocolo(): string
    {
        return $this->datos['nota_protocolo'];
    }

    /**
     * Si el descriptor (número de indicador) sigue la regla de protocolo de la pauta.
     */
    public function requiereProtocolo(int $numero): bool
    {
        return in_array($numero, $this->datos['descriptores_con_protocolo'], true);
    }

    /**
     * Indicación para la plantilla "Plan de mejora acordado".
     */
    public function planDeMejora(): string
    {
        return $this->datos['plan_de_mejora'];
    }
}
