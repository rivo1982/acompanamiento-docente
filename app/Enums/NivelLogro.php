<?php

namespace App\Enums;

/**
 * Nivel formativo de logro asignado a un indicador en un acompañamiento
 * (`respuestas_acompanamiento.nivel`). El CHECK de la base repite los valores; una prueba
 * compara ambos.
 */
enum NivelLogro: string
{
    case EnInicio = 'en_inicio';
    case EnDesarrollo = 'en_desarrollo';
    case Logrado = 'logrado';
    case Destacado = 'destacado';

    /**
     * Texto para mostrar.
     */
    public function label(): string
    {
        return match ($this) {
            self::EnInicio => 'En inicio',
            self::EnDesarrollo => 'En desarrollo',
            self::Logrado => 'Logrado',
            self::Destacado => 'Destacado',
        };
    }

    /**
     * Significado general del nivel, mostrado en toda pauta. En logrado y destacado termina con
     * la misma felicitación de mensajeFelicitacion(), única fuente de ese texto.
     */
    public function significado(): string
    {
        $descripcion = match ($this) {
            self::EnInicio => 'La práctica aparece de forma aislada o con efecto limitado en los estudiantes. El descriptor no aplica a la clase y no es posible observar.',
            self::EnDesarrollo => 'Aparece en parte de la clase o con parte del grupo, con vacíos que limitan su efecto.',
            self::Logrado => 'Se da de manera consistente y los estudiantes responden como se espera.',
            self::Destacado => 'Además de lo logrado, la práctica se adapta a la diversidad del grupo y los estudiantes la ejercen con autonomía.',
        };

        $felicitacion = $this->mensajeFelicitacion();

        return $felicitacion === null ? $descripcion : "{$descripcion} {$felicitacion}";
    }

    /**
     * Felicitación que se agrega a la retroalimentación automática, si el nivel la merece.
     */
    public function mensajeFelicitacion(): ?string
    {
        return match ($this) {
            self::Logrado => 'Felicitaciones.',
            self::Destacado => 'Felicitaciones por su labor y su exitoso desarrollo profesional.',
            default => null,
        };
    }

    /**
     * Tabla de significados en orden ascendente.
     *
     * @return list<array{nivel: string, etiqueta: string, significado: string}>
     */
    public static function tablaSignificados(): array
    {
        return array_map(
            fn (self $nivel): array => [
                'nivel' => $nivel->value,
                'etiqueta' => $nivel->label(),
                'significado' => $nivel->significado(),
            ],
            self::cases(),
        );
    }
}
