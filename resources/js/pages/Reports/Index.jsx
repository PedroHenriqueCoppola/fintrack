import { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    Calendar,
    CalendarRange,
    CreditCard,
    FileDown,
    FileSpreadsheet,
    Landmark,
    ListOrdered,
    PieChart,
    Scale,
    Target,
} from 'lucide-react';
import Button from '../../components/Button/Button';
import Card from '../../components/Card/Card';
import EmptyState from '../../components/EmptyState/EmptyState';
import PageHeader from '../../components/PageHeader/PageHeader';
import Select from '../../components/Select/Select';
import Tabs from '../../components/Tabs/Tabs';
import AppLayout from '../../layouts/AppLayout/AppLayout';
import { bankOption, cardOption, cardsForBank, categoryOption, namedOption } from '../../lib/catalogOptions';
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
    { key: 'spending', title: 'Gastos por categoria', text: 'Despesas pagas por categoria, com % do total', icon: PieChart },
    { key: 'summary', title: 'Resumo do período', text: 'Receitas, despesas, reservado e saldo, com o período anterior', icon: Scale },
    { key: 'statement', title: 'Extrato de transações', text: 'Transações pagas, da mais recente à mais antiga', icon: ListOrdered },
    { key: 'monthly', title: 'Saldo mês a mês', text: 'Receitas, despesas e saldo de cada mês', icon: CalendarRange },
];

