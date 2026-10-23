<?php

namespace App\Models;

use Database\Factories\RubricSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Section (ámbito) of a catalog rubric.
 */
#[Fillable(['rubric_template_id', 'name', 'position'])]
class RubricSection extends Model
{
    /** @use HasFactory<RubricSectionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<RubricTemplate, $this>
     */
    public function rubricTemplate(): BelongsTo
    {
        return $this->belongsTo(RubricTemplate::class);
    }

    /**
     * @return HasMany<Indicator, $this>
     */
    public function indicators(): HasMany
    {
        return $this->hasMany(Indicator::class)->orderBy('number');
    }
}
