<?php

namespace App\Models;

use App\Enums\TeachingTipTarget;
use Database\Factories\TeachingTipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pedagogical support tip (apoyo pedagógico) attached to an indicator.
 */
#[Fillable(['indicator_id', 'position', 'tool_name', 'target', 'how_to', 'example', 'duration', 'success_signal'])]
class TeachingTip extends Model
{
    /** @use HasFactory<TeachingTipFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target' => TeachingTipTarget::class,
        ];
    }

    /**
     * @return BelongsTo<Indicator, $this>
     */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class);
    }
}
