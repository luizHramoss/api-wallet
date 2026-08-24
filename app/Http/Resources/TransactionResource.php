<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'category_id' => $this->category_id,
            'recurring_bill_id' => $this->recurring_bill_id,
            'transfer_pair_id' => $this->transfer_pair_id,
            'transfer_direction' => $this->transfer_direction,
            'type' => $this->type,
            'status' => $this->status,
            'amount' => (float) $this->amount,
            'description' => $this->description,
            'occurred_at' => $this->occurred_at->toDateString(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
