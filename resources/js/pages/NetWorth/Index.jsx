import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Calendar, Landmark } from 'lucide-react';
import Button from '../../components/Button/Button';
import Card from '../../components/Card/Card';
import EmptyState from '../../components/EmptyState/EmptyState';
import EvolutionChart from '../../components/EvolutionChart/EvolutionChart';
import PageHeader from '../../components/PageHeader/PageHeader';
import AppLayout from '../../layouts/AppLayout/AppLayout';
import { formatDate, formatMoney, formatPlainMoney } from '../../lib/format';
import PeriodPopover from '../Analysis/PeriodPopover';
import ClosingModal from './ClosingModal';
import '../../components/DatePicker/DatePicker.css';
import './Index.css';

const PERIODS = [
    { key: 'current_month', label: 'Mês atual' },
    { key: 'previous_month', label: 'Mês anterior' },
    { key: 'quarter', label: 'Trimestre' },
    { key: 'semester', label: 'Semestre' },
    { key: 'year', label: 'Ano' },
    { key: 'all', label: 'Todo o período' },
    { key: 'custom', label: 'Personalizado' },
];

function visitNetWorth(next) {
    const params = {};

    if (next.period && next.period !== 'year') {
        params.period = next.period;
    }

    if (next.period === 'custom') {
        params.period = 'custom';
        params.from = next.from;
        params.to = next.to;
    }

    router.get('/net-worth', params, {
        preserveScroll: true,
        preserveState: true,
    });
}

function toneClass(kind, variation) {
    if (kind !== 'amount') {
        return '';
    }

    return String(variation).startsWith('-') ? 'is-negative' : 'is-positive';
}

function amountVariation(kind, variation) {
    if (kind === 'none') {
        return '—';
    }

    if (kind === 'same') {
        return 'Sem alteração';
    }

    if (kind === 'new') {
        return 'Novo';
    }

    return formatMoney(variation);
}

const MONTHS = [
    'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
    'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
];

function closingPhrase(key) {
    const match = /^(\d{4})-(\d{2})$/.exec(key ?? '');

    if (!match) {
        return key ?? '';
    }

    return `${MONTHS[parseInt(match[2], 10) - 1]} de ${match[1]}`;
}

function periodError(errors) {
    for (const key of ['period', 'from', 'to']) {
        const value = errors?.[key];

        if (Array.isArray(value) && value[0]) {
            return value[0];
        }

        if (typeof value === 'string' && value !== '') {
            return value;
        }
    }

    return '';
}

