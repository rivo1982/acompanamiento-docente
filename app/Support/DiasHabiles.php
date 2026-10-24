<?php

namespace App\Support;

use App\Exceptions\SinEstablecimientoActivo;
use App\Models\DiaNoHabil;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Throwable;

/**
 * Cálculo de días hábiles de un establecimiento. Servicio puro: sin UI ni efectos, solo lee
 * `dias_no_habiles` (una consulta por ventana, memoizada por petición).
 *
 * Convenciones (decididas; no se reabren aquí):
 * - Día hábil: de lunes a viernes y que NO figure en `dias_no_habiles`. Sábados y domingos nunca
 *   son hábiles. Las filas con `establecimiento_id` nulo (feriados nacionales) valen para todos;
 *   las de un establecimiento, solo para él. Un feriado en fin de semana no cambia nada.
 * - Zona horaria: la de la aplicación (`config('app.timezone')`, America/Santiago). Un instante
 *   se convierte a su FECHA CIVIL local antes de comparar; una cadena `AAAA-MM-DD` ya es una fecha
 *   civil y no se reinterpreta. Dentro del servicio los días son números enteros (días desde 1970,
 *   en UTC), sin horas, así que los cambios de hora de Chile (abril y septiembre, a medianoche:
 *   el 00:00 del domingo de septiembre no existe) no pueden mover una fecha.
 * - Conteo: el día de partida se EXCLUYE y el de llegada se INCLUYE. "N días hábiles después del
 *   lunes" termina en el N-ésimo día hábil posterior al lunes. Partir de un sábado o feriado es
 *   válido: el primer día hábil siguiente cuenta como 1.
 * - `sumar($f, $n)` / `restar($f, $n)`: un $n negativo equivale a la operación contraria.
 *   Con `$n = 0` devuelven la misma fecha SIN ajustarla, aunque no sea hábil.
 * - `entre($desde, $hasta)`: cantidad CON SIGNO de días hábiles; positiva si $hasta es posterior,
 *   y `entre(a, b) === -entre(b, a)`. Si $a es hábil: `entre(a, sumar(a, n)) === n`.
 * - Las fechas devueltas son CarbonImmutable a las 12:00 locales (mediodía evita los huecos y
 *   repeticiones horarias); solo la fecha es significativa.
 *
 * Aislamiento: el establecimiento sale de EstablecimientoActual o de un id explícito (para
 * trabajos fuera de una petición). Sin ninguno lanza SinEstablecimientoActivo (falla cerrado).
 * Los feriados de un establecimiento nunca afectan a otro: la memoria se indexa por id y la
 * consulta pasa por DiaNoHabil::paraEstablecimiento().
 *
 * No incluye el calendario oficial de feriados de Chile (fuente pendiente de decidir).
 *
 * @host-owned Copied from Vinkured app/Support/DiasHabiles.php — do not modify here; replace with the host class at integration.
 */
final class DiasHabiles
{
    /** Días de holgura que se cargan a cada lado de lo pedido, para no consultar en cada fecha. */
    private const HOLGURA = 120;

    /** Las fechas deben estar a ±20 años de hoy; acota el recorrido día a día de `entre()` (~7.300 días por lado). */
    public const ANIOS_PLAUSIBLES = 20;

    /** Máximo de días hábiles que acepta `sumar`/`restar` (~20 años de lunes a viernes). */
    public const MAX_DIAS_HABILES = 5300;

    /** @var array<int, array{desde: int, hasta: int, dias: array<int, true>}> por establecimiento */
    private array $calendarios = [];

    public function __construct(private readonly EstablecimientoActual $actual) {}

    public function sumar(mixed $fecha, int $n, ?int $establecimientoId = null): CarbonImmutable
    {
        return $this->desplazar($fecha, $n, $establecimientoId);
    }

    public function restar(mixed $fecha, int $n, ?int $establecimientoId = null): CarbonImmutable
    {
        return $this->desplazar($fecha, -$n, $establecimientoId);
    }

    /**
     * Días hábiles con signo entre dos fechas (excluye $desde, incluye $hasta).
     */
    public function entre(mixed $desde, mixed $hasta, ?int $establecimientoId = null): int
    {
        $a = $this->plausible($this->dia($desde));
        $b = $this->plausible($this->dia($hasta));
        $est = $this->establecimiento($establecimientoId);

        if ($a === $b) {
            return 0;
        }

        [$menor, $mayor] = $a < $b ? [$a, $b] : [$b, $a];
        $this->asegurar($est, $menor, $mayor);

        $cantidad = 0;
        for ($d = $menor + 1; $d <= $mayor; $d++) {
            $cantidad += $this->habil($est, $d) ? 1 : 0;
        }

        return $a < $b ? $cantidad : -$cantidad;
    }

    public function esHabil(mixed $fecha, ?int $establecimientoId = null): bool
    {
        $d = $this->plausible($this->dia($fecha));

        return $this->habil($this->establecimiento($establecimientoId), $d);
    }

    /**
     * Fecha civil (AAAA-MM-DD) en la zona de la aplicación de un instante o de una fecha.
     */
    public function fechaLocal(mixed $valor): string
    {
        return $this->aTexto($this->dia($valor));
    }

    /**
     * Olvida el calendario memoizado (para trabajos largos o pruebas que cambian los datos).
     */
    public function olvidar(): void
    {
        $this->calendarios = [];
    }

