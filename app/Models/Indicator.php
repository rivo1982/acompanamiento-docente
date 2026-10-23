<?php

namespace App\Models;

use App\Enums\AchievementLevel;
use Database\Factories\IndicatorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Numbered catalog indicator with its four level descriptors and feedback texts.
 */
#[Fillable([
    'rubric_section_id', 'number', 'name',
    'level_beginning', 'level_developing', 'level_achieved', 'level_outstanding',
    'feedback_meaning', 'feedback_to_achieved', 'feedback_to_outstanding', 'sources',
])]
class Indicator extends Model
{
    /** @use HasFactory<IndicatorFactory> */
    use HasFactory;

    /**
     * Descriptor of this indicator for the given level.
     */
    public function levelDescription(AchievementLevel $level): string
    {
        return match ($level) {
            AchievementLevel::Beginning => $this->level_beginning,
            AchievementLevel::Developing => $this->level_developing,
            AchievementLevel::Achieved => $this->level_achieved,
            AchievementLevel::Outstanding => $this->level_outstanding,
        };
    }

    /**
     * @return BelongsTo<RubricSection, $this>
     */
    public function rubricSection(): BelongsTo
    {
        return $this->belongsTo(RubricSection::class);
    }

    /**
     * @return HasMany<TeachingTip, $this>
     */
    public function teachingTips(): HasMany
    {
        return $this->hasMany(TeachingTip::class)->orderBy('position');
    }

    /**
     * @return BelongsToMany<Rubric, $this>
     */
    public function rubrics(): BelongsToMany
    {
        return $this->belongsToMany(Rubric::class)->withPivot('position')->withTimestamps();
    }
}
