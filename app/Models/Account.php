<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    public const TYPE_CHECKING = 'checking';

    public const TYPE_SAVINGS = 'savings';

    public const TYPE_CASH = 'cash';

    public const TYPE_INVESTMENT = 'investment';

    public const TYPES = [
        self::TYPE_CHECKING,
        self::TYPE_SAVINGS,
        self::TYPE_CASH,
        self::TYPE_INVESTMENT,
    ];

    protected $fillable = ['user_id', 'name', 'type', 'balance', 'color', 'is_archived'];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_archived' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function hasSufficientBalance(float $amount): bool
    {
        return $this->balance >= $amount;
    }
}
