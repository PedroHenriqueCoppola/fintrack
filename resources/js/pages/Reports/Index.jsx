import { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Calendar, ChevronDown, FileSpreadsheet } from 'lucide-react';
import Button from '../../components/Button/Button';
import Card from '../../components/Card/Card';
import EmptyState from '../../components/EmptyState/EmptyState';
import PageHeader from '../../components/PageHeader/PageHeader';
import AppLayout from '../../layouts/AppLayout/AppLayout';
import { formatDate } from '../../lib/format';
import PeriodPopover from '../Analysis/PeriodPopover';
import '../../components/DatePicker/DatePicker.css';
import '../../components/Money/Money.css';
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

const MODELS = [
    { key: 'spending', title: 'Gastos por categoria', text: 'Despesas pagas do período, por categoria.' },
    { key: 'summary', title: 'Resumo do período', text: 'Receitas, despesas, reservado e saldo, com o período anterior.' },
    { key: 'statement', title: 'Extrato de transações', text: 'Lançamentos pagos, do mais recente ao mais antigo.' },
    { key: 'monthly', title: 'Saldo mês a mês', text: 'Um mês por linha, inclusive os meses sem movimento.' },
];

const DATASETS = [
    { key: 'transactions', label: 'Transações' },
    { key: 'invoices', label: 'Faturas' },
    { key: 'net_worth', label: 'Patrimônio' },
    { key: 'goals', label: 'Metas' },
];

function toQuery(definition) {
    const params = {};
    const period = definition.period || 'current_month';

    if (period !== 'current_month') {
        params.period = period;
    }

    if (period === 'custom') {
        params.period = 'custom';
        params.from = definition.from;
        params.to = definition.to;
    }

    if (definition.mode === 'builder') {
        params.mode = 'builder';
        const dataset = definition.dataset || 'transactions';

        if (dataset !== 'transactions') {
            params.dataset = dataset;
        }

        if (dataset === 'transactions') {
            if (definition.type) params.type = definition.type;
            if (definition.category_id) params.category_id = definition.category_id;
            if (definition.bank_id) params.bank_id = definition.bank_id;
            if (definition.card_id) params.card_id = definition.card_id;
            if (definition.payment_method_id) params.payment_method_id = definition.payment_method_id;
            if (definition.status && definition.status !== 'paid') params.status = definition.status;
            if (definition.group_by && definition.group_by !== 'none') params.group_by = definition.group_by;
        } else if (dataset === 'invoices' && definition.card_id) {
            params.card_id = definition.card_id;
        } else if (dataset === 'goals' && definition.goal_status && definition.goal_status !== 'all') {
            params.goal_status = definition.goal_status;
        }
    } else if (definition.preset && definition.preset !== 'spending') {
        params.preset = definition.preset;
    }

    return params;
}

function allCategories(options) {
    const groups = options?.categories ?? {};
    const seen = new Set();

    return [...(groups.expense ?? []), ...(groups.income ?? []), ...(groups.reserve ?? [])].filter((item) => {
        if (seen.has(item.id)) {
            return false;
        }

        seen.add(item.id);

        return true;
    });
}

function errorText(errors, key) {
    const value = errors?.[key];

    if (!value) {
        return '';
    }

    return Array.isArray(value) ? value[0] : value;
}

function errorMessages(errors) {
    return Object.values(errors ?? {}).flatMap((value) => (Array.isArray(value) ? value : [value])).filter(Boolean);
}

function isZero(raw) {
    const unsigned = raw.startsWith('-') ? raw.slice(1) : raw;
    const [whole, fraction = '00'] = unsigned.split('.');
    const cents = `${fraction}00`.slice(0, 2);

    return (whole === '0' || whole === '') && cents === '00';
}

function formatReportMoney(value, tone) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const raw = String(value).trim();
    const negative = raw.startsWith('-');
    const unsigned = negative ? raw.slice(1) : raw;
    const [whole, fraction = '00'] = unsigned.split('.');
    const cents = `${fraction}00`.slice(0, 2);
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    const body = `R$ ${grouped},${cents}`;

    if (isZero(raw)) {
        return 'R$ 0,00';
    }

    if (tone === 'negative') {
        return `−${body}`;
    }

    if (tone === 'signed' || tone === 'positive') {
        return `${negative ? '−' : '+'}${body}`;
    }

    return negative ? `−${body}` : body;
}

