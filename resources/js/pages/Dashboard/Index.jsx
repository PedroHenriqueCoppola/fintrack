import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Calendar, CreditCard, Landmark, Receipt, Target } from 'lucide-react';
import BarsChart from '../../components/BarsChart/BarsChart';
import Card from '../../components/Card/Card';
import DonutChart from '../../components/DonutChart/DonutChart';
import EmptyState from '../../components/EmptyState/EmptyState';
import EvolutionChart from '../../components/EvolutionChart/EvolutionChart';
import Money from '../../components/Money/Money';
import PageHeader from '../../components/PageHeader/PageHeader';
import TransactionModal, { NewTransactionButton } from '../../components/TransactionModal/TransactionModal';
import UsageBar from '../../components/UsageBar/UsageBar';
import AppLayout from '../../layouts/AppLayout/AppLayout';
import { formatDate, formatMoney, formatNeutralMoney, formatPlainMoney } from '../../lib/format';
import CatalogIcon from '../Manage/icons';
import PeriodPopover from '../Analysis/PeriodPopover';
import ReferenceSeal from '../../components/ReferenceSeal/ReferenceSeal';
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

const BREAKDOWNS = [
    { key: 'category', label: 'Categoria' },
    { key: 'bank', label: 'Banco' },
    { key: 'card', label: 'Cartão' },
];

function greeting(date) {
    const hour = date.getHours();

    if (hour >= 5 && hour < 12) {
        return 'Bom dia';
    }

    if (hour >= 12 && hour < 18) {
        return 'Boa tarde';
    }

    return 'Boa noite';
}

function firstName(name) {
    const [part] = String(name ?? '').trim().split(/\s+/);

    return part ?? '';
}

function firstError(errors) {
    const value = Object.values(errors ?? {})[0];

    if (!value) {
        return '';
    }

    return Array.isArray(value) ? value[0] : value;
}

function queryParams(next) {
    const params = {};
    const period = next.period || 'current_month';

    if (period !== 'current_month') {
        params.period = period;
    }

    if (period === 'custom') {
        params.period = 'custom';
        params.from = next.from;
        params.to = next.to;
    }

    if (next.breakdown && next.breakdown !== 'category') {
        params.breakdown = next.breakdown;
    }

    return params;
}

function visitDashboard(next, { only = null } = {}) {
    router.get('/', queryParams(next), {
        preserveScroll: true,
        preserveState: true,
        replace: Boolean(only),
        only: only ?? undefined,
    });
}

function Variation({ variation, comparison }) {
    if (!comparison || !variation || (variation.kind === 'none' && !variation.message)) {
        return null;
    }

    if (variation.kind === 'none') {
        return <p className="dashboard-note">{variation.message}</p>;
    }

    if (variation.kind === 'same') {
        return <p className="dashboard-note">{`${variation.message} ${comparison}`}</p>;
    }

    return (
        <p className={`dashboard-note dashboard-note--${variation.tone}`}>
            <span>{formatMoney(variation.amount)}</span>
            {` ${comparison}`}
        </p>
    );
}

function Indicator({ label, amount, plain = false, variation, comparison, support = null, legend = null }) {
    return (
        <>
            <h2 className="dashboard-label">{label}</h2>
            <p className={plain ? 'dashboard-figure dashboard-figure--plain' : 'dashboard-figure'}>
                {plain ? formatNeutralMoney(amount) : <Money value={amount} />}
            </p>
            {legend ? <p className="dashboard-legend">{legend}</p> : null}
            <Variation variation={variation} comparison={comparison} />
            {support ? <p className="dashboard-note">{support}</p> : null}
        </>
    );
}

function PlanBar({ line }) {
    if (line.used_percent === null || line.used_percent === undefined) {
        return <p className="dashboard-plan__unplanned">Sem valor do mês</p>;
    }

    return <UsageBar percent={line.used_percent} over={line.over} nearLimit={line.near_limit} />;
}

function remainingLabel(value) {
    if (String(value).startsWith('-')) {
        return formatMoney(value);
    }

    return formatPlainMoney(value);
}

function shortDate(value) {
    const full = formatDate(value);

    return full.length >= 5 ? full.slice(0, 5) : full;
}

function transactionMeta(transaction) {
    const payment = transaction.card?.name || transaction.payment_method?.name;

    return [shortDate(transaction.date), payment].filter(Boolean).join(' · ');
}

