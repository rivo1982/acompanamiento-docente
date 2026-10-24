<?php

namespace App\Support\Importacion;

use App\Exceptions\PlanillaIlegible;
use OpenSpout\Common\Exception\OpenSpoutException;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Lector de planillas .xlsx con OpenSpout (ADR-0007, propuesta): recorre la primera hoja en streaming, de modo que una
 * planilla grande no se carga completa en memoria.
 */
final class LectorXlsx implements LectorDePlanilla
{
    /** Firma de un archivo ZIP, que es lo que es un .xlsx por dentro. */
    private const FIRMA_ZIP = "PK\x03\x04";

    /** Firma de un archivo OLE: un .xlsx protegido con contraseña o un .xls antiguo. */
    private const FIRMA_OLE = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";

    public function filas(string $ruta): iterable
    {
        $this->asegurarQueEsXlsx($ruta);

        return $this->recorrer($ruta);
    }

    /**
     * @throws PlanillaIlegible
     */
    private function asegurarQueEsXlsx(string $ruta): void
    {
        if (strtolower(pathinfo($ruta, PATHINFO_EXTENSION)) !== 'xlsx') {
            throw PlanillaIlegible::noEsXlsx();
        }

        if (! is_file($ruta) || ! is_readable($ruta)) {
            throw PlanillaIlegible::noSePuedeLeer();
        }

        $inicio = (string) file_get_contents($ruta, length: 8);

        if ($inicio === self::FIRMA_OLE) {
            throw PlanillaIlegible::protegidaOAntigua();
        }

        if (! str_starts_with($inicio, self::FIRMA_ZIP)) {
            throw PlanillaIlegible::noSePuedeLeer();
        }
    }

    /**
     * @return \Generator<int, array<int, string|int|float|bool|\DateTimeInterface|\DateInterval|null>>
     */
    private function recorrer(string $ruta): \Generator
    {
        $lector = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));

        try {
            $lector->open($ruta);

            foreach ($lector->getSheetIterator() as $hoja) {
                $numeroDeFila = 0;

                foreach ($hoja->getRowIterator() as $fila) {
                    yield ++$numeroDeFila => $fila->toArray();
                }

                break;
            }
        } catch (OpenSpoutException $e) {
            throw PlanillaIlegible::noSePuedeLeer($e);
        } finally {
            $lector->close();
        }
    }
}
