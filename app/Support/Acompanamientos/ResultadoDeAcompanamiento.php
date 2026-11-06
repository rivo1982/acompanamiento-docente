<?php

namespace App\Support\Acompanamientos;

use App\Enums\NivelLogro;
use App\Models\Acompanamiento;
use App\Models\ApoyoPedagogico;
use App\Models\RespuestaAcompanamiento;
use App\Support\ApoyoPedagogico\Orientaciones;

/**
 * Retroalimentación de un acompañamiento finalizado, lista para mostrar (pantalla `acompanamientos.show`).
 *
 * Cada indicador sale del snapshot congelado de la pauta (nombre, ámbito, descriptor del nivel y retroalimentación
 * automática), con el nivel y la retroalimentación personal de su respuesta y, bajo Destacado, la ficha de apoyo
 * pedagógico de su indicador si existe. Los indicadores se agrupan como pide el anexo: "Para trabajar ahora" (En
 * inicio y luego En desarrollo), "Para llevar a Destacado" (Logrado) y "Logros destacados" (Destacado), cada grupo en
 * el orden de la pauta. Lee las respuestas y las fichas con una consulta cada una, sin importar cuántos indicadores
 * tenga la pauta.
 */
final class ResultadoDeAcompanamiento
{
    /**
     * @param  list<array{clave: string, titulo: string, descripcion: string, indicadores: list<array<string, mixed>>}>  $secciones
     * @param  list<array{etiqueta: string, cantidad: int, variante: string}>  $resumen
     */
    private function __construct(
        public readonly array $secciones,
        public readonly array $resumen,
    ) {}

    public static function de(Acompanamiento $acompanamiento, Orientaciones $orientaciones): self
    {
        $indicadores = $acompanamiento->pauta_snapshot['indicadores'] ?? [];

        $respuestas = RespuestaAcompanamiento::query()
            ->where('acompanamiento_id', $acompanamiento->getKey())
            ->get()
            ->keyBy(fn (RespuestaAcompanamiento $respuesta): int => (int) $respuesta->indicador_id);

        $fichas = ApoyoPedagogico::query()
            ->whereIn('indicador_id', array_map(fn (array $indicador): int => (int) $indicador['id'], $indicadores))
            ->get()
            ->keyBy(fn (ApoyoPedagogico $ficha): int => (int) $ficha->indicador_id);

        /** @var array<string, list<array<string, mixed>>> $porNivel */
        $porNivel = array_fill_keys(array_map(fn (NivelLogro $nivel): string => $nivel->value, NivelLogro::cases()), []);

        foreach ($indicadores as $indicador) {
            $respuesta = $respuestas->get((int) $indicador['id']);

            if ($respuesta === null) {
                continue;
            }

            $porNivel[$respuesta->nivel->value][] = self::indicador($indicador, $respuesta, $fichas->get((int) $indicador['id']), $orientaciones);
        }

        $secciones = array_values(array_filter([
            [
                'clave' => 'trabajar',
                'titulo' => 'Para trabajar ahora',
                'descripcion' => 'Indicadores en inicio y en desarrollo. Elegir uno o dos para el próximo ciclo de acompañamiento y acordar su plan de mejora.',
                'indicadores' => [...$porNivel[NivelLogro::EnInicio->value], ...$porNivel[NivelLogro::EnDesarrollo->value]],
            ],
            [
                'clave' => 'destacado',
                'titulo' => 'Para llevar a Destacado',
                'descripcion' => 'Indicadores logrados, con su ficha para dar el siguiente paso.',
                'indicadores' => $porNivel[NivelLogro::Logrado->value],
            ],
            [
                'clave' => 'logros',
                'titulo' => 'Logros destacados',
                'descripcion' => 'Prácticas que se adaptan a la diversidad del grupo y que los estudiantes ejercen con autonomía.',
                'indicadores' => $porNivel[NivelLogro::Destacado->value],
            ],
        ], fn (array $seccion): bool => $seccion['indicadores'] !== []));

        $resumen = array_map(fn (NivelLogro $nivel): array => [
            'etiqueta' => $nivel->label(),
            'cantidad' => count($porNivel[$nivel->value]),
            'variante' => self::variante($nivel),
        ], NivelLogro::cases());

        return new self($secciones, $resumen);
    }

    /**
     * Variante de insignia (paleta de estados del brief) que identifica cada nivel, siempre junto a su nombre.
     */
    public static function variante(NivelLogro $nivel): string
    {
        return match ($nivel) {
            NivelLogro::EnInicio => 'aviso',
            NivelLogro::EnDesarrollo => 'neutro',
            NivelLogro::Logrado => 'exito',
            NivelLogro::Destacado => 'informacion',
        };
    }

    /**
     * @param  array<string, mixed>  $indicador  Entrada del snapshot.
     * @return array<string, mixed>
     */
    private static function indicador(array $indicador, RespuestaAcompanamiento $respuesta, ?ApoyoPedagogico $ficha, Orientaciones $orientaciones): array
    {
        $nivel = $respuesta->nivel;

        $siguientePaso = match ($nivel) {
            NivelLogro::EnInicio, NivelLogro::EnDesarrollo => ['titulo' => 'Para llegar a Logrado', 'texto' => self::texto($indicador['retro_para_logrado'] ?? null)],
            NivelLogro::Logrado => ['titulo' => 'Para llegar a Destacado', 'texto' => self::texto($indicador['retro_para_destacado'] ?? null)],
            NivelLogro::Destacado => null,
        };

        return [
            'id' => (int) $indicador['id'],
            'numero' => (int) $indicador['numero'],
            'nombre' => (string) $indicador['nombre'],
            'ambito' => self::texto($indicador['ambito'] ?? null),
            'nivel' => $nivel->label(),
            'variante' => self::variante($nivel),
            'descriptor' => self::texto($indicador['niveles'][$nivel->value] ?? null),
            'queSignifica' => self::texto($indicador['retro_que_significa'] ?? null),
            'siguientePaso' => $siguientePaso !== null && $siguientePaso['texto'] !== null ? $siguientePaso : null,
            'fuentes' => self::texto($indicador['fuentes'] ?? null),
            'felicitacion' => $nivel->mensajeFelicitacion(),
            'retroalimentacionPersonal' => self::texto($respuesta->retroalimentacion_personal),
            'ficha' => $nivel === NivelLogro::Destacado || $ficha === null ? null : [
                'foco' => (string) $ficha->foco_mejora,
                'metodologica' => (string) $ficha->accion_metodologica,
                'procedimental' => (string) $ficha->accion_procedimental,
                'formativa' => (string) $ficha->accion_formativa,
                'evidencia' => (string) $ficha->evidencia_avance,
                'referencias' => (string) $ficha->referencias,
            ],
            'notaProtocolo' => $orientaciones->requiereProtocolo((int) $indicador['numero']) ? $orientaciones->notaProtocolo() : null,
        ];
    }

    private static function texto(mixed $valor): ?string
    {
        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }
}
