<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvestmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'name' => $this->name,
            'symbol' => $this->symbol,
            'type' => $this->type,
            'quantity' => (float) $this->quantity,
            'average_price' => (float) $this->average_price,
            'current_price' => $this->current_price !== null ? (float) $this->current_price : null,
            'invested_value' => $this->investedValue(),
            'current_value' => $this->currentValue(),
            'rentability_percent' => $this->rentabilityPercent(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
