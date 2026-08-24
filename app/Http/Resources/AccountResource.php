<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'balance' => (float) $this->balance,
            'color' => $this->color,
            'is_archived' => $this->is_archived,
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
