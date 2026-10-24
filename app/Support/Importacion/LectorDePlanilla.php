<?php

namespace App\Support\Importacion;

use App\Exceptions\PlanillaIlegible;

/**
 * Lee la primera hoja de una planilla subida, fila por fila, sin cargar el archivo completo en memoria.
 */
interface LectorDePlanilla
{
    /**
     * Filas de la primera hoja, con la clave igual al número de fila que se ve en Excel (la primera es 1). Las filas
     * vacías también se entregan, como arreglos vacíos o de celdas vacías, para no correr la numeración.
     *
     * @return iterable<int, array<int, string|int|float|bool|\DateTimeInterface|\DateInterval|null>>
     *
     * @throws PlanillaIlegible
     */
    public function filas(string $ruta): iterable;
}