function moneyClass(value, tone) {
    if (value === null || value === undefined || value === '') {
        return 'money';
    }

    const raw = String(value).trim();

    if (isZero(raw)) {
        return 'money';
    }

    if (tone === 'negative' || raw.startsWith('-')) {
        return 'money money--negative';
    }

    if (tone === 'signed' || tone === 'positive') {
        return 'money money--positive';
    }

    return 'money';
}

function formatPercent(value, signed) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const raw = String(value).trim();
    const negative = raw.startsWith('-');
    const unsigned = negative ? raw.slice(1) : raw;
    const [whole, frac = ''] = unsigned.split('.');

    if (!/^\d+$/.test(whole)) {
        return '—';
    }

    const padded = `${frac}00000000`.slice(0, 8);
    const head = `${whole}${padded.slice(0, 3)}`.replace(/^0+(?=\d)/, '') || '0';
    let milli = BigInt(head);

    if (padded[3] >= '5') {
        milli += 1n;
    }

    const text = `${milli / 10n},${milli % 10n}%`;

    if (milli === 0n) {
        return '0,0%';
    }

    if (negative) {
        return `−${text}`;
    }

    if (signed) {
        return `+${text}`;
    }

    return text;
}

function formatReportDate(value) {
    if (!value) {
        return '—';
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value));

    return match ? `${match[3]}/${match[2]}/${match[1]}` : String(value);
}

function barWidth(value) {
    const match = /^(\d+)(?:\.(\d+))?$/.exec(String(value ?? ''));

    if (!match) {
        return '0%';
    }

    if (match[1] !== '0') {
        return '100%';
    }

    const hundredths = (match[2] ?? '').padEnd(2, '0').slice(0, 2);
    const digits = hundredths.replace(/^0+/, '');

    return `${digits || '0'}%`;
}

function cellText(value, column) {
    if (column.type === 'money') {
        return formatReportMoney(value, column.tone || (column.signed ? 'signed' : 'plain'));
    }

    if (column.type === 'percent') {
        return formatPercent(value, Boolean(column.signed));
    }

    if (column.type === 'date') {
        return formatReportDate(value);
    }

    if (column.type === 'integer') {
        return value === null || value === undefined || value === '' ? '—' : String(value);
    }

    return value === null || value === undefined || value === '' ? '—' : String(value);
}

function SelectField({ label, value, onChange, children, error }) {
    return (
        <label className="reports-field">
            <span>{label}</span>
            <div className="reports-select">
                <select value={value ?? ''} onChange={(event) => onChange(event.target.value)} aria-invalid={error ? 'true' : undefined}>
                    {children}
                </select>
                <ChevronDown aria-hidden="true" strokeWidth={1.75} />
            </div>
            {error ? <small>{error}</small> : null}
        </label>
    );
}

