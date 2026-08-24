<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    public const TYPE_INCOME = 'income';

    public const TYPE_EXPENSE = 'expense';

    public const TYPE_TRANSFER = 'transfer';

    public const TYPES = [
        self::TYPE_INCOME,
        self::TYPE_EXPENSE,
        self::TYPE_TRANSFER,
    ];

    public const STATUS_PLANNED = 'planned';

    public const STATUS_REALIZED = 'realized';

    public const STATUSES = [
        self::STATUS_PLANNED,
        self::STATUS_REALIZED,
    ];

    protected $fillable = [
        'account_id',
        'category_id',
        'type',
        'status',
        'amount',
        'description',
        'occurred_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'occurred_at' => 'date',
        'created_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function isIncome(): bool
    {
        return $this->type === self::TYPE_INCOME;
    }

    public function isExpense(): bool
    {
        return $this->type === self::TYPE_EXPENSE;
    }

    public function isTransfer(): bool
    {
        return $this->type === self::TYPE_TRANSFER;
    }

    public function isPlanned(): bool
    {
        return $this->status === self::STATUS_PLANNED;
    }

    public function isRealized(): bool
    {
        return $this->status === self::STATUS_REALIZED;
    }
}
