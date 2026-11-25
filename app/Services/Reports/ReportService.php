<?php

namespace App\Services\Reports;

use App\Models\Bank;
use App\Models\Card;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Tag;
use App\Models\Transaction;
use App\Services\CardInvoiceService;
use App\Services\GoalService;
use App\Services\NetWorthService;
use App\Services\PeriodRange;
use App\Services\TransactionService;
use App\Support\Money;
use App\Support\Month;

class ReportService
{
    public const PREVIEW_ROWS = 50;

    public const PDF_MAX = 2000;

    public const XLSX_MAX = 20000;

    public const MONTH_CAP = 60;

    /** @var array{from: ?string, to: ?string}|null */
    private ?array $span = null;

    public function __construct(
        private PeriodRange $periods,
        private TransactionService $transactions,
        private CardInvoiceService $invoices,
        private NetWorthService $netWorth,
        private GoalService $goals,
        private ReportFormatter $formatter,
    ) {}

    /**
     * @return array{preview: int, pdf: int, xlsx: int, pdf_message: string, xlsx_message: string}
     */
    public function limits(): array
    {
        return [
            'preview' => self::PREVIEW_ROWS,
            'pdf' => self::PDF_MAX,
            'xlsx' => self::XLSX_MAX,
            'pdf_message' => ReportLimitException::PDF,
            'xlsx_message' => ReportLimitException::XLSX,
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public function build(array $definition, string $audience): ReportData
    {
        $range = $this->periods->resolve(
            $definition['period'],
            $definition['from'],
            $definition['to'],
        );
        $built = $definition['mode'] === 'builder'
            ? $this->builder($definition, $range, $audience)
            : $this->preset($definition, $range, $audience);

        return $this->finish($built, $definition, $range, $audience);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array{from: ?string, to: ?string, label: string}  $range
     * @return array<string, mixed>
     */
    private function preset(array $definition, array $range, string $audience): array
    {
        return match ($definition['preset']) {
            'summary' => $this->periodSummary($definition, $range),
            'statement' => $this->statement($this->forced($definition, null, 'paid'), $range, $audience, 'Extrato de transações', []),
            'monthly' => $this->monthlyBalance($definition, $range),
            default => $this->spendingByCategory($definition, $range),
        };
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array{from: ?string, to: ?string, label: string}  $range
     * @return array<string, mixed>
     */
    private function builder(array $definition, array $range, string $audience): array
    {
        return match ($definition['dataset']) {
            'invoices' => $this->invoiceReport($definition, $range, $audience),
            'net_worth' => $this->netWorthReport($definition, $range, $audience),
            'goals' => $this->goalReport($definition, $range, $audience),
            default => $definition['group_by'] === 'none'
                ? $this->statement(
                    $definition,
                    $range,
                    $audience,
                    'Extrato de transações',
                    $this->transactionTags($definition),
                )
                : $this->grouped($definition, $range, $audience),
        };
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array{from: ?string, to: ?string, label: string}  $range
     * @return array<string, mixed>
     */
    private function spendingByCategory(array $definition, array $range): array
    {
        $filters = $this->forced($definition, 'expense', 'paid');
        $groups = $this->transactions->groupedPaidTotals($filters, 'category');
        $total = '0.00';
        $count = 0;

        foreach ($groups as $group) {
            $total = Money::add($total, $group['expense']);
            $count += $group['count'];
        }

        $rows = [];

        foreach ($groups as $group) {
            $rows[] = [
                'category' => $group['name'],
                'amount' => $group['expense'],
                'share' => $this->ratio($group['expense'], $total),
                'count' => $group['count'],
            ];
        }

        $section = [
            'title' => null,
            'columns' => [
                $this->column('category', 'Categoria', 'text'),
                $this->column('amount', 'Valor', 'money'),
                $this->column('share', '% do total', 'percent'),
                $this->column('count', 'Qtd.', 'integer'),
            ],
            'rows' => $rows,
            'totals' => $rows === [] ? null : [
                'category' => 'Total',
                'amount' => $total,
                'share' => Money::compare($total, '0') === 1 ? '1' : null,
                'count' => $count,
            ],
            'bar_column' => 'share',
        ];

        return $this->pack('Gastos por categoria', [], [$section], count($rows));
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array{from: ?string, to: ?string, label: string}  $range
     * @return array<string, mixed>
     */
    private function periodSummary(array $definition, array $range): array
    {
        $current = $this->transactions->periodTotals($range['from'], $range['to']);
        $previousRange = $this->periods->previous($definition['period'], $definition['from'], $definition['to']);
        $previous = $previousRange === null
            ? null
            : $this->transactions->periodTotals($previousRange['from'], $previousRange['to']);
        $columns = [
            $this->column('metric', 'Indicador', 'text'),
            $this->column('current', 'Período', 'money'),
        ];
        $sections = [$this->kpiSection(null, $current)];
        $metrics = [
            ['Receitas', 'income'],
            ['Despesas', 'expense'],
            ['Reservado', 'reserved'],
            ['Saldo', 'balance'],
        ];
        $rows = [];

        foreach ($metrics as [$label, $key]) {
            $row = [
                'metric' => $label,
                'current' => $current[$key],
            ];

            if ($previous !== null) {
                $change = $this->change($previous[$key], $current[$key]);
                $row['previous'] = $previous[$key];
                $row['delta'] = $change['delta'];
                $row['share'] = $change['share'];
            }

            $rows[] = $row;
        }

        if ($previous !== null) {
            $columns[] = $this->column('previous', 'Período anterior', 'money');
            $columns[] = $this->column('delta', 'Variação (R$)', 'money', true);
            $columns[] = $this->column('share', 'Variação (%)', 'percent', true);
            $sections[] = [
                'kind' => 'table',
                'title' => null,
                'columns' => $columns,
                'rows' => $rows,
                'totals' => null,
                'bar_column' => null,
            ];
        }

        return $this->pack('Resumo do período', [], $sections, count($rows));
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array{from: ?string, to: ?string, label: string}  $range
     * @return array<string, mixed>
     */
    /**
     * @param  array<int, array{label: string, value: string}>  $filters
     */
    private function statement(array $definition, array $range, string $audience, string $title, array $filters): array
    {
        $total = $this->transactions->reportCount($definition);
        $this->guard($audience, $total);

        if ($total === 0) {
            return $this->pack($title, $filters, [], 0);
        }

        $lines = $this->transactions->reportLines(
            $definition,
            $audience === 'preview' ? self::PREVIEW_ROWS : null,
        );
        $rows = [];

        foreach ($lines as $transaction) {
            $rows[] = $this->statementRow($transaction);
        }

        $totals = $this->transactions->reportSummary($definition);

        return $this->pack($title, $filters, [
            $this->kpiSection('Totais', $totals),
            [
                'kind' => 'table',
                'title' => null,
                'columns' => $this->statementColumns(),
                'rows' => $rows,
                'totals' => null,
                'bar_column' => null,
            ],
        ], $total);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array{from: ?string, to: ?string, label: string}  $range
     * @return array<string, mixed>
     */
    private function monthlyBalance(array $definition, array $range): array
    {
        $window = $this->monthWindow($definition, $range);

        if ($window['keys'] === []) {
            return $this->pack('Saldo mês a mês', [], [], 0);
        }

        $groups = $this->transactions->groupedPaidTotals($definition, 'month');
        $indexed = [];

        foreach ($groups as $group) {
            $indexed[$group['key']] = $group;
        }

        $rows = [];
        $totals = $this->zeroGroup();

        foreach ($window['keys'] as $key) {
            $group = $indexed[$key] ?? $this->zeroGroup();
            $rows[] = [
                'month' => Month::label($key),
                'income' => $group['income'],
                'expense' => $group['expense'],
                'reserved' => $group['reserved'],
                'balance' => $group['balance'],
            ];
            $totals = $this->addGroup($totals, $group);
        }

        return $this->pack('Saldo mês a mês', [], [[
            'title' => null,
            'columns' => $this->flowColumns('Mês', 'month'),
            'rows' => $rows,
            'totals' => [
                'month' => 'Total',
                'income' => $totals['income'],
                'expense' => $totals['expense'],
                'reserved' => $totals['reserved'],
                'balance' => $totals['balance'],
            ],
            'bar_column' => null,
        ]], count($rows), $window['truncated'] ? 'Últimos 60 meses do período.' : null, $window['truncated']);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array{from: ?string, to: ?string, label: string}  $range
     * @return array<string, mixed>
     */
    private function grouped(array $definition, array $range, string $audience): array
    {
        $groupBy = $definition['group_by'];
        $groups = $this->transactions->groupedPaidTotals($definition, $groupBy);
        $this->guard($audience, count($groups));
        $rows = [];
        $totals = $this->zeroGroup();

        foreach ($groups as $group) {
            $rows[] = [
                'group' => $this->groupLabel($groupBy, $group),
                'income' => $group['income'],
                'expense' => $group['expense'],
                'reserved' => $group['reserved'],
                'balance' => $group['balance'],
                'count' => $group['count'],
            ];
            $totals = $this->addGroup($totals, $group);
        }

        $titles = [
            'day' => 'Transações por dia',
            'week' => 'Transações por semana',
            'month' => 'Transações por mês',
            'category' => 'Transações por categoria',
            'bank' => 'Transações por banco',
        ];

        return $this->pack($titles[$groupBy] ?? 'Transações', $this->transactionTags($definition), [[
            'title' => null,
            'columns' => [
                $this->column('group', 'Grupo', 'text'),
                $this->column('income', 'Receitas', 'money'),
                $this->column('expense', 'Despesas', 'money'),
                $this->column('reserved', 'Reservado', 'money'),
                $this->column('balance', 'Saldo', 'money'),
                $this->column('count', 'Qtd.', 'integer'),
            ],
            'rows' => $rows,
            'totals' => $rows === [] ? null : [
                'group' => 'Total',
                'income' => $totals['income'],
                'expense' => $totals['expense'],
                'reserved' => $totals['reserved'],
                'balance' => $totals['balance'],
                'count' => $totals['count'],
            ],
            'bar_column' => null,
        ]], count($rows));
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array{from: ?string, to: ?string, label: string}  $range
     * @return array<string, mixed>
     */
    private function invoiceReport(array $definition, array $range, string $audience): array
    {
        $rows = $this->invoices->reportInvoices(
            $definition['card_id'],
            $range['from'],
            $range['to'],
        );
        $this->guard($audience, count($rows));
        $filters = [];

        if ($definition['card_id'] !== null) {
            $name = Card::query()->whereKey($definition['card_id'])->value('name');
            $filters[] = ['label' => 'Cartão', 'value' => $name ?: '—'];
        }

        $table = [];

        foreach ($rows as $row) {
            $table[] = [
                'card' => $row['card'],
                'invoice' => $row['invoice'],
                'closing' => $row['closing'],
                'due' => $row['due'],
                'posted' => $row['posted'],
                'forecast' => $row['forecast'],
                'total' => $row['total'],
                'situation' => $row['situation'],
            ];
        }

        return $this->pack('Faturas', $filters, $table === [] ? [] : [[
            'title' => null,
            'columns' => [
                $this->column('card', 'Cartão', 'text'),
                $this->column('invoice', 'Fatura', 'text'),
                $this->column('closing', 'Fecha em', 'date'),
                $this->column('due', 'Vence em', 'date'),
                $this->column('posted', 'Lançado', 'money'),
                $this->column('forecast', 'Previsto', 'money'),
                $this->column('total', 'Total', 'money'),
                $this->column('situation', 'Situação', 'status'),
            ],
            'rows' => $table,
            'totals' => null,
            'bar_column' => null,
        ]], count($table));
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array{from: ?string, to: ?string, label: string}  $range
     * @return array<string, mixed>
     */
    private function netWorthReport(array $definition, array $range, string $audience): array
    {
        $presented = $this->netWorth->present($definition['period'], $definition['from'], $definition['to']);
        $records = array_reverse($presented['records']);
        $this->guard($audience, count($records));

        if ($records === []) {
            return $this->pack('Patrimônio', [], [], 0);
        }

        $rows = [];

        foreach ($records as $record) {
            $rows[] = [
                'month' => $record['label'],
                'total' => $record['total'],
                'variation' => $record['variation'],
            ];
        }

        $latest = $presented['records'][0];
        $accounts = [];
        $investments = [];

        foreach ($latest['banks'] as $bank) {
            $row = [
                'bank' => $bank['name'],
                'kind' => ($bank['kind'] ?? 'account') === 'investment' ? 'Investimento' : 'Conta',
                'balance' => $bank['balance'],
            ];

            if (($bank['kind'] ?? 'account') === 'investment') {
                $investments[] = $row;
            } else {
                $accounts[] = $row;
            }
        }

        $banks = [
            ...$accounts,
            [
                'bank' => 'Contas',
                'kind' => 'Subtotal',
                'balance' => $this->moneySum(array_column($accounts, 'balance')),
            ],
            ...$investments,
            [
                'bank' => 'Investimentos',
                'kind' => 'Subtotal',
                'balance' => $this->moneySum(array_column($investments, 'balance')),
            ],
        ];

        return $this->pack('Patrimônio', [], [[
            'title' => null,
            'columns' => [
                $this->column('month', 'Mês', 'text'),
                $this->column('total', 'Total', 'money'),
                $this->column('variation', 'Variação (R$)', 'money', true),
            ],
            'rows' => $rows,
            'totals' => null,
            'bar_column' => null,
        ], [
            'title' => 'Por banco no último fechamento do período',
            'columns' => [
                $this->column('bank', 'Banco', 'text'),
                $this->column('kind', 'Tipo', 'text'),
                $this->column('balance', 'Saldo', 'money'),
            ],
            'rows' => $banks,
            'totals' => null,
            'bar_column' => null,
        ]], count($records));
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array{from: ?string, to: ?string, label: string}  $range
     * @return array<string, mixed>
     */
    private function goalReport(array $definition, array $range, string $audience): array
    {
        $index = $this->goals->index([
            'period' => $definition['period'],
            'from' => $definition['from'],
            'to' => $definition['to'],
            'status' => $definition['goal_status'],
        ]);
        $goals = $index['goals'];
        $this->guard($audience, count($goals));
        $rows = [];

        foreach ($goals as $goal) {
            $percent = (int) ($goal['percent'] ?? 0);
            $deadline = $goal['deadline_key'] ?? null;
            $rows[] = [
                'name' => $goal['name'],
                'category' => $goal['category']['name'] ?? '—',
                'target' => $goal['target_amount'],
                'saved' => $goal['progress'],
                'share' => bcdiv((string) $percent, '100', 4),
                'remaining' => $goal['remaining'],
                'deadline' => $deadline === null ? null : Month::label($deadline),
                'situation' => match ($goal['state']) {
                    'completed' => 'Concluída',
                    'archived' => 'Arquivada',
                    default => 'Ativa',
                },
                'period_saved' => $goal['period_saved'],
            ];
        }

        $labels = [
            'active' => 'Ativas',
            'completed' => 'Concluídas',
            'archived' => 'Arquivadas',
        ];
        $filters = isset($labels[$definition['goal_status']])
            ? [['label' => 'Situação', 'value' => $labels[$definition['goal_status']]]]
            : [];

        return $this->pack('Metas', $filters, $rows === [] ? [] : [[
            'title' => null,
            'columns' => [
                $this->column('name', 'Meta', 'text'),
                $this->column('category', 'Categoria', 'text'),
                $this->column('target', 'Valor alvo', 'money'),
                $this->column('saved', 'Guardado', 'money'),
                $this->column('share', '%', 'percent'),
                $this->column('remaining', 'Restante', 'money'),
                $this->column('deadline', 'Prazo', 'text'),
                $this->column('situation', 'Situação', 'status'),
                $this->column('period_saved', 'Guardado no período', 'money'),
            ],
            'rows' => $rows,
            'totals' => null,
            'bar_column' => null,
        ]], count($rows));
    }

    /**
     * @param  array<int, array{label: string, value: string}>  $filters
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<string, mixed>
     */
    private function pack(string $title, array $filters, array $sections, int $rowCount, ?string $note = null, bool $truncated = false): array
    {
        return [
            'title' => $title,
            'filters' => $filters,
            'sections' => $sections,
            'row_count' => $rowCount,
            'note' => $note,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  array<string, mixed>  $built
     * @param  array<string, mixed>  $definition
     * @param  array{from: ?string, to: ?string, label: string}  $range
     */
    private function finish(array $built, array $definition, array $range, string $audience): ReportData
    {
        $sections = $this->presentSections($built['sections']);
        $rowCount = $built['row_count'];
        $shown = $rowCount;
        $truncated = $built['truncated'];

        if ($audience === 'preview' && $rowCount > self::PREVIEW_ROWS) {
            foreach ($sections as &$section) {
                if (($section['kind'] ?? '') === 'table' && isset($section['rows'])) {
                    $section['rows'] = array_slice($section['rows'], 0, self::PREVIEW_ROWS);
                    $shown = self::PREVIEW_ROWS;
                    $truncated = true;
                    break;
                }
            }
            unset($section);
        }

        return new ReportData(
            title: $built['title'],
            periodLabel: $range['label'],
            periodDetail: $this->periodDetail($definition, $range),
            filters: $built['filters'],
            generatedAt: now('America/Sao_Paulo')->format('Y-m-d H:i'),
            sections: $sections,
            rowCount: $rowCount,
            truncated: $truncated,
            empty: $rowCount === 0,
            landscape: $this->landscape($sections),
            note: $built['note'],
            shownCount: $rowCount === 0 ? 0 : $shown,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, mixed>>
     */
    private function presentSections(array $sections): array
    {
        foreach ($sections as &$section) {
            $section['kind'] = $section['kind'] ?? 'table';

            if ($section['kind'] === 'kpis') {
                foreach ($section['items'] as &$item) {
                    $cell = $this->formatter->present($item['raw'], ['type' => 'money']);
                    $item['raw'] = $cell['raw'];
                    $item['text'] = $cell['text'];
                    $item['tone'] = $cell['tone'];
                }
                unset($item);

                continue;
            }

            $bar = $section['bar_column'] ?? null;

            foreach ($section['rows'] as &$row) {
                $row = $this->presentRow($row, $section['columns'], $bar);
            }
            unset($row);

            if (! empty($section['totals'])) {
                $section['totals'] = $this->presentRow($section['totals'], $section['columns'], $bar);
            }
        }
        unset($section);

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<string, array{raw: mixed, text: string, tone: string, bar?: string}>
     */
    private function presentRow(array $row, array $columns, ?string $bar): array
    {
        $presented = [];

        foreach ($columns as $column) {
            $cell = $this->formatter->present($row[$column['key']] ?? null, $column);

            if (($row['metric'] ?? null) === 'Despesas' && in_array($column['key'], ['delta', 'share'], true)) {
                $cell['tone'] = match ($cell['tone']) {
                    'positive' => 'negative',
                    'negative' => 'positive',
                    default => $cell['tone'],
                };
            }

            if ($bar === $column['key']) {
                $cell['bar'] = $this->formatter->barWidth($cell['raw']);
            }

            $presented[$column['key']] = $cell;
        }

        return $presented;
    }

    /**
     * @param  array{from: ?string, to: ?string, label: string}  $range
     */
    private function periodDetail(array $definition, array $range): string
    {
        if ($definition['period'] === 'all' || $range['from'] === null || $range['to'] === null) {
            $first = $this->span()['from'];

            return $first === null ? 'Todo o período' : 'Todo o período · desde '.$this->brDate($first);
        }

        $dates = $this->brDate($range['from']).' a '.$this->brDate($range['to']);

        return match ($definition['period']) {
            'quarter' => 'Trimestre · '.$dates,
            'semester' => 'Semestre · '.$dates,
            'year' => 'Ano de '.substr($range['from'], 0, 4).' · '.$dates,
            'custom' => $dates,
            default => $this->monthPhrase($range['from']).' · '.$dates,
        };
    }

    private function monthPhrase(string $date): string
    {
        $label = Month::label(substr($date, 0, 7));

        return preg_replace('/\s(?=\d{4}$)/', ' de ', $label) ?? $label;
    }

    /**
     * @return array{from: ?string, to: ?string}
     */
    private function span(): array
    {
        return $this->span ??= $this->transactions->dateSpan();
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function kpiSection(?string $title, array $summary): array
    {
        return [
            'kind' => 'kpis',
            'title' => $title,
            'items' => [
                ['key' => 'income', 'label' => 'Receitas', 'raw' => $summary['income']],
                ['key' => 'expense', 'label' => 'Despesas', 'raw' => $summary['expense']],
                ['key' => 'reserved', 'label' => 'Reservado', 'raw' => $summary['reserved']],
                ['key' => 'balance', 'label' => 'Saldo', 'raw' => $summary['balance']],
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    private function landscape(array $sections): bool
    {
        foreach ($sections as $section) {
            if (($section['kind'] ?? 'table') === 'table' && count($section['columns'] ?? []) >= 7) {
                return true;
            }
        }

        return false;
    }

    private function guard(string $audience, int $count): void
    {
        if ($audience === 'pdf' && $count > self::PDF_MAX) {
            throw new ReportLimitException('pdf');
        }

        if ($audience === 'xlsx' && $count > self::XLSX_MAX) {
            throw new ReportLimitException('xlsx');
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function forced(array $definition, ?string $type, string $status): array
    {
        return [
            'period' => $definition['period'],
            'from' => $definition['from'],
            'to' => $definition['to'],
            'type' => $type,
            'status' => $status,
            'category_id' => null,
            'bank_id' => null,
            'card_id' => null,
            'payment_method_id' => null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function statementColumns(): array
    {
        return [
            $this->column('date', 'Data', 'date'),
            $this->column('description', 'Descrição', 'text'),
            $this->column('tags', 'Tags', 'text'),
            $this->column('category', 'Categoria', 'text'),
            $this->column('account', 'Banco/Cartão', 'text'),
            $this->column('payment', 'Forma de pagamento', 'text'),
            $this->column('type', 'Tipo', 'text'),
            $this->column('situation', 'Situação', 'status'),
            $this->column('amount', 'Valor', 'money'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function statementRow(Transaction $transaction): array
    {
        $bank = $transaction->bank?->name;
        $card = $transaction->card?->name;
        $account = $card && $bank ? $bank.' · '.$card : ($card ?: ($bank ?: '—'));
        $negative = $transaction->type === 'expense'
            || ($transaction->type === 'reserve' && $transaction->reserve_action === 'withdraw');
        $amount = Money::of($transaction->amount);

        return [
            'date' => $transaction->date?->toDateString(),
            'description' => $transaction->description,
            'tags' => $transaction->relationLoaded('tags') && $transaction->tags->isNotEmpty()
                ? $transaction->tags->pluck('name')->implode(', ')
                : '—',
            'category' => $transaction->category?->name ?? '—',
            'account' => $account,
            'payment' => $transaction->paymentMethod?->name ?? '—',
            'type' => match ($transaction->type) {
                'income' => 'Receita',
                'expense' => 'Despesa',
                'reserve' => $transaction->reserve_action === 'withdraw' ? 'Resgate' : 'Reserva',
                default => (string) $transaction->type,
            },
            'situation' => $transaction->status === 'paid' ? 'Paga' : 'Pendente',
            'amount' => $negative ? Money::sub('0', $amount) : $amount,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function flowColumns(string $label, string $key): array
    {
        return [
            $this->column($key, $label, 'text'),
            $this->column('income', 'Receitas', 'money'),
            $this->column('expense', 'Despesas', 'money'),
            $this->column('reserved', 'Reservado', 'money'),
            $this->column('balance', 'Saldo', 'money'),
        ];
    }

    /**
     * @param  array{key: string, name: string}  $group
     */
    private function groupLabel(string $groupBy, array $group): string
    {
        return match ($groupBy) {
            'day' => $this->brDate($group['key']),
            'week' => 'Semana de '.$this->brDate($group['key']),
            'month' => Month::label($group['key']),
            default => $group['name'] !== '' ? $group['name'] : '—',
        };
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array{from: ?string, to: ?string}  $range
     * @return array{keys: array<int, string>, truncated: bool}
     */
    private function monthWindow(array $definition, array $range): array
    {
        if ($range['from'] === null || $range['to'] === null) {
            $span = $this->span();

            if ($span['from'] === null || $span['to'] === null) {
                return ['keys' => [], 'truncated' => false];
            }

            $start = substr($span['from'], 0, 7);
            $end = substr($span['to'], 0, 7);
            $current = Month::currentKey();

            if ($end < $current) {
                $end = $current;
            }
        } else {
            $start = substr($range['from'], 0, 7);
            $end = substr($range['to'], 0, 7);
        }

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        $truncated = false;

        if ($this->periods->inclusiveMonths($start, $end) > self::MONTH_CAP) {
            $start = Month::shift($end, -(self::MONTH_CAP - 1)) ?? $start;
            $truncated = true;
        }

        $keys = [];
        $cursor = $start;
        $guard = 0;

        while ($cursor !== null && $cursor <= $end && $guard < self::MONTH_CAP) {
            $keys[] = $cursor;

            if ($cursor === $end) {
                break;
            }

            $cursor = Month::shift($cursor, 1);
            $guard++;
        }

        return ['keys' => $keys, 'truncated' => $truncated];
    }

    /**
     * Tags for choices the person made. Paid is the default and stays off the list.
     *
     * @param  array<string, mixed>  $definition
     * @return array<int, array{label: string, value: string}>
     */
    private function transactionTags(array $definition): array
    {
        $tags = [];
        $types = ['income' => 'Receita', 'expense' => 'Despesa', 'reserve' => 'Reserva'];
        $groups = [
            'day' => 'Dia',
            'week' => 'Semana',
            'month' => 'Mês',
            'category' => 'Categoria',
            'bank' => 'Banco',
        ];

        if ($definition['type'] !== null) {
            $tags[] = ['label' => 'Tipo', 'value' => $types[$definition['type']] ?? $definition['type']];
        }

        foreach ([
            [Category::class, $definition['category_id'], 'Categoria'],
            [Bank::class, $definition['bank_id'], 'Banco'],
            [Card::class, $definition['card_id'], 'Cartão'],
            [PaymentMethod::class, $definition['payment_method_id'], 'Forma de pagamento'],
        ] as [$model, $id, $label]) {
            $tag = $this->named($model, $id, $label);

            if ($tag !== null) {
                $tags[] = $tag;
            }
        }

        $tagIds = $definition['tag_ids'] ?? [];

        if ($tagIds !== []) {
            $names = Tag::query()->whereIn('id', $tagIds)->orderBy('name')->pluck('name');

            if ($names->isNotEmpty()) {
                $tags[] = ['label' => 'Tags', 'value' => $names->implode(', ')];
            }
        }

        if ($definition['status'] !== null && $definition['status'] !== 'paid') {
            $tags[] = [
                'label' => 'Situação',
                'value' => $definition['status'] === 'pending' ? 'Pendente' : 'Todas',
            ];
        }

        if (($definition['group_by'] ?? 'none') !== 'none') {
            $tags[] = ['label' => 'Agrupar por', 'value' => $groups[$definition['group_by']] ?? $definition['group_by']];
        }

        return $tags;
    }

    /**
     * @param  class-string  $model
     * @return array{label: string, value: string}|null
     */
    private function named(string $model, ?int $id, string $label): ?array
    {
        if ($id === null) {
            return null;
        }

        $name = $model::query()->whereKey($id)->value('name');

        return ['label' => $label, 'value' => $name ?: '—'];
    }

    /**
     * @return array{delta: string, share: ?string}
     */
    private function change(string $previous, string $current): array
    {
        return [
            'delta' => Money::sub($current, $previous),
            'share' => Money::compare($previous, '0') === 0 ? null : bcdiv(Money::sub($current, $previous), Money::of($previous), 8),
        ];
    }

    private function ratio(string $part, string $total): ?string
    {
        if (Money::compare($total, '0') !== 1) {
            return null;
        }

        return bcdiv(Money::of($part), Money::of($total), 8);
    }

    /**
     * @return array{income: string, expense: string, reserved: string, balance: string, count: int}
     */
    private function zeroGroup(): array
    {
        return [
            'income' => '0.00',
            'expense' => '0.00',
            'reserved' => '0.00',
            'balance' => '0.00',
            'count' => 0,
        ];
    }

    /**
     * @param  array{income: string, expense: string, reserved: string, balance: string, count: int}  $left
     * @param  array{income: string, expense: string, reserved: string, balance: string, count: int}  $right
     * @return array{income: string, expense: string, reserved: string, balance: string, count: int}
     */
    private function addGroup(array $left, array $right): array
    {
        $income = Money::add($left['income'], $right['income']);
        $expense = Money::add($left['expense'], $right['expense']);

        return [
            'income' => $income,
            'expense' => $expense,
            'reserved' => Money::add($left['reserved'], $right['reserved']),
            'balance' => Money::sub($income, $expense),
            'count' => $left['count'] + $right['count'],
        ];
    }

    /**
     * @return array{key: string, label: string, type: string, signed?: bool}
     */
    private function column(string $key, string $label, string $type, bool $signed = false): array
    {
        $column = [
            'key' => $key,
            'label' => $label,
            'type' => $type,
        ];

        if ($signed) {
            $column['signed'] = true;
        }

        return $column;
    }

    /**
     * @param  array<int, string>  $amounts
     */
    private function moneySum(array $amounts): string
    {
        $total = '0.00';

        foreach ($amounts as $amount) {
            $total = Money::add($total, $amount);
        }

        return $total;
    }

    private function brDate(string $date): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $date, $match)) {
            return $date;
        }

        return $match[3].'/'.$match[2].'/'.$match[1];
    }
}
