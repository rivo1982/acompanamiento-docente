<?php

namespace App\Models;

use App\Enums\EstadoAcompanamiento;
use App\Exceptions\AcompanamientoYaFinalizado;
use App\Models\Concerns\PerteneceAEstablecimiento;
use Database\Factories\AcompanamientoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Acompañamiento de aula: un observador observa a un docente en un curso y asignatura con una
 * pauta del establecimiento, cuyo contenido queda congelado en `pauta_snapshot`.
 */
class Acompanamiento extends Model
{
    /** @use HasFactory<AcompanamientoFactory> */
    use HasFactory, PerteneceAEstablecimiento;

    protected $table = 'acompanamientos';

    /** @var list<string> */
    protected $fillable = [
        'pauta_id', 'observador_id', 'docente_id', 'curso_id', 'asignatura_id',
        'iniciado_en', 'finalizado_en', 'duracion_minutos', 'estado', 'pauta_snapshot',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'estado' => 'en_curso',
    ];

    /**
     * Congela el contenido de la pauta y la hora de inicio al crear, solo si no vienen dados.
     */
    protected static function booted(): void
    {
        static::creating(function (Acompanamiento $acompanamiento): void {
            $acompanamiento->iniciado_en ??= now();

            if ($acompanamiento->pauta_snapshot === null) {
                $acompanamiento->pauta_snapshot = $acompanamiento->pauta->toSnapshot();
            }
        });
    }

    /**
     * Cierra el acompañamiento con su hora de término y su duración en minutos completos.
     *
     * La transición es un UPDATE condicionado al estado guardado, así una copia desactualizada
     * en memoria nunca finaliza dos veces.
     *
     * @throws AcompanamientoYaFinalizado
     */
    public function finalizar(): void
    {
        $finalizadoEn = now();

        $afectadas = static::query()
            ->whereKey($this->getKey())
            ->where('estado', EstadoAcompanamiento::EnCurso->value)
            ->update([
                'finalizado_en' => $finalizadoEn,
                'duracion_minutos' => (int) floor($this->iniciado_en->diffInMinutes($finalizadoEn, true)),
                'estado' => EstadoAcompanamiento::Finalizado->value,
            ]);

        if ($afectadas === 0) {
            throw AcompanamientoYaFinalizado::para($this);
        }

        $this->refresh();
    }

    /**
     * Entrada del snapshot para el indicador dado, o null si no era parte de la pauta.
     *
     * @return array<string, mixed>|null
     */
    public function indicadorDelSnapshot(int $indicadorId): ?array
    {
        return collect($this->pauta_snapshot['indicadores'] ?? [])->firstWhere('id', $indicadorId);
    }

    /**
     * Nombre del nivel de enseñanza, derivado de curso → grado → niveles_ensenanza (no se guarda).
     */
    public function nivelEnsenanza(): ?string
    {
        return DB::table('cursos')
            ->join('grados', 'grados.id', '=', 'cursos.grado_id')
            ->join('niveles_ensenanza', 'niveles_ensenanza.id', '=', 'grados.nivel_ensenanza_id')
            ->where('cursos.establecimiento_id', $this->establecimiento_id)
            ->where('cursos.id', $this->curso_id)
            ->value('niveles_ensenanza.nombre');
    }

    /**
     * @return BelongsTo<Pauta, $this>
     */
    public function pauta(): BelongsTo
    {
        return $this->belongsTo(Pauta::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Funcionario, $this>
     */
    public function observador(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class, 'observador_id');
    }

    /**
     * @return BelongsTo<Funcionario, $this>
     */
    public function docente(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class, 'docente_id');
    }

    /**
     * @return BelongsTo<Curso, $this>
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /**
     * @return HasMany<RespuestaAcompanamiento, $this>
     */
    public function respuestas(): HasMany
    {
        return $this->hasMany(RespuestaAcompanamiento::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'iniciado_en' => 'datetime',
            'finalizado_en' => 'datetime',
            'duracion_minutos' => 'integer',
            'estado' => EstadoAcompanamiento::class,
            'pauta_snapshot' => 'array',
        ];
    }
}