export default function Index({
    period,
    has_banks: hasBanks,
    has_closings: hasClosings,
    current_month: currentMonth,
    suggested_month: suggestedMonth,
    indicators,
    by_bank: byBank,
    chart,
    records,
}) {
    const pageErrors = usePage().props.errors;
    const [calendarOpen, setCalendarOpen] = useState(false);
    const [modalMonth, setModalMonth] = useState(null);
    const activePeriod = calendarOpen ? 'custom' : period.key;
    const banner = periodError(pageErrors);

    function choosePeriod(key) {
        if (key === 'custom') {
            setCalendarOpen(true);
            return;
        }

        setCalendarOpen(false);
        visitNetWorth({ period: key });
    }

    return (
        <>
            <Head title="Patrimônio" />
            <div className="page net-worth">
                <PageHeader
                    title="Patrimônio"
                    subtitle="O que você tem guardado, banco a banco."
                    actions={hasBanks ? (
                        <Button onClick={() => setModalMonth(suggestedMonth)}>Registrar fechamento</Button>
                    ) : null}
                />
                {banner ? <p className="net-worth-alert" role="alert">{banner}</p> : null}
                {hasBanks ? (
                    <div className="net-worth-tools">
                        {calendarOpen ? (
                            <button
                                type="button"
                                className="popover-backdrop"
                                aria-label="Fechar"
                                tabIndex={-1}
                                onClick={() => setCalendarOpen(false)}
                            />
                        ) : null}
                        <div className="period-bar">
                            <div className="period-seg" role="group" aria-label="Período">
                                {PERIODS.map((item) => (
                                    <button
                                        key={item.key}
                                        type="button"
                                        aria-pressed={activePeriod === item.key}
                                        onClick={() => choosePeriod(item.key)}
                                    >
                                        {item.label}
                                    </button>
                                ))}
                            </div>
                            <button type="button" className="period-range" onClick={() => choosePeriod('custom')}>
                                <Calendar aria-hidden="true" strokeWidth={1.75} />
                                {period.from && period.to ? (
                                    <>
                                        <span>{formatDate(period.from)}</span>
                                        <i>–</i>
                                        <span>{formatDate(period.to)}</span>
                                    </>
                                ) : (
                                    <span>Todo o período</span>
                                )}
                            </button>
                            <PeriodPopover
                                open={calendarOpen}
                                initialFrom={period.from}
                                initialTo={period.to}
                                onClose={() => setCalendarOpen(false)}
                                onApply={(from, to) => {
                                    setCalendarOpen(false);
                                    visitNetWorth({ period: 'custom', from, to });
                                }}
                            />
                        </div>
                    </div>
                ) : null}
                {!hasBanks ? (
                    <Card className="card--fit">
                        <EmptyState
                            icon={Landmark}
                            message="Cadastre um banco em Gerenciar."
                            shortcutLabel="Ir para Gerenciar"
                            shortcutHref="/manage"
                        />
                    </Card>
                ) : null}
                {hasBanks && !hasClosings ? (
                    <Card className="card--fit">
                        <EmptyState
                            icon={Landmark}
                            message="Nenhum fechamento ainda. Feche o mês atual para começar."
                            shortcutLabel="Registrar fechamento"
                            onShortcut={() => setModalMonth(suggestedMonth)}
                        />
                    </Card>
                ) : null}
                {hasBanks && hasClosings ? (
                    <>
                        <div className="net-worth-grid">
                            <Card className="card--highlight net-worth-span-4 net-worth-indicator">
                                <p className="net-worth-label">Patrimônio atual</p>
                                <p className="net-worth-value">{formatPlainMoney(indicators.total)}</p>
                                <p className="net-worth-note">
                                    {indicators.variation_kind === 'amount' ? (
                                        <>
                                            <span className={toneClass('amount', indicators.variation)}>
                                                {formatMoney(indicators.variation)}
                                            </span>
                                            {' '}
                                            desde o último fechamento
                                        </>
                                    ) : null}
                                    {indicators.variation_kind === 'same' ? 'Sem alteração desde o último fechamento' : null}
                                    {indicators.variation_kind === 'none' ? '—' : null}
                                </p>
                            </Card>
                            <Card className="net-worth-span-4 net-worth-indicator">
                                <p className="net-worth-label">Último fechamento</p>
                                <p className="net-worth-value net-worth-value--md">{indicators.last_label}</p>
                            </Card>
                            <Card className="net-worth-span-4 net-worth-indicator">
                                <p className="net-worth-label">Registros</p>
                                <p className="net-worth-value net-worth-value--md">{indicators.records_label}</p>
                            </Card>
                            <Card className="net-worth-span-12" title="Evolução do patrimônio">
                                {chart.points.length > 0 ? (
                                    <EvolutionChart points={chart.points} ariaLabel={chart.aria_label} />
                                ) : (
                                    <EmptyState icon={Landmark} message="Nenhum fechamento neste período." />
                                )}
                            </Card>
                            {byBank ? (
                                <Card className="net-worth-span-5" title={byBank.title}>
                                    <div className="net-worth-banks">
                                        {byBank.banks.map((bank) => (
                                            <div className="net-worth-bank" key={bank.id}>
                                                <span
                                                    className={bank.color ? 'net-worth-tile' : 'net-worth-tile net-worth-tile--plain'}
                                                    style={bank.color ? { '--item-color': bank.color } : undefined}
                                                >
                                                    <Landmark aria-hidden="true" strokeWidth={1.75} />
                                                </span>
                                                <div className="net-worth-bank__name">
                                                    <strong>{bank.name}</strong>
                                                    <span className={toneClass(bank.variation_kind, bank.variation)}>
                                                        {bank.variation_kind === 'amount'
                                                            ? `Variação ${formatMoney(bank.variation)}`
                                                            : amountVariation(bank.variation_kind, bank.variation)}
                                                    </span>
                                                </div>
                                                <span className="net-worth-bank__balance">{formatPlainMoney(bank.balance)}</span>
                                            </div>
                                        ))}
                                    </div>
                                </Card>
                            ) : null}
                            <Card className="net-worth-span-7 net-worth-records-card" title="Registros por mês">
                            {records.length === 0 ? (
                                <EmptyState icon={Landmark} message="Nenhum fechamento neste período." />
                            ) : (
                                <div
                                    className="net-worth-records"
                                    tabIndex={0}
                                    role="region"
                                    aria-label="Registros por mês"
                                >
                                    {records.map((record) => (
                                        <button
                                            key={record.id}
                                            type="button"
                                            className="net-worth-record"
                                            aria-label={`Editar fechamento de ${closingPhrase(record.month)}`}
                                            onClick={() => setModalMonth(record.month)}
                                        >
                                            <div className="net-worth-record__main">
                                                <strong>{record.label}</strong>
                                                {record.notes ? <span className="net-worth-record__notes">{record.notes}</span> : null}
                                                <span className="net-worth-record__banks">
                                                    {record.banks.map((bank) => `${bank.name} ${formatPlainMoney(bank.balance)}`).join(' · ')}
                                                </span>
                                            </div>
                                            <div className="net-worth-record__values">
                                                <span>{formatPlainMoney(record.total)}</span>
                                                <span className={toneClass(record.variation_kind, record.variation)}>
                                                    {amountVariation(record.variation_kind, record.variation)}
                                                </span>
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </Card>
                        </div>
                    </>
                ) : null}
            </div>
            <ClosingModal month={modalMonth} maxMonth={currentMonth} onClose={() => setModalMonth(null)} />
        </>
    );
}

Index.layout = (page) => <AppLayout>{page}</AppLayout>;
