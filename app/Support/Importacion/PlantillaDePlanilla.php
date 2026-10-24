<?php

namespace App\Support\Importacion;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Plantilla de la carga masiva (Q18): un .xlsx con SOLO la fila de encabezado de ValidadorDePlanilla, generado al vuelo.
 * Sin fila de ejemplo: una fila olvidada en la planilla se procesaría como una persona real y le llegaría una
 * invitación. Las columnas y los valores aceptados se explican en la pantalla "Carga masiva".
 */
class PlantillaDePlanilla
{
    public const NOMBRE = 'plantilla-carga-masiva.xlsx';

    public const TIPO = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function descargar(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(ValidadorDePlanilla::ENCABEZADO));
            $writer->close();
        }, self::NOMBRE, ['Content-Type' => self::TIPO, 'Cache-Control' => 'no-store']);
    }
}
