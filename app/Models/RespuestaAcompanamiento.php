<?php

namespace App\Models;

use App\Enums\NivelLogro;
use App\Exceptions\AcompanamientoYaFinalizado;
use App\Exceptions\EntradaDeSnapshotIncompleta;
use App\Exceptions\IndicadorFueraDelSnapshot;
use App\Models\Concerns\PerteneceAEstablecimiento;
use Database\Factories\RespuestaAcompanamientoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nivel asignado a un indicador dentro de un acompañamiento.
 */
class RespuestaAcompanamiento extends Model
{
    /** @use HasFactory<RespuestaAcompanamientoFactory> */
    use HasFactory, PerteneceAEstablecimiento;

    /**
     * Claves del snapshot sin las cuales no hay retroalimentación automática.
     *
     * @var list<string>
     */
    private const CLAVES_REQUERIDAS = ['retro_que_significa', 'retro_para_logrado', 'fuentes'];

    protected $table = 'respuestas_acompanamiento';

    /** @var list<string> */
    protected $fillable = ['acompanamiento_id', 'indicador_id', 'nivel', 'retroalimentacion_personal'];

    /**
     * Guarda de escritura: una respuesta solo se crea, modifica o elimina mientras su
     * acompañamiento sigue en curso, y solo para indicadores congelados en su snapshot.
     */
    protected static function booted(): void
    {
        static::saving(function (RespuestaAcompanamiento $respuesta): void {
            if ($respuesta->exists && $respuesta->isDirty('acompanamiento_id')) {
                self::acompanamientoEnCurso($respuesta->getOriginal('acompanamiento_id'));
            }

            $acompanamiento = self::acompanamientoEnCurso($respuesta->acompanamiento_id);

            if ($acompanamiento->indicadorDelSnapshot((int) $respuesta->indicador_id) === null) {
                throw IndicadorFueraDelSnapshot::para($acompanamiento, (int) $respuesta->indicador_id);
            }
        });

        static::deleting(function (RespuestaAcompanamiento $respuesta): void {
            self::acompanamientoEnCurso($respuesta->getOriginal('acompanamiento_id'));
        });
    }

    /**
     * Lee el acompañamiento desde la base (nunca una copia en memoria, que puede estar
     * desactualizada) y exige que siga en curso.
     *
     * @throws AcompanamientoYaFinalizado
     */
    private static function acompanamientoEnCurso(mixed $acompanamientoId): Acompanamiento
    {
        $acompanamiento = Acompanamiento::query()->findOrFail($acompanamientoId);

        if ($acompanamiento->estaFinalizado()) {
            throw AcompanamientoYaFinalizado::paraRespuestas($acompanamiento);
        }

        return $acompanamiento;
    }

    /**
     * Retroalimentación automática leída SOLO del snapshot congelado del acompañamiento.
     *
     * @return array{que_significa: string, para_logrado: string, para_destacado: ?string, fuentes: string, felicitaciones: ?string}
     *
     * @throws IndicadorFueraDelSnapshot
     * @throws EntradaDeSnapshotIncompleta
     */
    public function retroalimentacionAutomatica(): array
    {
        $indicador = $this->acompanamiento->indicadorDelSnapshot($this->indicador_id)
            ?? throw IndicadorFueraDelSnapshot::para($this->acompanamiento, $this->indicador_id);

        $faltantes = array_values(array_diff(self::CLAVES_REQUERIDAS, array_keys($indicador)));

        if ($faltantes !== []) {
            throw EntradaDeSnapshotIncompleta::para($this->acompanamiento, $this->indicador_id, $faltantes);
        }

        return [
            'que_significa' => $indicador['retro_que_significa'],
            'para_logrado' => $indicador['retro_para_logrado'],
            'para_destacado' => $indicador['retro_para_destacado'] ?? null,
            'fuentes' => $indicador['fuentes'],
            'felicitaciones' => $this->nivel->mensajeFelicitacion(),
        ];
    }

    /**
     * @return BelongsTo<Acompanamiento, $this>
     */
    public function acompanamiento(): BelongsTo
    {
        return $this->belongsTo(Acompanamiento::class);
    }

    /**
     * @return BelongsTo<Indicador, $this>
     */
    public function indicador(): BelongsTo
    {
        return $this->belongsTo(Indicador::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['nivel' => NivelLogro::class];
    }
}
