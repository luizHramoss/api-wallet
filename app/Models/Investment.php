<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Investment extends Model
{
    use HasFactory;

    public const TYPE_STOCK = 'stock';

    public const TYPE_FIXED_INCOME = 'fixed_income';

    public const TYPE_FUND = 'fund';

    public const TYPE_CRYPTO = 'crypto';

    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_STOCK,
        self::TYPE_FIXED_INCOME,
        self::TYPE_FUND,
        self::TYPE_CRYPTO,
        self::TYPE_OTHER,
    ];

    protected $fillable = [
        'user_id', 'account_id', 'name', 'symbol', 'type',
        'quantity', 'average_price', 'current_price',
    ];

    protected $casts = [
        'quantity' => 'decimal:8',
        'average_price' => 'decimal:4',
        'current_price' => 'decimal:4',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InvestmentMovement::class);
    }

    public function investedValue(): float
    {
        return round((float) $this->quantity * (float) $this->average_price, 2);
    }

    /** Usa average_price como fallback enquanto current_price nunca foi informado. */
    public function currentValue(): float
    {
        $price = $this->current_price !== null ? (float) $this->current_price : (float) $this->average_price;

        return round((float) $this->quantity * $price, 2);
    }

    public function rentabilityPercent(): ?float
    {
        $invested = $this->investedValue();

        if ($invested <= 0) {
            return null;
        }

        return round((($this->currentValue() - $invested) / $invested) * 100, 2);
    }
}
