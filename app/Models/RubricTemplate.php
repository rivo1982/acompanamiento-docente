<?php

namespace App\Models;

use Database\Factories\RubricTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Platform catalog rubric (pauta base).
 */
#[Fillable(['number', 'name', 'evidence', 'standards', 'protocol', 'usage_warning'])]
class RubricTemplate extends Model
{
    /** @use HasFactory<RubricTemplateFactory> */
    use HasFactory;

    /**
     * @return HasMany<RubricSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(RubricSection::class)->orderBy('position');
    }

    /**
     * @return HasManyThrough<Indicator, RubricSection, $this>
     */
    public function indicators(): HasManyThrough
    {
        return $this->hasManyThrough(Indicator::class, RubricSection::class);
    }
}
