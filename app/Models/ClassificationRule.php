<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassificationRule extends Model
{
    protected $fillable = ['category_id', 'pattern', 'match_type', 'priority', 'hit_count', 'bank_account_id'];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function incrementHit(): void
    {
        $this->increment('hit_count');
    }
}
