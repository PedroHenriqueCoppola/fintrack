<?php

namespace App\Support;

class AnalysisFilters
{
    public const PERIODS = [
        'current_month',
        'previous_month',
        'quarter',
        'semester',
        'year',
        'all',
        'custom',
    ];

    public const SORTS = [
        'date_desc',
        'date_asc',
        'amount_desc',
        'amount_asc',
    ];

    public const KEYS = [
        'type',
        'status',
        'category_id',
        'bank_id',
        'card_id',
        'payment_method_id',
        'min_amount',
        'max_amount',
        'q',
        'sort',
    ];

    public const PERIOD_KEYS = [
        'period',
        'from',
        'to',
    ];
}
