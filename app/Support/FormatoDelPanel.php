<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Formatos de texto del panel (brief: fecha `dd-mm-aaaa`; números `1.234,5`; "N días hábiles").
 * Solo devuelve texto SIN escapar: las vistas lo imprimen con `{{ }}`. Los textos largos no se
 * recortan aquí: se entregan completos y la vista los recorta con CSS (`line-clamp-2`).
 *
 * @host-owned Copied from Vinkured app/Support/FormatoDelPanel.php — do not modify here; replace with the host class at integration.
 */
final class FormatoDelPanel
{
    /**
     * Fecha `dd-mm-aaaa` en la zona de la aplicación.
     */
    public static function fecha(?CarbonInterface $fecha): string
    {
        return $fecha?->copy()->setTimezone((string) config('app.timezone'))->format('d-m-Y') ?? '';
    }

    /**
     * Fecha y hora `dd-mm-aaaa HH:MM` (24 horas) en la zona de la aplicación.
     */
    public static function fechaYHora(?CarbonInterface $fecha): string
    {
        return $fecha?->copy()->setTimezone((string) config('app.timezone'))->format('d-m-Y H:i') ?? '';
    }

    /**
     * "1 día hábil" o "N días hábiles".
     */
    public static function diasHabiles(int $dias): string
    {
        return $dias === 1 ? '1 día hábil' : "{$dias} días hábiles";
    }

    /**
     * "1 resultado" o "N resultados", con el separador de miles chileno.
     */
    public static function resultados(int $total): string
    {
        return self::entero($total).($total === 1 ? ' resultado' : ' resultados');
    }

    /**
     * Entero con separador de miles chileno: 1234 => "1.234".
     */
    public static function entero(int $numero): string
    {
        return number_format($numero, 0, ',', '.');
    }
}
