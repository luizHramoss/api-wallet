<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvestmentMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'investment_id' => $this->investment_id,
            'type' => $this->type,
            'quantity' => $this->quantity !== null ? (float) $this->quantity : null,
            'price' => $this->price !== null ? (float) $this->price : null,
            'amount' => (float) $this->amount,
            'occurred_at' => $this->occurred_at->toDateString(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
