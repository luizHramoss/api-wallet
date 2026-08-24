<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecurringBillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'type' => $this->type,
            'amount' => (float) $this->amount,
            'day_of_month' => $this->day_of_month,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'status' => $this->status,
            'last_generated_at' => $this->last_generated_at?->toDateString(),
        ];
    }
}
