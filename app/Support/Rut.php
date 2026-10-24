<?php

namespace App\Support;

use App\Exceptions\RutInvalido;

/**
 * RUT chileno validado con su dígito verificador (módulo 11). Se guarda como Vinkured guarda
 * personas.numero_documento: cuerpo sin puntos, guion y dígito verificador en mayúscula ("12345678-5").
 */
final class Rut
{
    /** Máximo de dígitos del cuerpo, sin ceros a la izquierda. */
    private const MAXIMO_DE_DIGITOS = 8;

    private function __construct(
        private readonly int $cuerpo,
        private readonly string $dv,
    ) {}

    /**
     * Lee el RUT escrito con o sin puntos, guion o espacios, y con la K en minúscula o mayúscula.
     *
     * @throws RutInvalido
     */
    public static function desde(string $texto): self
    {
        $limpio = strtoupper((string) preg_replace('/[\s\p{Z}.]+/u', '', $texto));

        if (str_replace('-', '', $limpio) === '') {
            throw RutInvalido::vacio();
        }

        if (preg_match('/^(\d+)-?([0-9K])$/', $limpio, $partes) !== 1) {
            throw RutInvalido::formatoInvalido();
        }

        $digitos = ltrim($partes[1], '0');

        if ($digitos === '' || strlen($digitos) > self::MAXIMO_DE_DIGITOS) {
            throw RutInvalido::formatoInvalido();
        }

        $cuerpo = (int) $digitos;

        if (self::calcularDv($cuerpo) !== $partes[2]) {
            throw RutInvalido::digitoVerificadorIncorrecto();
        }

        return new self($cuerpo, $partes[2]);
    }

    public static function esValido(string $texto): bool
    {
        try {
            self::desde($texto);

            return true;
        } catch (RutInvalido) {
            return false;
        }
    }

    public function cuerpo(): int
    {
        return $this->cuerpo;
    }

    public function dv(): string
    {
        return $this->dv;
    }

    /** Forma en que Vinkured guarda el RUT: "12345678-5". */
    public function paraAlmacenar(): string
    {
        return $this->cuerpo.'-'.$this->dv;
    }

    /** Forma para mostrar: "12.345.678-5". */
    public function formateado(): string
    {
        return number_format($this->cuerpo, 0, ',', '.').'-'.$this->dv;
    }

    private static function calcularDv(int $cuerpo): string
    {
        $suma = 0;
        $factor = 2;

        foreach (array_reverse(str_split((string) $cuerpo)) as $digito) {
            $suma += (int) $digito * $factor;
            $factor = $factor === 7 ? 2 : $factor + 1;
        }

        $resto = 11 - $suma % 11;

        return match ($resto) {
            11 => '0',
            10 => 'K',
            default => (string) $resto,
        };
    }
}
