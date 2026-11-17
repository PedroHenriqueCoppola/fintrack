import { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Calendar, ChevronDown, Receipt, Search, X } from 'lucide-react';
import Button from '../../components/Button/Button';
import ConfirmDialog from '../../components/ConfirmDialog/ConfirmDialog';
import Card from '../../components/Card/Card';
import EmptyState from '../../components/EmptyState/EmptyState';
import Money from '../../components/Money/Money';
import PageHeader from '../../components/PageHeader/PageHeader';
import TransactionModal, { NewTransactionButton } from '../../components/TransactionModal/TransactionModal';
import AppLayout from '../../layouts/AppLayout/AppLayout';
import { formatDate, formatPlainMoney } from '../../lib/format';
import MoreFilters from './MoreFilters';
import PeriodPopover from './PeriodPopover';
import SaveFilterModal from './SaveFilterModal';
import TransactionList from './TransactionList';
import { visitAnalysis } from './visit';
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

const SORT_LABELS = {
    date_asc: 'Data, mais antigas',
    amount_desc: 'Valor, maior',
    amount_asc: 'Valor, menor',
};

function nameOf(list, id) {
    return list.find((item) => String(item.id) === String(id))?.name ?? 'Selecionado';
}

function categoriesOf(options) {
    return [...(options.categories?.expense ?? []), ...(options.categories?.income ?? [])];
}

function buildChips(filters, options) {
    const chips = [];

    if (filters.q) chips.push({ key: 'q', label: `Busca: ${filters.q}` });
    if (filters.category_id) chips.push({ key: 'category_id', label: `Categoria: ${nameOf(categoriesOf(options), filters.category_id)}` });
    if (filters.bank_id) chips.push({ key: 'bank_id', label: `Banco: ${nameOf(options.banks ?? [], filters.bank_id)}` });
    if (filters.payment_method_id) chips.push({ key: 'payment_method_id', label: `Pagamento: ${nameOf(options.paymentMethods ?? [], filters.payment_method_id)}` });
    if (filters.card_id) chips.push({ key: 'card_id', label: `Cartão: ${nameOf(options.cards ?? [], filters.card_id)}` });
    if (filters.type) chips.push({ key: 'type', label: `Tipo: ${filters.type === 'income' ? 'Receita' : 'Despesa'}` });
    if (filters.status) chips.push({ key: 'status', label: `Status: ${filters.status === 'paid' ? 'Pagas' : 'Pendentes'}` });
    if (filters.min_amount) chips.push({ key: 'min_amount', label: `Mínimo: ${formatPlainMoney(filters.min_amount)}` });
    if (filters.max_amount) chips.push({ key: 'max_amount', label: `Máximo: ${formatPlainMoney(filters.max_amount)}` });
    if (filters.sort && filters.sort !== 'date_desc') chips.push({ key: 'sort', label: `Ordem: ${SORT_LABELS[filters.sort] ?? filters.sort}` });

    return chips;
}

function amountLabel(min, max) {
    if (min && max) return `${formatPlainMoney(min)} – ${formatPlainMoney(max)}`;
    if (min) return `Mín. ${formatPlainMoney(min)}`;
    if (max) return `Máx. ${formatPlainMoney(max)}`;

    return 'Qualquer';
}

function signedExpense(value) {
    if (!value || Number(value) === 0) return '0.00';

    return String(value).startsWith('-') ? value : `-${value}`;
}

function firstError(errors) {
    const value = Object.values(errors ?? {})[0];

    if (!value) return '';

    return Array.isArray(value) ? value[0] : value;
}

