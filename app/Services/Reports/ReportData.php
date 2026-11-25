<?php

namespace App\Services\Reports;

class ReportData
{
    /**
     * @param  array<int, array{label: string, value: string}>  $filters
     * @param  array<int, array<string, mixed>>  $sections
     */
    public function __construct(
        public readonly string $title,
        public readonly string $periodLabel,
        public readonly string $periodDetail,
        public readonly array $filters,
        public readonly string $generatedAt,
        public readonly array $sections,
        public readonly int $rowCount,
        public readonly bool $truncated,
        public readonly bool $empty,
        public readonly bool $landscape,
        public readonly ?string $note,
        public readonly int $shownCount,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'period_label' => $this->periodLabel,
            'period_detail' => $this->periodDetail,
            'filters' => $this->filters,
            'generated_at' => $this->generatedAt,
            'sections' => $this->sections,
            'row_count' => $this->rowCount,
            'truncated' => $this->truncated,
            'empty' => $this->empty,
            'landscape' => $this->landscape,
            'note' => $this->note,
            'shown_count' => $this->shownCount,
        ];
    }
}
