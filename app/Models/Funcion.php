<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo GLOBAL de funciones (director, coordinador de convivencia, docente de aula...).
 * Sin filtro por establecimiento. `clave` es el identificador estable del que se derivan
 * los permisos; el nombre es texto libre y nunca se usa para decidir un permiso.
 *
 * @host-owned Copied from Vinkured app/Models/Funcion.php — do not modify here; replace with the host class at integration.
 */
class Funcion extends Model
{
    public const CLAVE_COORDINADOR_CONVIVENCIA = 'coordinador_convivencia';

    public const CATEGORIA_DIRECTIVO = 'directivo';

    protected $table = 'funciones';

    /**
     * `clave` y `categoria` deciden permisos: no son asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = ['nombre'];
}
