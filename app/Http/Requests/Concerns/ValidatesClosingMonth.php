<?php

namespace App\Http\Requests\Concerns;

use App\Support\Month;

trait ValidatesClosingMonth
{
    protected function closingMonthError(mixed $month): ?string
    {
        if (! is_string($month) || Month::startOrNull($month) === null) {
            return 'Informe um mês válido.';
        }

        if ($month > Month::currentKey()) {
            return 'Não é possível fechar um mês futuro.';
        }

        return null;
    }
}