export default function Index({
    filters,
    period,
    summary,
    transactions,
    savedFilters,
    options,
}) {
    const pageErrors = usePage().props.errors;
    const [query, setQuery] = useState(filters.q ?? '');
    const skipQuery = useRef(false);
    const externalQuery = filters.q ?? '';
    const [calendarOpen, setCalendarOpen] = useState(false);
    const [moreOpen, setMoreOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [filterToDelete, setFilterToDelete] = useState(null);
    const [deletingFilter, setDeletingFilter] = useState(false);
    const [session, setSession] = useState(null);
    const [payingId, setPayingId] = useState(null);
    const [loadingMore, setLoadingMore] = useState(false);
    const chips = buildChips(filters, options);
    const rows = transactions?.data ?? [];
    const grouped = !String(filters.sort || '').startsWith('amount');
    const activePeriod = calendarOpen ? 'custom' : period.key;

    function currentQuery() {
        return {
            ...filters,
            period: period.key,
            from: period.from,
            to: period.to,
        };
    }

    useEffect(() => {
        skipQuery.current = true;
        setQuery(externalQuery);
    }, [externalQuery]);

    useEffect(() => {
        if (skipQuery.current || query === externalQuery) {
            return undefined;
        }

        const timer = setTimeout(() => {
            visitAnalysis({ ...currentQuery(), q: query.trim() }, { replace: true });
        }, 300);

        return () => clearTimeout(timer);
    }, [query]);

    function onSearch(value) {
        skipQuery.current = false;
        setQuery(value);
    }

    function choosePeriod(key) {
        setMoreOpen(false);

        if (key === 'custom') {
            setCalendarOpen(true);
            return;
        }

        setCalendarOpen(false);
        visitAnalysis({ ...currentQuery(), period: key, from: undefined, to: undefined });
    }

    function setFilter(key, value) {
        visitAnalysis({ ...currentQuery(), [key]: value });
    }

    function removeChip(key) {
        visitAnalysis({
            ...currentQuery(),
            [key]: key === 'sort' ? 'date_desc' : '',
        });
    }

    function clearFilters() {
        visitAnalysis({
            period: period.key,
            from: period.from,
            to: period.to,
        });
    }

    function pay(transaction) {
        router.post(`/transactions/${transaction.id}/pay`, {}, {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setPayingId(transaction.id),
            onFinish: () => setPayingId(null),
        });
    }

    const banner = !session && !saving ? firstError(pageErrors) : '';

    return (
        <>
            <Head title="Análise" />
            <div className="page analysis-page">
                <PageHeader
                    title="Análise"
                    subtitle="Todas as suas transações, agrupadas por dia."
                    actions={<NewTransactionButton onClick={() => setSession({ mode: 'create' })} />}
                />
                {banner ? <p className="analysis-alert" role="alert">{banner}</p> : null}
                <div className="analysis-tools">
                    {(calendarOpen || moreOpen) ? (
                        <button
                            type="button"
                            className="popover-backdrop"
                            aria-label="Fechar"
                            tabIndex={-1}
                            onClick={() => {
                                setCalendarOpen(false);
                                setMoreOpen(false);
                            }}
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
                        <button
                            type="button"
                            className="filter-toggle"
                            aria-expanded={moreOpen}
                            onClick={() => {
                                setCalendarOpen(false);
                                setMoreOpen((open) => !open);
                            }}
                        >
                            <Search aria-hidden="true" strokeWidth={1.75} />
                            Mais filtros
                        </button>
                        <button type="button" className="filter-toggle" onClick={() => setSaving(true)}>
                            Salvar filtro
                        </button>
                        <PeriodPopover
                            open={calendarOpen}
                            initialFrom={period.from}
                            initialTo={period.to}
                            onClose={() => setCalendarOpen(false)}
                            onApply={(from, to) => {
                                setCalendarOpen(false);
                                visitAnalysis({ ...currentQuery(), period: 'custom', from, to });
                            }}
                        />
                        <MoreFilters
                            open={moreOpen}
                            filters={filters}
                            cards={options.cards ?? []}
                            onClose={() => setMoreOpen(false)}
                            onApply={(partial) => {
                                setMoreOpen(false);
                                visitAnalysis({ ...currentQuery(), ...partial });
                            }}
                            onClear={() => {
                                setMoreOpen(false);
                                visitAnalysis({
                                    ...currentQuery(),
                                    type: '',
                                    status: '',
                                    min_amount: '',
                                    max_amount: '',
                                    card_id: '',
                                    sort: 'date_desc',
                                });
                            }}
                        />
                    </div>
                    <Card className="filter-card">
                        <div className="filter-row">
                            <label className="filter-field">
                                <span>Buscar</span>
                                <div className="filter-search">
                                    <Search aria-hidden="true" strokeWidth={1.75} />
                                    <input
                                        type="search"
                                        placeholder="Descrição ou observação"
                                        value={query}
                                        onChange={(event) => onSearch(event.target.value)}
                                    />
                                </div>
                            </label>
                            <label className="filter-field">
                                <span>Categoria</span>
                                <div className="tx-select">
                                    <select
                                        value={filters.category_id ?? ''}
                                        onChange={(event) => setFilter('category_id', event.target.value)}
                                    >
                                        <option value="">Todas</option>
                                        {categoriesOf(options).map((item) => (
                                            <option key={item.id} value={item.id}>{item.name}</option>
                                        ))}
                                    </select>
                                    <ChevronDown aria-hidden="true" strokeWidth={1.75} />
                                </div>
                            </label>
                            <label className="filter-field">
                                <span>Banco</span>
                                <div className="tx-select">
                                    <select
                                        value={filters.bank_id ?? ''}
                                        onChange={(event) => setFilter('bank_id', event.target.value)}
                                    >
                                        <option value="">Todos</option>
                                        {(options.banks ?? []).map((item) => (
                                            <option key={item.id} value={item.id}>{item.name}</option>
                                        ))}
                                    </select>
                                    <ChevronDown aria-hidden="true" strokeWidth={1.75} />
                                </div>
                            </label>
                            <label className="filter-field">
                                <span>Pagamento</span>
                                <div className="tx-select">
                                    <select
                                        value={filters.payment_method_id ?? ''}
                                        onChange={(event) => setFilter('payment_method_id', event.target.value)}
                                    >
                                        <option value="">Todas</option>
                                        {(options.paymentMethods ?? []).map((item) => (
                                            <option key={item.id} value={item.id}>{item.name}</option>
                                        ))}
                                    </select>
                                    <ChevronDown aria-hidden="true" strokeWidth={1.75} />
                                </div>
                            </label>
                            <label className="filter-field">
                                <span>Valor</span>
                                <button
                                    type="button"
                                    className="filter-value"
                                    aria-expanded={moreOpen}
                                    onClick={() => {
                                        setCalendarOpen(false);
                                        setMoreOpen(true);
                                    }}
                                >
                                    <span>{amountLabel(filters.min_amount, filters.max_amount)}</span>
                                    <ChevronDown aria-hidden="true" strokeWidth={1.75} />
                                </button>
                            </label>
                        </div>
                    </Card>
                </div>
                <div className="filter-chips">
                    {chips.map((chip) => (
                        <span key={chip.key} className="filter-chip">
                            {chip.label}
                            <button type="button" aria-label={`Remover filtro ${chip.label}`} onClick={() => removeChip(chip.key)}>
                                <X aria-hidden="true" strokeWidth={1.75} />
                            </button>
                        </span>
                    ))}
                    {chips.length > 0 ? (
                        <button type="button" className="filter-clear" onClick={clearFilters}>Limpar filtros</button>
                    ) : null}
                    <span className="filter-chips__spacer" />
                    {savedFilters.length > 0 ? <span className="filter-saved-label">Filtros salvos:</span> : null}
                    {savedFilters.map((item) => (
                        <span key={item.id} className="saved-chip">
                            <button type="button" className="saved-chip__apply" onClick={() => visitAnalysis(item.filters ?? {})}>
                                {item.name}
                            </button>
                            <button
                                type="button"
                                className="saved-chip__remove"
                                aria-label={`Apagar filtro salvo ${item.name}`}
                                onClick={() => setFilterToDelete(item)}
                            >
                                <X aria-hidden="true" strokeWidth={1.75} />
                            </button>
                        </span>
                    ))}
                </div>
                <div className="summary">
                    <Card className="summary-card">
                        <p className="summary-card__label">Transações</p>
                        <p className="summary-card__value">{summary.count}</p>
                    </Card>
                    <Card className="summary-card">
                        <p className="summary-card__label">Receitas</p>
                        <p className="summary-card__value"><Money value={summary.income} /></p>
                    </Card>
                    <Card className="summary-card">
                        <p className="summary-card__label">Despesas</p>
                        <p className="summary-card__value"><Money value={signedExpense(summary.expense)} /></p>
                    </Card>
                    <Card className="summary-card">
                        <p className="summary-card__label">Saldo</p>
                        <p className="summary-card__value"><Money value={summary.balance} /></p>
                    </Card>
                    <Card className="summary-card">
                        <p className="summary-card__label">Média por despesa</p>
                        <p className="summary-card__value">{formatPlainMoney(summary.average_expense)}</p>
                    </Card>
                </div>
                <Card>
                    {summary.count === 0 ? (
                        <EmptyState
                            icon={Receipt}
                            message={chips.length > 0 ? 'Nenhum resultado para esses filtros.' : 'Nenhuma transação neste período.'}
                            shortcutLabel={chips.length > 0 ? 'Limpar filtros' : 'Nova transação'}
                            onShortcut={chips.length > 0 ? clearFilters : () => setSession({ mode: 'create' })}
                        />
                    ) : (
                        <>
                            <TransactionList
                                transactions={rows}
                                grouped={grouped}
                                payingId={payingId}
                                onEdit={(transaction) => setSession({ mode: 'edit', transaction })}
                                onPay={pay}
                            />
                            {transactions.current_page < transactions.last_page ? (
                                <div className="tx-more-wrap">
                                    <Button variant="secondary" onClick={() => {
                                        setLoadingMore(true);
                                        visitAnalysis({
                                            ...currentQuery(),
                                            page: transactions.current_page + 1,
                                        }, {
                                            loadMore: true,
                                            onFinish: () => setLoadingMore(false),
                                        });
                                    }} disabled={loadingMore}>
                                        {loadingMore ? 'Carregando…' : 'Carregar mais'}
                                    </Button>
                                </div>
                            ) : null}
                        </>
                    )}
                </Card>
            </div>
            {session ? (
                <TransactionModal
                    key={`${session.mode}-${session.transaction?.id ?? 'new'}`}
                    mode={session.mode}
                    transaction={session.transaction}
                    options={options}
                    onClose={() => setSession(null)}
                />
            ) : null}
            <ConfirmDialog
                open={filterToDelete !== null}
                title="Apagar filtro salvo?"
                message={filterToDelete ? `${filterToDelete.name}. Essa ação não pode ser desfeita.` : ''}
                confirmLabel="Apagar"
                busy={deletingFilter}
                onCancel={() => setFilterToDelete(null)}
                onConfirm={() => {
                    if (!filterToDelete) {
                        return;
                    }

                    setDeletingFilter(true);
                    router.delete(`/saved-filters/${filterToDelete.id}`, {
                        preserveScroll: true,
                        preserveState: true,
                        only: ['savedFilters'],
                        onFinish: () => {
                            setDeletingFilter(false);
                            setFilterToDelete(null);
                        },
                    });
                }}
            />
            {saving ? (
                <SaveFilterModal
                    filters={filters}
                    period={period}
                    chips={chips}
                    onClose={() => setSaving(false)}
                />
            ) : null}
        </>
    );
}

Index.layout = (page) => <AppLayout>{page}</AppLayout>;
