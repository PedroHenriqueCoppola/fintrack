<?php

namespace App\Services\Reports;

use RuntimeException;

class ReportLimitException extends RuntimeException
{
    public const PDF = 'Muitas linhas para o PDF. Reduza o período ou filtre mais.';

    public const XLSX = 'Muitas linhas para o Excel. Reduza o período ou filtre mais.';

    public function __construct(public readonly string $format)
    {
        parent::__construct($format === 'pdf' ? self::PDF : self::XLSX);
    }
}
