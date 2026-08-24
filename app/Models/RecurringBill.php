<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringBill extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_PAUSED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'user_id',
        'account_id',
        'category_id',
        'name',
        'type',
        'amount',
        'day_of_month',
        'start_date',
        'end_date',
        'status',
        'last_generated_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'day_of_month' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'last_generated_at' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
