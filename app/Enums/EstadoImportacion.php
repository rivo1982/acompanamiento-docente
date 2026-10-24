<?php

namespace App\Enums;

/**
 * Ciclo de vida de una carga masiva (`importaciones_usuarios.estado`, Q18). Vinkured dejó la columna como texto
 * libre: estos valores son una propuesta del módulo, con su CHECK, para el paquete de entrega (tarea C5).
 *
 * pendiente -> procesando -> completada | fallida. Las dos últimas son terminales.
 */
enum EstadoImportacion: string
{
    case Pendiente = 'pendiente';
    case Procesando = 'procesando';
    case Completada = 'completada';
    case Fallida = 'fallida';
}
