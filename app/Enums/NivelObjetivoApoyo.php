<?php

namespace App\Enums;

/**
 * Nivel que un apoyo pedagógico ayuda a alcanzar (`apoyos_pedagogicos.nivel_objetivo`). El CHECK
 * de la base repite los valores.
 */
enum NivelObjetivoApoyo: string
{
    case Logrado = 'logrado';
    case Destacado = 'destacado';
}
