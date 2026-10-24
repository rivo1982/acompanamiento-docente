<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Estado de un plazo frente al reloj, en días hábiles: En plazo (N), Vence hoy o Vencido (N).
 * Valor inmutable; lo crea `calcular()` y lo muestra el panel como insignia (el texto siempre
 * está presente: nunca solo color).
 *
 * Reglas de `calcular($vencimiento, $ahora, ...)`, con fechas civiles en la zona de la aplicación:
 * - `$ahora` posterior al instante de vencimiento => Vencido. `dias` = días hábiles entre la fecha
 *   local del vencimiento y la de hoy, con mínimo 1 si las fechas difieren (aunque no haya pasado
 *   ningún día hábil, p. ej. vencía un sábado). Si es el mismo día local: Vencido con 0 ("hoy").
 * - Misma fecha local y la hora aún no pasó (incluye la hora exacta) => Vence hoy.
 * - Si no, En plazo con `dias` = días hábiles desde hoy hasta la fecha local del vencimiento,
 *   incluida esta; puede ser 0 si el vencimiento cae en un día no hábil posterior a hoy.
 *
 * @host-owned Copied from Vinkured app/Support/EstadoDePlazo.php — do not modify here; replace with the host class at integration.
 */
final readonly class EstadoDePlazo
{
    public const EN_PLAZO = 'en_plazo';

    public const VENCE_HOY = 'vence_hoy';

    public const VENCIDO = 'vencido';

    private function __construct(public string $tipo, public int $dias)
    {
        if ($dias < 0) {
            throw new InvalidArgumentException('Los días hábiles de un plazo no pueden ser negativos.');
        }
    }

    public static function enPlazo(int $dias): self
    {
        return new self(self::EN_PLAZO, $dias);
    }

    public static function venceHoy(): self
    {
        return new self(self::VENCE_HOY, 0);
    }

    public static function vencido(int $dias): self
    {
        return new self(self::VENCIDO, $dias);
    }

    /**
     * @param  ?DateTimeInterface  $ahora  null = instante actual
     */
    public static function calcular(
        DateTimeInterface $vencimiento,
        ?DateTimeInterface $ahora,
        DiasHabiles $dias,
        ?int $establecimientoId = null,
    ): self {
        $ahora ??= CarbonImmutable::now();

        $fechaVencimiento = $dias->fechaLocal($vencimiento);
        $hoy = $dias->fechaLocal($ahora);
        $mismoDia = $fechaVencimiento === $hoy;

        // Falla cerrado (sin establecimiento activo ni id explícito) también cuando no hace falta contar.
        $dias->esHabil($hoy, $establecimientoId);

        if ($ahora > $vencimiento) {
            return self::vencido($mismoDia ? 0 : max(1, $dias->entre($fechaVencimiento, $hoy, $establecimientoId)));
        }

        if ($mismoDia) {
            return self::venceHoy();
        }

        return self::enPlazo($dias->entre($hoy, $fechaVencimiento, $establecimientoId));
    }

    /**
     * Como `calcular()` con el instante actual, pero devuelve null si la fecha no se puede calcular
     * (p. ej. fuera de ±20 años): las pantallas muestran "Plazo no disponible" en vez de fallar enteras.
     * Sigue fallando cerrado sin establecimiento activo.
     */
    public static function calcularONulo(DateTimeInterface $vencimiento, DiasHabiles $dias): ?self
    {
        try {
            return self::calcular($vencimiento, null, $dias);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function esVencido(): bool
    {
        return $this->tipo === self::VENCIDO;
    }

    /**
     * Texto en español de Chile: "En plazo (2 días hábiles)", "Vence hoy", "Vencido hace 1 día hábil".
     */
    public function etiqueta(): string
    {
        return match ($this->tipo) {
            self::EN_PLAZO => 'En plazo ('.self::cantidad($this->dias).')',
            self::VENCE_HOY => 'Vence hoy',
            default => $this->dias === 0 ? 'Vencido hoy' : 'Vencido hace '.self::cantidad($this->dias),
        };
    }

    /**
     * Variante de `<x-badge>`: neutro, aviso o peligro.
     */
    public function variante(): string
    {
        return match ($this->tipo) {
            self::EN_PLAZO => 'neutro',
            self::VENCE_HOY => 'aviso',
            default => 'peligro',
        };
    }

    private static function cantidad(int $dias): string
    {
        return $dias === 1 ? '1 día hábil' : "{$dias} días hábiles";
    }
}
