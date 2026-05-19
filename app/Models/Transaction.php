<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'import_batch_id', 'bank_account_id', 'transaction_date', 'description',
        'amount', 'type', 'category_id', 'classification_method',
        'confidence_score', 'raw_data', 'deduplication_hash',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'datetime',
            'amount' => 'decimal:2',
            'confidence_score' => 'decimal:2',
            'raw_data' => 'array',
        ];
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function anomalyFlags(): HasMany
    {
        return $this->hasMany(AnomalyFlag::class);
    }

    public function scopeDebit($query)
    {
        return $query->where('type', 'DEBIT');
    }

    public function scopeCredit($query)
    {
        return $query->where('type', 'CREDIT');
    }

    public function scopeForAccount($query, $accountId)
    {
        if ($accountId) {
            return $query->where('bank_account_id', $accountId);
        }
        return $query;
    }
}
