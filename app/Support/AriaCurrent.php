<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Valor de `aria-current` de un ítem de la navegación: `page` (la página actual), `true` (la sección actual
 * desde una página interna, p. ej. un detalle) o null (no es el actual). Cualquier otro valor lanza, para que un
 * llamador futuro no reciba en silencio un token equivocado.
 *
 * @host-owned Copied from Vinkured app/Support/AriaCurrent.php — do not modify here; replace with the host class at integration.
 */
final class AriaCurrent
{
    public const VALORES = ['page', 'true'];

    public static function de(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        if (is_string($valor) && in_array($valor, self::VALORES, true)) {
            return $valor;
        }

        throw new InvalidArgumentException('ariaCurrent de la navegación debe ser "page", "true" o null.');
    }
}
