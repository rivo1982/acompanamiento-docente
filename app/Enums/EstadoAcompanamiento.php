<?php

namespace App\Enums;

/**
 * Ciclo de vida de un acompañamiento (`acompanamientos.estado`). El CHECK de la base repite
 * los valores.
 */
enum EstadoAcompanamiento: string
{
    case EnCurso = 'en_curso';
    case Finalizado = 'finalizado';
}
