<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentMovement extends Model
{
    use HasFactory;

    public const TYPE_BUY = 'buy';

    public const TYPE_SELL = 'sell';

    public const TYPE_DIVIDEND = 'dividend';

    public const TYPES = [
        self::TYPE_BUY,
        self::TYPE_SELL,
        self::TYPE_DIVIDEND,
    ];

    protected $fillable = ['investment_id', 'type', 'quantity', 'price', 'amount', 'occurred_at'];

    protected $casts = [
        'quantity' => 'decimal:8',
        'price' => 'decimal:4',
        'amount' => 'decimal:2',
        'occurred_at' => 'date',
    ];

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }
}
