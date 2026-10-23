<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Se intentó leer o crear un modelo de un establecimiento sin que haya uno activo.
 * El filtro por establecimiento falla cerrado: nunca devuelve "todo" ni "nada" en silencio.
 *
 * @host-owned Copied from Vinkured app/Exceptions/SinEstablecimientoActivo.php — do not modify here; replace with the host class at integration.
 */
final class SinEstablecimientoActivo extends RuntimeException
{
    public static function para(string $modelo): self
    {
        return new self("No hay un establecimiento activo: no se puede consultar ni crear {$modelo}. Use {$modelo}::sinEstablecimiento() solo en seeders, comandos de consola o pruebas.");
    }
}
