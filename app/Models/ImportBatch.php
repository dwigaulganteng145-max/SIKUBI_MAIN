<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImportBatch extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'bank_account_id', 'uploaded_by', 'file_name', 'bank_format',
        'total_rows', 'success_rows', 'failed_rows', 'duplicate_rows',
        'status', 'imported_at',
    ];

    protected function casts(): array
    {
        return ['imported_at' => 'datetime'];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
