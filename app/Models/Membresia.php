<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une una credencial global con una persona de un establecimiento (ADR-0005).
 * Es de alcance global por naturaleza: no lleva filtro por establecimiento.
 *
 * @host-owned Copied from Vinkured app/Models/Membresia.php — do not modify here; replace with the host class at integration.
 */
class Membresia extends Model
{
    public const ESTADO_ACTIVA = 'activa';

    protected $table = 'membresias';

    /**
     * Membresías que dan acceso a la aplicación en el hito 1: activas y ligadas
     * a un establecimiento. Las invitadas, las suspendidas y las de soporte
     * (sin establecimiento) no dan acceso.
     *
     * @param  Builder<Membresia>  $consulta
     */
    public function scopeConAcceso(Builder $consulta): void
    {
        $consulta->where('estado', self::ESTADO_ACTIVA)->whereNotNull('establecimiento_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    /**
     * Persona del establecimiento ligada a la membresía. `Persona` sí lleva el filtro por
     * establecimiento: solo se resuelve la del establecimiento activo.
     *
     * @return BelongsTo<Persona, $this>
     */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /**
     * @return BelongsTo<Establecimiento, $this>
     */
    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'es_administrador' => 'boolean',
            'invitada_en' => 'datetime',
            'activada_en' => 'datetime',
            'invitacion_expira_en' => 'datetime',
        ];
    }
}