    private function desplazar(mixed $fecha, int $n, ?int $establecimientoId): CarbonImmutable
    {
        $inicio = $this->plausible($this->dia($fecha));

        if (abs($n) > self::MAX_DIAS_HABILES) {
            throw new InvalidArgumentException('Implausible amount of business days: '.$n.' (maximum '.self::MAX_DIAS_HABILES.').');
        }

        $est = $this->establecimiento($establecimientoId);

        if ($n === 0) {
            return $this->aFecha($inicio);
        }

        $paso = $n > 0 ? 1 : -1;
        $alcance = (int) ceil(abs($n) * 7 / 5) + 10;
        $this->asegurar($est, min($inicio, $inicio + $paso * $alcance), max($inicio, $inicio + $paso * $alcance));

        $dia = $inicio;
        for ($restan = abs($n); $restan > 0;) {
            $dia += $paso;
            if ($this->habil($est, $dia)) {
                $restan--;
            }
        }

        return $this->aFecha($dia);
    }

    private function establecimiento(?int $explicito): int
    {
        if ($explicito !== null) {
            if ($explicito < 1) {
                throw new InvalidArgumentException('El id de establecimiento debe ser un entero positivo.');
            }

            return $explicito;
        }

        return $this->actual->id() ?? throw SinEstablecimientoActivo::para(DiaNoHabil::class);
    }

    private function habil(int $establecimientoId, int $dia): bool
    {
        // Nunca se evalúa un día fuera de la ventana cargada: si hace falta, se amplía antes.
        $this->asegurar($establecimientoId, $dia, $dia);

        // El día 0 (1970-01-01) fue jueves: 0 = lunes ... 5 = sábado, 6 = domingo.
        $semana = (($dia + 3) % 7 + 7) % 7;

        return $semana < 5 && ! isset($this->calendarios[$establecimientoId]['dias'][$dia]);
    }

    /**
     * Garantiza que el calendario memoizado cubra [$desde, $hasta]; si no, UNA consulta que
     * reemplaza la ventana por la unión de la anterior y lo pedido, con holgura.
     */
    private function asegurar(int $establecimientoId, int $desde, int $hasta): void
    {
        $actual = $this->calendarios[$establecimientoId] ?? null;

        if ($actual !== null && $desde >= $actual['desde'] && $hasta <= $actual['hasta']) {
            return;
        }

        if ($actual === null) {
            $this->calendarios[$establecimientoId] = [
                'desde' => $desde - self::HOLGURA,
                'hasta' => $hasta + self::HOLGURA,
                'dias' => $this->cargar($establecimientoId, $desde - self::HOLGURA, $hasta + self::HOLGURA),
            ];

            return;
        }

        // Solo se consulta el tramo que falta, a cada lado, y se une a lo ya memoizado.
        if ($desde < $actual['desde']) {
            $actual['dias'] += $this->cargar($establecimientoId, $desde - self::HOLGURA, $actual['desde'] - 1);
            $actual['desde'] = $desde - self::HOLGURA;
        }

        if ($hasta > $actual['hasta']) {
            $actual['dias'] += $this->cargar($establecimientoId, $actual['hasta'] + 1, $hasta + self::HOLGURA);
            $actual['hasta'] = $hasta + self::HOLGURA;
        }

        $this->calendarios[$establecimientoId] = $actual;
    }

    /**
     * UNA consulta: los días no hábiles (nacionales y del establecimiento) entre dos números de día.
     *
     * @return array<int, true>
     */
    private function cargar(int $establecimientoId, int $desde, int $hasta): array
    {
        $fechas = DiaNoHabil::paraEstablecimiento($establecimientoId)
            ->toBase()
            ->whereBetween('fecha', [$this->aTexto($desde), $this->aTexto($hasta)])
            ->pluck('fecha');

        $dias = [];
        foreach ($fechas as $fecha) {
            $dias[$this->dia((string) $fecha)] = true;
        }

        return $dias;
    }

    /**
     * Rechaza fechas fuera de ±ANIOS_PLAUSIBLES años de hoy (fecha local) antes de cualquier consulta.
     */
    private function plausible(int $dia): int
    {
        $hoy = CarbonImmutable::now((string) config('app.timezone'));
        $minimo = $this->dia($hoy->subYears(self::ANIOS_PLAUSIBLES)->format('Y-m-d'));
        $maximo = $this->dia($hoy->addYears(self::ANIOS_PLAUSIBLES)->format('Y-m-d'));

        if ($dia < $minimo || $dia > $maximo) {
            throw new InvalidArgumentException(
                'Implausible date '.$this->aTexto($dia).': business-day calculations accept dates within '
                .self::ANIOS_PLAUSIBLES.' years of today ('.$this->aTexto($minimo).' to '.$this->aTexto($maximo).').'
            );
        }

        return $dia;
    }

    /**
     * Número de día (días desde 1970-01-01, sin hora) de una fecha civil o de un instante.
     */
    private function dia(mixed $valor): int
    {
        if (is_string($valor) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $m) === 1) {
            if (! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                throw new InvalidArgumentException("Fecha inválida: {$valor}.");
            }

            return intdiv(gmmktime(0, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]), 86400);
        }

        $zona = (string) config('app.timezone');

        try {
            $instante = match (true) {
                $valor instanceof DateTimeInterface => CarbonImmutable::instance($valor),
                is_string($valor) && trim($valor) !== '' => CarbonImmutable::parse($valor, $zona),
                default => throw new InvalidArgumentException('Se esperaba una fecha o un instante.'),
            };
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new InvalidArgumentException('Fecha inválida: '.(is_string($valor) ? $valor : get_debug_type($valor)).'.', 0, $e);
        }

        $local = $instante->setTimezone($zona);

        return intdiv(gmmktime(0, 0, 0, $local->month, $local->day, $local->year), 86400);
    }

    private function aTexto(int $dia): string
    {
        return gmdate('Y-m-d', $dia * 86400);
    }

    private function aFecha(int $dia): CarbonImmutable
    {
        return CarbonImmutable::parse($this->aTexto($dia).' 12:00:00', (string) config('app.timezone'));
    }
}