export default function Index({ definition, period, report, options, limits }) {
    const pageErrors = usePage().props.errors ?? {};
    const [draft, setDraft] = useState(definition);
    const [loading, setLoading] = useState(false);
    const [calendarOpen, setCalendarOpen] = useState(false);
    const dirty = useRef(false);
    const requestId = useRef(0);
    const timer = useRef(null);
    const messages = errorMessages(pageErrors);
    const activePeriod = calendarOpen ? 'custom' : (draft.period || 'current_month');
    const rangeFrom = draft.period === 'custom' ? (draft.from || period.from) : period.from;
    const rangeTo = draft.period === 'custom' ? (draft.to || period.to) : period.to;
    const pdfBlocked = report.empty || report.row_count > limits.pdf || loading;
    const xlsxBlocked = report.empty || report.row_count > limits.xlsx || loading;
    const pdfReason = report.empty
        ? 'Nenhum dado neste período.'
        : (report.row_count > limits.pdf ? limits.pdf_message : (loading ? 'A prévia está atualizando.' : 'Baixar PDF'));
    const xlsxReason = report.empty
        ? 'Nenhum dado neste período.'
        : (report.row_count > limits.xlsx ? limits.xlsx_message : (loading ? 'A prévia está atualizando.' : 'Baixar Excel'));
    const limitMessages = [
        !report.empty && report.row_count > limits.pdf ? limits.pdf_message : '',
        !report.empty && report.row_count > limits.xlsx ? limits.xlsx_message : '',
    ].filter(Boolean);

    useEffect(() => {
        if (!dirty.current) {
            setDraft(definition);
        }
    }, [definition]);

    useEffect(() => () => clearTimeout(timer.current), []);

    function schedule(next) {
        dirty.current = true;
        clearTimeout(timer.current);
        timer.current = setTimeout(() => {
            const id = requestId.current + 1;
            requestId.current = id;
            setLoading(true);
            router.cancelAll();
            router.get('/reports', toQuery(next), {
                only: ['definition', 'period', 'report'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onSuccess: () => {
                    if (id === requestId.current) {
                        dirty.current = false;
                    }
                },
                onFinish: () => {
                    if (id === requestId.current) {
                        setLoading(false);
                    }
                },
            });
        }, 300);
    }

    function commit(next) {
        setDraft(next);
        schedule(next);
    }

    function choosePeriod(key) {
        if (key === 'custom') {
            setCalendarOpen(true);
            return;
        }

        setCalendarOpen(false);
        commit({ ...draft, period: key, from: null, to: null });
    }

    function setField(key, value) {
        setDraft((current) => {
            const next = { ...current, [key]: value === '' ? null : value };
            schedule(next);

            return next;
        });
    }

    const downloadQuery = new URLSearchParams(toQuery(definition)).toString();

    function downloadHref(format) {
        const query = downloadQuery ? `${downloadQuery}&format=${format}` : `format=${format}`;

        return `/reports/download?${query}`;
    }

    return (
        <>
            <Head title="Relatórios" />
            <div className="page reports-page">
                <PageHeader
                    title="Relatórios"
                    subtitle="Escolha um modelo ou monte o seu, e baixe em PDF ou Excel."
                />
                <div className="reports-tools">
                    {calendarOpen ? (
                        <button
                            type="button"
                            className="popover-backdrop"
                            aria-label="Fechar calendário"
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
                            {rangeFrom && rangeTo ? (
                                <>
                                    <span>{formatDate(rangeFrom)}</span>
                                    <i>–</i>
                                    <span>{formatDate(rangeTo)}</span>
                                </>
                            ) : (
                                <span>Todo o período</span>
                            )}
                        </button>
                        <PeriodPopover
                            open={calendarOpen}
                            initialFrom={rangeFrom}
                            initialTo={rangeTo}
                            onClose={() => setCalendarOpen(false)}
                            onApply={(from, to) => {
                                setCalendarOpen(false);
                                commit({ ...draft, period: 'custom', from, to });
                            }}
                        />
                    </div>
                </div>
                {messages.length > 0 ? (
                    <div className="reports-alert" role="alert">
                        {messages.map((message) => <p key={message}>{message}</p>)}
                    </div>
                ) : null}
                <div className="reports-layout">
                    <section className="reports-setup" aria-label="Definição do relatório">
                        <div className="period-seg" role="group" aria-label="Tipo de relatório">
                            <button
                                type="button"
                                aria-pressed={draft.mode !== 'builder'}
                                onClick={() => commit({ ...draft, mode: 'presets' })}
                            >
                                Modelos prontos
                            </button>
                            <button
                                type="button"
                                aria-pressed={draft.mode === 'builder'}
                                onClick={() => commit({ ...draft, mode: 'builder' })}
                            >
                                Montar o meu
                            </button>
                        </div>
                        {draft.mode === 'builder' ? (
                            <div className="reports-form">
                                <p className="reports-label">O que incluir</p>
                                <div className="period-seg reports-datasets" role="group" aria-label="O que incluir">
                                    {DATASETS.map((item) => (
                                        <button
                                            key={item.key}
                                            type="button"
                                            aria-pressed={(draft.dataset || 'transactions') === item.key}
                                            onClick={() => setField('dataset', item.key)}
                                        >
                                            {item.label}
                                        </button>
                                    ))}
                                </div>
                                {(draft.dataset || 'transactions') === 'transactions' ? (
                                    <div className="reports-fields">
                                        <SelectField label="Tipo" value={draft.type ?? ''} error={errorText(pageErrors, 'type')} onChange={(value) => setField('type', value)}>
                                            <option value="">Todos</option>
                                            <option value="income">Receita</option>
                                            <option value="expense">Despesa</option>
                                            <option value="reserve">Reserva</option>
                                        </SelectField>
                                        <SelectField label="Categoria" value={draft.category_id ?? ''} error={errorText(pageErrors, 'category_id')} onChange={(value) => setField('category_id', value)}>
                                            <option value="">Todas</option>
                                            {allCategories(options).map((item) => (
                                                <option key={item.id} value={item.id}>{item.name}</option>
                                            ))}
                                        </SelectField>
                                        <SelectField label="Banco" value={draft.bank_id ?? ''} error={errorText(pageErrors, 'bank_id')} onChange={(value) => setField('bank_id', value)}>
                                            <option value="">Todos</option>
                                            {(options.banks ?? []).map((item) => (
                                                <option key={item.id} value={item.id}>{item.name}</option>
                                            ))}
                                        </SelectField>
                                        <SelectField label="Cartão" value={draft.card_id ?? ''} error={errorText(pageErrors, 'card_id')} onChange={(value) => setField('card_id', value)}>
                                            <option value="">Todos</option>
                                            {(options.cards ?? []).map((item) => (
                                                <option key={item.id} value={item.id}>{item.name}</option>
                                            ))}
                                        </SelectField>
                                        <SelectField label="Forma de pagamento" value={draft.payment_method_id ?? ''} error={errorText(pageErrors, 'payment_method_id')} onChange={(value) => setField('payment_method_id', value)}>
                                            <option value="">Todas</option>
                                            {(options.paymentMethods ?? []).map((item) => (
                                                <option key={item.id} value={item.id}>{item.name}</option>
                                            ))}
                                        </SelectField>
                                        <SelectField label="Situação" value={draft.status ?? 'paid'} error={errorText(pageErrors, 'status')} onChange={(value) => setField('status', value)}>
                                            <option value="paid">Paga</option>
                                            <option value="pending">Pendente</option>
                                            <option value="all">Todas</option>
                                        </SelectField>
                                        <SelectField label="Agrupar por" value={draft.group_by ?? 'none'} error={errorText(pageErrors, 'group_by')} onChange={(value) => setField('group_by', value)}>
                                            <option value="none">Sem agrupamento</option>
                                            <option value="day">Dia</option>
                                            <option value="week">Semana</option>
                                            <option value="month">Mês</option>
                                            <option value="category">Categoria</option>
                                            <option value="bank">Banco</option>
                                        </SelectField>
                                    </div>
                                ) : null}
                                {draft.dataset === 'invoices' ? (
                                    <div className="reports-fields">
                                        <SelectField label="Cartão" value={draft.card_id ?? ''} error={errorText(pageErrors, 'card_id')} onChange={(value) => setField('card_id', value)}>
                                            <option value="">Todos</option>
                                            {(options.cards ?? []).map((item) => (
                                                <option key={item.id} value={item.id}>{item.name}</option>
                                            ))}
                                        </SelectField>
                                    </div>
                                ) : null}
                                {draft.dataset === 'goals' ? (
                                    <div className="reports-fields">
                                        <SelectField label="Situação" value={draft.goal_status ?? 'all'} error={errorText(pageErrors, 'goal_status')} onChange={(value) => setField('goal_status', value)}>
                                            <option value="all">Todas</option>
                                            <option value="active">Ativas</option>
                                            <option value="completed">Concluídas</option>
                                            <option value="archived">Arquivadas</option>
                                        </SelectField>
                                    </div>
                                ) : null}
                                {draft.dataset === 'net_worth' ? (
                                    <p className="reports-hint">O patrimônio usa só o período, sem filtros.</p>
                                ) : null}
                            </div>
                        ) : (
                            <div className="model-grid">
                                {MODELS.map((model) => (
                                    <button
                                        key={model.key}
                                        type="button"
                                        className="model-card"
                                        aria-pressed={(draft.preset || 'spending') === model.key}
                                        onClick={() => commit({ ...draft, mode: 'presets', preset: model.key })}
                                    >
                                        <strong>{model.title}</strong>
                                        <span>{model.text}</span>
                                    </button>
                                ))}
                            </div>
                        )}
                    </section>
                    <section className="reports-preview" aria-label="Prévia do relatório">
                        <div className="reports-downloads">
                            {pdfBlocked ? (
                                <Button variant="secondary" disabled title={pdfReason}>Baixar PDF</Button>
                            ) : (
                                <a className="button button--secondary" href={downloadHref('pdf')}>Baixar PDF</a>
                            )}
                            {xlsxBlocked ? (
                                <Button variant="secondary" disabled title={xlsxReason}>Baixar Excel</Button>
                            ) : (
                                <a className="button button--secondary" href={downloadHref('xlsx')}>Baixar Excel</a>
                            )}
                        </div>
                        <p className="reports-live" aria-live="polite">
                            {loading ? 'Atualizando a prévia…' : (report.shown_count < report.row_count ? `Mostrando ${report.shown_count} de ${report.row_count}` : '')}
                        </p>
                        {limitMessages.map((message) => (
                            <p key={message} className="reports-limit">{message}</p>
                        ))}
                        <Card>
                            {report.empty ? (
                                <EmptyState icon={FileSpreadsheet} message="Nenhum dado neste período." />
                            ) : (
                                <div className={loading ? 'reports-sheet reports-sheet--loading' : 'reports-sheet'}>
                                    <header className="reports-sheet__head">
                                        <h2>{report.title}</h2>
                                        <p>{report.period_label}</p>
                                        <p>Filtros: {report.filters_summary}</p>
                                        {report.note ? <p>{report.note}</p> : null}
                                    </header>
                                    {report.sections.map((section, index) => (
                                        <div key={section.title || `section-${index}`} className="reports-section">
                                            {section.title ? <h3>{section.title}</h3> : null}
                                            <div
                                                className="reports-scroll"
                                                tabIndex={0}
                                                role="region"
                                                aria-label={section.title || report.title}
                                            >
                                                <table>
                                                    <thead>
                                                        <tr>
                                                            {section.columns.map((column) => (
                                                                <th
                                                                    key={column.key}
                                                                    scope="col"
                                                                    className={column.type === 'text' || column.type === 'status' ? '' : 'num'}
                                                                >
                                                                    {column.label}
                                                                </th>
                                                            ))}
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {section.rows.map((row, rowIndex) => (
                                                            <tr key={`${section.title || index}-${rowIndex}`}>
                                                                {section.columns.map((column) => (
                                                                    <td
                                                                        key={column.key}
                                                                        className={column.type === 'text' || column.type === 'status' ? '' : 'num'}
                                                                    >
                                                                        {column.type === 'money' ? (
                                                                            <span className={moneyClass(row[column.key], column.tone || (column.signed ? 'signed' : 'plain'))}>
                                                                                {cellText(row[column.key], column)}
                                                                            </span>
                                                                        ) : section.bar_column === column.key ? (
                                                                            <span className="reports-share">
                                                                                <span className="reports-share__track" aria-hidden="true">
                                                                                    <span style={{ width: barWidth(row[column.key]) }} />
                                                                                </span>
                                                                                {cellText(row[column.key], column)}
                                                                            </span>
                                                                        ) : cellText(row[column.key], column)}
                                                                    </td>
                                                                ))}
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                    {section.totals ? (
                                                        <tfoot>
                                                            <tr>
                                                                {section.columns.map((column, columnIndex) => (
                                                                    <th
                                                                        key={column.key}
                                                                        scope={columnIndex === 0 ? 'row' : 'col'}
                                                                        className={column.type === 'text' || column.type === 'status' ? '' : 'num'}
                                                                    >
                                                                        {column.type === 'money' ? (
                                                                            <span className={moneyClass(section.totals[column.key], column.tone || (column.signed ? 'signed' : 'plain'))}>
                                                                                {cellText(section.totals[column.key], column)}
                                                                            </span>
                                                                        ) : cellText(section.totals[column.key], column)}
                                                                    </th>
                                                                ))}
                                                            </tr>
                                                        </tfoot>
                                                    ) : null}
                                                </table>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </Card>
                    </section>
                </div>
            </div>
        </>
    );
}

Index.layout = (page) => <AppLayout>{page}</AppLayout>;
