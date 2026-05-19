<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnomalyFlag extends Model
{
    protected $fillable = [
        'transaction_id', 'detection_method', 'score', 'severity',
        'reason', 'is_reviewed', 'is_dismissed', 'review_note', 'detected_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:4',
            'is_reviewed' => 'boolean',
            'is_dismissed' => 'boolean',
            'detected_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