const DATASETS = [
    { key: 'transactions', label: 'Transações', icon: ArrowLeftRight },
    { key: 'invoices', label: 'Faturas', icon: CreditCard },
    { key: 'net_worth', label: 'Patrimônio', icon: Landmark },
    { key: 'goals', label: 'Metas', icon: Target },
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

function numeric(type) {
    return type === 'money' || type === 'percent' || type === 'integer';
}

function ShareCell({ cell }) {
    return (
        <span className="reports-share">
            <span className="reports-share__track" aria-hidden="true">
                <span style={{ width: cell.bar }} />
            </span>
            <span className="reports-share__value">{cell.text}</span>
        </span>
    );
}

function cellClass(column, cell) {
    const classes = [];

    if (numeric(column.type)) {
        classes.push('num');
    }

    if (cell?.tone && cell.tone !== 'neutral') {
        classes.push(`tone-${cell.tone}`);
    }

    return classes.join(' ');
}

function SelectField({ label, value, onChange, error, options, emptyOption }) {
    return (
        <label className="reports-field">
            <span>{label}</span>
            <Select
                value={value ?? ''}
                onChange={onChange}
                options={options}
                emptyOption={emptyOption}
                placeholder={emptyOption || 'Escolha'}
                aria-invalid={error ? 'true' : undefined}
            />
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
    const draftRef = useRef(definition);
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
    const filters = report.filters ?? [];

    useEffect(() => {
        if (!dirty.current) {
            draftRef.current = definition;
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
        draftRef.current = next;
        setDraft(next);
        schedule(next);
    }

    function choosePeriod(key) {
        if (key === 'custom') {
            setCalendarOpen(true);
            return;
        }

        setCalendarOpen(false);
        commit({ ...draftRef.current, period: key, from: null, to: null });
    }

    function setField(key, value) {
        const next = { ...draftRef.current, [key]: value === '' ? null : value };

        const onTransactions = (draftRef.current.dataset || 'transactions') === 'transactions';

        if (onTransactions && key === 'bank_id') {
            const card = (options.cards ?? []).find((item) => String(item.id) === String(next.card_id));

            if (value && card && String(card.bank_id) !== String(value)) {
                next.card_id = null;
            }
        }

        if (onTransactions && key === 'card_id' && value) {
            const card = (options.cards ?? []).find((item) => String(item.id) === String(value));

            if (card?.bank_id) {
                next.bank_id = String(card.bank_id);
            }
        }

        draftRef.current = next;
        setDraft(next);
        schedule(next);
    }

    const downloadQuery = new URLSearchParams(toQuery(definition)).toString();

    function downloadHref(format) {
        const query = downloadQuery ? `${downloadQuery}&format=${format}` : `format=${format}`;

        return `/reports/download?${query}`;
    }

    function downloadButton(format, blocked, reason, variant, Icon, label) {
        if (blocked) {
            return (
                <Button variant={variant} disabled title={reason}>
                    <Icon aria-hidden="true" strokeWidth={1.75} />
                    {label}
                </Button>
            );
        }

        return (
            <a className={`button button--${variant}`} href={downloadHref(format)}>
                <Icon aria-hidden="true" strokeWidth={1.75} />
                {label}
            </a>
        );
    }

    const presetPanel = (
        <div className="model-list">
            {MODELS.map((model) => {
                const Icon = model.icon;

                return (
                    <button
                        key={model.key}
                        type="button"
                        className="model-card"
                        aria-pressed={(draft.preset || 'spending') === model.key}
                        onClick={() => commit({ ...draftRef.current, mode: 'presets', preset: model.key })}
                    >
                        <Icon aria-hidden="true" strokeWidth={1.75} />
                        <span>
                            <strong>{model.title}</strong>
                            <small>{model.text}</small>
                        </span>
                    </button>
                );
            })}
        </div>
    );

    const builderPanel = (
        <div className="reports-form">
            <p className="reports-label">O que incluir</p>
            <div className="dataset-grid" role="group" aria-label="O que incluir">
                {DATASETS.map((item) => {
                    const Icon = item.icon;

                    return (
                        <button
                            key={item.key}
                            type="button"
                            className="dataset-card"
                            aria-pressed={(draft.dataset || 'transactions') === item.key}
                            onClick={() => setField('dataset', item.key)}
                        >
                            <Icon aria-hidden="true" strokeWidth={1.75} />
                            <span>{item.label}</span>
                        </button>
                    );
                })}
            </div>
            {(draft.dataset || 'transactions') === 'transactions' ? (
                <div className="reports-fields">
                    <SelectField label="Tipo" value={draft.type ?? ''} error={errorText(pageErrors, 'type')} emptyOption="Todos" options={[
                        { value: 'income', label: 'Receita' },
                        { value: 'expense', label: 'Despesa' },
                        { value: 'reserve', label: 'Reserva' },
                    ]} onChange={(value) => setField('type', value)} />
                    <SelectField label="Categoria" value={draft.category_id ?? ''} error={errorText(pageErrors, 'category_id')} emptyOption="Todas" options={allCategories(options).map((item) => categoryOption(item))} onChange={(value) => setField('category_id', value)} />
                    <SelectField label="Banco" value={draft.bank_id ?? ''} error={errorText(pageErrors, 'bank_id')} emptyOption="Todos" options={(options.banks ?? []).map((item) => bankOption(item))} onChange={(value) => setField('bank_id', value)} />
                    <SelectField label="Cartão" value={draft.card_id ?? ''} error={errorText(pageErrors, 'card_id')} emptyOption="Todos" options={cardsForBank(options.cards, draft.bank_id).map((item) => cardOption(item, options.banks))} onChange={(value) => setField('card_id', value)} />
                    <SelectField label="Forma de pagamento" value={draft.payment_method_id ?? ''} error={errorText(pageErrors, 'payment_method_id')} emptyOption="Todas" options={(options.paymentMethods ?? []).map((item) => namedOption(item))} onChange={(value) => setField('payment_method_id', value)} />
                    <SelectField label="Situação" value={draft.status ?? 'paid'} error={errorText(pageErrors, 'status')} options={[
                        { value: 'paid', label: 'Paga' },
                        { value: 'pending', label: 'Pendente' },
                        { value: 'all', label: 'Todas' },
                    ]} onChange={(value) => setField('status', value)} />
                    <SelectField label="Agrupar por" value={draft.group_by ?? 'none'} error={errorText(pageErrors, 'group_by')} options={[
                        { value: 'none', label: 'Sem agrupamento' },
                        { value: 'day', label: 'Dia' },
                        { value: 'week', label: 'Semana' },
                        { value: 'month', label: 'Mês' },
                        { value: 'category', label: 'Categoria' },
                        { value: 'bank', label: 'Banco' },
                    ]} onChange={(value) => setField('group_by', value)} />
                </div>
            ) : null}
            {draft.dataset === 'invoices' ? (
                <div className="reports-fields">
                    <SelectField label="Cartão" value={draft.card_id ?? ''} error={errorText(pageErrors, 'card_id')} emptyOption="Todos" options={(options.cards ?? []).map((item) => cardOption(item, options.banks))} onChange={(value) => setField('card_id', value)} />
                </div>
            ) : null}
            {draft.dataset === 'goals' ? (
                <div className="reports-fields">
                    <SelectField label="Situação" value={draft.goal_status ?? 'all'} error={errorText(pageErrors, 'goal_status')} options={[
                        { value: 'all', label: 'Todas' },
                        { value: 'active', label: 'Ativas' },
                        { value: 'completed', label: 'Concluídas' },
                        { value: 'archived', label: 'Arquivadas' },
                    ]} onChange={(value) => setField('goal_status', value)} />
                </div>
            ) : null}
            {draft.dataset === 'net_worth' ? (
                <p className="reports-hint">O patrimônio usa só o período, sem filtros.</p>
            ) : null}
        </div>
    );

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
                                commit({ ...draftRef.current, period: 'custom', from, to });
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
                    <Card title="Configurar" className="reports-config">
                        <Tabs
                            label="Tipo de relatório"
                            value={draft.mode === 'builder' ? 'builder' : 'presets'}
                            onChange={(id) => commit({ ...draftRef.current, mode: id })}
                            tabs={[
                                { id: 'presets', label: 'Modelos prontos', panel: presetPanel },
                                { id: 'builder', label: 'Montar o meu', panel: builderPanel },
                            ]}
                        />
                    </Card>
                    <section className="reports-preview" aria-label="Prévia do relatório">
                        {limitMessages.map((message) => (
                            <p key={message} className="reports-limit">{message}</p>
                        ))}
                        <Card className="reports-doc">
                            {report.empty ? (
                                <>
                                    <header className="reports-doc__head">
                                        <div>
                                            <h2 className="reports-doc__title">{report.title}</h2>
                                            <p className="reports-doc__period">{report.period_detail}</p>
                                            <p className="reports-live" aria-live="polite">{loading ? 'Atualizando a prévia…' : ''}</p>
                                        </div>
                                        <div className="reports-downloads">
                                            {downloadButton('pdf', true, pdfReason, 'primary', FileDown, 'Baixar PDF')}
                                            {downloadButton('xlsx', true, xlsxReason, 'secondary', FileSpreadsheet, 'Baixar Excel')}
                                        </div>
                                    </header>
                                    <EmptyState icon={FileSpreadsheet} message="Nenhum dado neste período." />
                                </>
                            ) : (
                                <div className={loading ? 'reports-sheet reports-sheet--loading' : 'reports-sheet'}>
                                    <header className="reports-doc__head">
                                        <div>
                                            <h2 className="reports-doc__title">{report.title}</h2>
                                            <p className="reports-doc__period">{report.period_detail}</p>
                                            {filters.length > 0 ? (
                                                <p className="reports-chips">
                                                    {filters.map((filter) => (
                                                        <span key={`${filter.label}-${filter.value}`} className="reports-chip">
                                                            {filter.label}: {filter.value}
                                                        </span>
                                                    ))}
                                                </p>
                                            ) : null}
                                            {report.note ? <p className="reports-note">{report.note}</p> : null}
                                            <p className="reports-live" aria-live="polite">{loading ? 'Atualizando a prévia…' : ''}</p>
                                        </div>
                                        <div className="reports-downloads">
                                            {downloadButton('pdf', pdfBlocked, pdfReason, 'primary', FileDown, 'Baixar PDF')}
                                            {downloadButton('xlsx', xlsxBlocked, xlsxReason, 'secondary', FileSpreadsheet, 'Baixar Excel')}
                                        </div>
                                    </header>
                                    {report.sections.map((section, index) => (
                                        <div key={`${section.kind || 'table'}-${section.title || index}`} className="reports-section">
                                            {section.title ? <h3>{section.title}</h3> : null}
                                            {section.kind === 'kpis' ? (
                                                <div className="reports-kpis">
                                                    {section.items.map((item) => (
                                                        <div key={item.key} className="reports-kpi">
                                                            <span>{item.label}</span>
                                                            <strong className={item.tone && item.tone !== 'neutral' ? `tone-${item.tone}` : ''}>{item.text}</strong>
                                                        </div>
                                                    ))}
                                                </div>
                                            ) : (
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
                                                                    <th key={column.key} scope="col" className={numeric(column.type) ? 'num' : ''}>
                                                                        {column.label}
                                                                    </th>
                                                                ))}
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            {section.rows.map((row, rowIndex) => (
                                                                <tr key={`${section.title || index}-${rowIndex}`}>
                                                                    {section.columns.map((column) => {
                                                                        const cell = row[column.key];

                                                                        return (
                                                                            <td key={column.key} className={cellClass(column, cell)}>
                                                                                {cell.bar ? <ShareCell cell={cell} /> : cell.text}
                                                                            </td>
                                                                        );
                                                                    })}
                                                                </tr>
                                                            ))}
                                                        </tbody>
                                                        {section.totals ? (
                                                            <tfoot>
                                                                <tr>
                                                                    {section.columns.map((column, columnIndex) => {
                                                                        const cell = section.totals[column.key];

                                                                        if (columnIndex === 0) {
                                                                            return <th key={column.key} scope="row">{cell.text}</th>;
                                                                        }

                                                                        return (
                                                                            <td key={column.key} className={cellClass(column, cell)}>
                                                                                {cell.bar ? <ShareCell cell={cell} /> : cell.text}
                                                                            </td>
                                                                        );
                                                                    })}
                                                                </tr>
                                                            </tfoot>
                                                        ) : null}
                                                    </table>
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                    {report.shown_count < report.row_count ? (
                                        <p className="reports-count">Mostrando {report.shown_count} de {report.row_count}</p>
                                    ) : null}
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
