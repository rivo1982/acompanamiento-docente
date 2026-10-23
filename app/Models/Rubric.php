<?php

namespace App\Models;

use Database\Factories\RubricFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * School-owned rubric (pauta del establecimiento) built from catalog indicators.
 */
#[Fillable(['school_id', 'name'])]
class Rubric extends Model
{
    /** @use HasFactory<RubricFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsToMany<Indicator, $this>
     */
    public function indicators(): BelongsToMany
    {
        return $this->belongsToMany(Indicator::class)
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }

    /**
     * @return HasMany<Observation, $this>
     */
    public function observations(): HasMany
    {
        return $this->hasMany(Observation::class);
    }

    /**
     * Frozen copy of the rubric content, stored on an observation when it starts.
     *
     * @return array{id: int, name: string, indicators: array<int, array{id: int, number: int, name: string, levels: array<string, string>, feedback_meaning: string, feedback_to_achieved: string, feedback_to_outstanding: ?string, sources: string}>}
     */
    public function toSnapshot(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'indicators' => $this->indicators()->get()->map(fn (Indicator $indicator): array => [
                'id' => $indicator->id,
                'number' => $indicator->number,
                'name' => $indicator->name,
                'levels' => [
                    'en_inicio' => $indicator->level_beginning,
                    'en_desarrollo' => $indicator->level_developing,
                    'logrado' => $indicator->level_achieved,
                    'destacado' => $indicator->level_outstanding,
                ],
                'feedback_meaning' => $indicator->feedback_meaning,
                'feedback_to_achieved' => $indicator->feedback_to_achieved,
                'feedback_to_outstanding' => $indicator->feedback_to_outstanding,
                'sources' => $indicator->sources,
            ])->values()->all(),
        ];
    }
}
