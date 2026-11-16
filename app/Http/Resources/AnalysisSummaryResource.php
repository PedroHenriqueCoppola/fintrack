<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnalysisSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'count' => (int) $this->resource['count'],
            'income' => $this->resource['income'],
            'expense' => $this->resource['expense'],
            'balance' => $this->resource['balance'],
            'average_expense' => $this->resource['average_expense'],
        ];
    }
}