export default function Index({
    options,
    filters,
    period,
    indicators,
    planning,
    invoices,
    bars,
    spending,
    transactions,
    netWorth,
    goals,
}) {
    const { auth, errors } = usePage().props;
    const name = firstName(auth?.user?.name);
    const hello = greeting(new Date());
    const title = name ? `${hello}, ${name}` : hello;
    const banner = firstError(errors);
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState(null);
    const [calendarOpen, setCalendarOpen] = useState(false);
    const activePeriod = calendarOpen ? 'custom' : period.key;
    const current = {
        period: period.key,
        from: period.from,
        to: period.to,
        breakdown: filters.breakdown,
    };

    function choosePeriod(key) {
        if (key === 'custom') {
            setCalendarOpen(true);
            return;
        }

        setCalendarOpen(false);
        visitDashboard({ ...current, period: key, from: null, to: null });
    }

    function chooseBreakdown(key) {
        visitDashboard({ ...current, breakdown: key }, { only: ['spending', 'filters'] });
    }

    return (
        <>
            <Head title="Dashboard" />
            <div className="page dashboard">
                <PageHeader
                    title={title}
                    subtitle="Seu resumo financeiro do período."
                    actions={<NewTransactionButton onClick={() => setCreating(true)} />}
                />
                {banner ? <p className="dashboard-alert" role="alert">{banner}</p> : null}
                <div className="dashboard-tools">
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
                                visitDashboard({ ...current, period: 'custom', from, to });
                            }}
                        />
                    </div>
                </div>

                <div className="dashboard-grid">
                    <Card className="card--highlight dashboard-span-4">
                        <Indicator
                            label="Saldo do período"
                            amount={indicators.balance.amount}
                            variation={indicators.balance.variation}
                            comparison={indicators.comparison}
                            legend={indicators.balance.legend}
                        />
                    </Card>
                    <div className="dashboard-span-8 dashboard-minis">
                        <Card>
                            <Indicator
                                label="Receitas"
                                amount={indicators.income.amount}
                                variation={indicators.income.variation}
                                comparison={indicators.comparison}
                                support={indicators.income.count_label}
                            />
                        </Card>
                        <Card>
                            <Indicator
                                label="Despesas"
                                amount={indicators.expense.amount}
                                variation={indicators.expense.variation}
                                comparison={indicators.comparison}
                                support={indicators.expense.count_label}
                            />
                        </Card>
                        <Card>
                            <Indicator
                                label="Reservado"
                                amount={indicators.reserved.amount}
                                plain
                                variation={indicators.reserved.variation}
                                comparison={indicators.comparison}
                            />
                        </Card>
                    </div>
                </div>

                <div className="dashboard-grid">
                    <Card className="dashboard-span-7">
                        <div className="dashboard-head">
                            <h2>{`Uso do planejado · ${planning.phrase}`}</h2>
                            <Link href={planning.href}>Ver planejamento</Link>
                        </div>
                        {planning.exists ? (
                            <>
                                {planning.lines.length === 0 ? (
                                    <p className="dashboard-note">Nenhuma categoria com valor ou gasto neste mês.</p>
                                ) : (
                                    <div className="dashboard-cats">
                                        {planning.lines.map((line) => (
                                            <article key={line.category_id} className="dashboard-cat">
                                                <div className="dashboard-cat__top">
                                                    <span
                                                        className={line.color ? 'dashboard-tile' : 'dashboard-tile dashboard-tile--plain'}
                                                        style={line.color ? { '--item-color': line.color } : undefined}
                                                        aria-hidden="true"
                                                    >
                                                        <CatalogIcon name={line.icon} />
                                                    </span>
                                                    <div className="dashboard-cat__copy">
                                                        <p className="dashboard-cat__name">{line.name}</p>
                                                        <p className="dashboard-cat__meta">
                                                            {`${formatPlainMoney(line.spent)} de ${formatPlainMoney(line.planned)}`}
                                                        </p>
                                                    </div>
                                                    <p className={String(line.remaining).startsWith('-') ? 'dashboard-cat__rest dashboard-cat__rest--over' : 'dashboard-cat__rest'}>
                                                        {remainingLabel(line.remaining)}
                                                    </p>
                                                </div>
                                                <PlanBar line={line} />
                                            </article>
                                        ))}
                                    </div>
                                )}
                                {planning.total ? (
                                    <div className="dashboard-total">
                                        <div>
                                            <p className="dashboard-cat__name">Total do mês</p>
                                            <p className="dashboard-cat__meta">
                                                {`${formatPlainMoney(planning.total.spent)} de ${formatPlainMoney(planning.total.planned)}`}
                                            </p>
                                        </div>
                                        <PlanBar line={planning.total} />
                                    </div>
                                ) : null}
                            </>
                        ) : (
                            <EmptyState
                                icon={Calendar}
                                message={`Nenhum planejamento para ${planning.phrase}`}
                                shortcutLabel="Planejar o mês"
                                shortcutHref={planning.href}
                            />
                        )}
                    </Card>
                    <Card className="dashboard-span-5 dashboard-invoices">
                        <div className="dashboard-head">
                            <h2>Fatura aberta dos cartões</h2>
                            <Link href="/invoices">Ver faturas</Link>
                        </div>
                        {invoices.cards.length === 0 ? (
                            <EmptyState
                                icon={CreditCard}
                                message="Nenhum cartão ativo."
                                shortcutLabel="Gerenciar"
                                shortcutHref="/manage"
                            />
                        ) : (
                            <div className="dashboard-invoice-list">
                                {invoices.cards.map((card) => (
                                    <Link key={card.id} href={`/invoices?card=${card.id}`} className="dashboard-invoice">
                                        <span className="dashboard-invoice__main">
                                            <span className="dashboard-invoice__name">{card.name}</span>
                                            <span className="dashboard-invoice__meta">
                                                {`${card.month_label} · Fecha em ${card.closes_on}`}
                                            </span>
                                        </span>
                                        <span className="dashboard-invoice__money">
                                            <span className="invoice-chip invoice-chip--neutral">{card.situation_label}</span>
                                            <span className="dashboard-invoice__estimated">{formatPlainMoney(card.estimated)}</span>
                                            <span className="dashboard-invoice__split">
                                                {`Lançado ${formatPlainMoney(card.posted)} · Previsto ${formatPlainMoney(card.forecast)}`}
                                            </span>
                                        </span>
                                        {card.closed ? (
                                            <span className="dashboard-invoice__closed">
                                                <span className="invoice-chip">{card.closed.situation_label}</span>
                                                {card.closed.label}
                                            </span>
                                        ) : null}
                                    </Link>
                                ))}
                            </div>
                        )}
                    </Card>
                </div>

                <div className="dashboard-grid">
                    <Card className="dashboard-span-7">
                        <div className="dashboard-head">
                            <h2>Receitas × despesas</h2>
                        </div>
                        <BarsChart points={bars.points} ariaLabel={bars.aria_label} axisMax={bars.axis_max_cents} />
                    </Card>
                    <Card className="dashboard-span-5">
                        <div className="dashboard-head">
                            <h2>Gastos</h2>
                            <div className="period-seg" role="group" aria-label="Agrupar gastos">
                                {BREAKDOWNS.map((item) => (
                                    <button
                                        key={item.key}
                                        type="button"
                                        aria-pressed={spending.breakdown === item.key}
                                        onClick={() => chooseBreakdown(item.key)}
                                    >
                                        {item.label}
                                    </button>
                                ))}
                            </div>
                        </div>
                        {spending.card_only ? <p className="dashboard-note">Só compras no cartão</p> : null}
                        {spending.empty ? (
                            <EmptyState icon={Receipt} message="Nenhuma despesa neste período." />
                        ) : (
                            <DonutChart
                                slices={spending.slices}
                                total={spending.total}
                                ariaLabel={spending.aria_label}
                            />
                        )}
                    </Card>
                </div>

                <div className="dashboard-grid">
                    <Card className="dashboard-span-7">
                        <div className="dashboard-head">
                            <h2>Últimas transações</h2>
                            <Link href={transactions.href}>Ver todas</Link>
                        </div>
                        {transactions.items.length === 0 ? (
                            <EmptyState icon={Receipt} message="Nenhuma transação neste período." />
                        ) : (
                            <div className="dashboard-tx">
                                {transactions.items.map((transaction) => (
                                    <div
                                        key={transaction.id}
                                        className="dashboard-tx__row"
                                        role="button"
                                        tabIndex={0}
                                        aria-label={`Editar ${transaction.description}`}
                                        onClick={() => setEditing(transaction)}
                                        onKeyDown={(event) => {
                                            if (event.target !== event.currentTarget) {
                                                return;
                                            }

                                            if (event.key === 'Enter' || event.key === ' ') {
                                                event.preventDefault();
                                                setEditing(transaction);
                                            }
                                        }}
                                    >
                                        <span
                                            className={transaction.category?.color ? 'dashboard-tile' : 'dashboard-tile dashboard-tile--plain'}
                                            style={transaction.category?.color ? { '--item-color': transaction.category.color } : undefined}
                                            aria-hidden="true"
                                        >
                                            <CatalogIcon name={transaction.category?.icon} />
                                        </span>
                                        <span className="dashboard-tx__copy">
                                            <span className="dashboard-tx__title">
                                                <span className="dashboard-cat__name">{transaction.description}</span>
                                                <ReferenceSeal label={transaction.competence_label} />
                                                {transaction.status === 'pending' ? <span className="invoice-chip">Pendente</span> : null}
                                            </span>
                                            <span className="dashboard-cat__meta">
                                                {transaction.category?.name}
                                                {transaction.type === 'reserve' ? (
                                                    <span className="invoice-chip invoice-chip--neutral">
                                                        {transaction.reserve_action === 'withdraw' ? 'Resgate' : 'Reserva'}
                                                    </span>
                                                ) : null}
                                                {transactionMeta(transaction) ? ` · ${transactionMeta(transaction)}` : ''}
                                            </span>
                                        </span>
                                        <span className="dashboard-tx__amount">
                                            {transaction.type === 'reserve'
                                                ? <span className="dashboard-tx__plain">{formatPlainMoney(transaction.amount)}</span>
                                                : transaction.status === 'pending'
                                                    ? <span className="dashboard-tx__plain">{formatMoney(transaction.signed_amount)}</span>
                                                    : <Money value={transaction.signed_amount} />}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Card>
                    <Card className="dashboard-span-5">
                        <div className="dashboard-head">
                            <h2>Patrimônio</h2>
                            <Link href="/net-worth">Ver patrimônio</Link>
                        </div>
                        {netWorth.empty ? (
                            <EmptyState
                                icon={Landmark}
                                message="Nenhum fechamento ainda."
                                shortcutLabel="Ir para Patrimônio"
                                shortcutHref="/net-worth"
                            />
                        ) : (
                            <>
                                <p className="dashboard-figure">{formatPlainMoney(netWorth.total)}</p>
                                {netWorth.variation !== null && netWorth.previous_name ? (
                                    <p className={`dashboard-note dashboard-note--${netWorth.variation_tone}`}>
                                        {netWorth.variation_tone === 'neutral'
                                            ? `Sem alteração em relação a ${netWorth.previous_name}`
                                            : `${formatMoney(netWorth.variation)} em relação a ${netWorth.previous_name}`}
                                    </p>
                                ) : null}
                                <EvolutionChart points={netWorth.chart.points} ariaLabel={netWorth.chart.aria_label} />
                            </>
                        )}
                    </Card>
                </div>

                <div className="dashboard-grid">
                    <Card className="dashboard-span-12">
                        <div className="dashboard-head">
                            <h2>Metas</h2>
                            <Link href={goals.shortcut_href}>Ver metas</Link>
                        </div>
                        {goals.items?.length ? (
                            <div className="dashboard-goals">
                                {goals.items.map((goal) => (
                                    <Link key={goal.id} href={goal.href} className="dashboard-goal">
                                        <h3 className="dashboard-goal__name">{goal.name}</h3>
                                        <span className="dashboard-goal__meta">
                                            {`${formatPlainMoney(goal.progress)} de ${formatPlainMoney(goal.target_amount)}`}
                                        </span>
                                        <UsageBar
                                            variant="meter"
                                            percent={goal.percent}
                                                complete={goal.reached}
                                            label={`Progresso de ${goal.name}`}
                                        />
                                        <span className="dashboard-goal__percent">{`${goal.percent}%`}</span>
                                    </Link>
                                ))}
                            </div>
                        ) : (
                            <EmptyState
                                icon={Target}
                                message={goals.message}
                                shortcutLabel={goals.shortcut_label}
                                shortcutHref={goals.shortcut_href}
                            />
                        )}
                    </Card>
                </div>
            </div>
            {creating ? (
                <TransactionModal
                    mode="create"
                    options={options}
                    onClose={() => setCreating(false)}
                />
            ) : null}
            {editing ? (
                <TransactionModal
                    mode="edit"
                    transaction={editing}
                    options={options}
                    onClose={() => setEditing(null)}
                />
            ) : null}
        </>
    );
}

Index.layout = (page) => <AppLayout>{page}</AppLayout>;
