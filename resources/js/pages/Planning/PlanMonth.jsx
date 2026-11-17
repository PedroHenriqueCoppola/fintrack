import { useEffect, useId, useRef, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Tags } from 'lucide-react';
import Button from '../../components/Button/Button';
import Card from '../../components/Card/Card';
import ConfirmDialog from '../../components/ConfirmDialog/ConfirmDialog';
import EmptyState from '../../components/EmptyState/EmptyState';
import FormField from '../../components/FormField/FormField';
import UsageBar from '../../components/UsageBar/UsageBar';
import { formatPlainMoney } from '../../lib/format';
import { useFieldSetter } from '../../lib/fields';
import CatalogIcon from '../Manage/icons';
import { moneyPayload, moneyText } from './fields';

function plain(value) {
    const negative = String(value ?? '').trim().startsWith('-');
    const text = formatPlainMoney(value);

    return negative ? `−${text}` : text;
}

function isZero(value) {
    return String(value ?? '').replace(/[0.]/g, '') === '';
}

function CategoryName({ row }) {
    return (
        <div className="plan-cat">
            <span
                className={row.color ? 'plan-tile' : 'plan-tile plan-tile--plain'}
                style={row.color ? { '--item-color': row.color } : undefined}
                aria-hidden="true"
            >
                <CatalogIcon name={row.icon} />
            </span>
            <span className="plan-cat__name">
                {row.name}
                {row.archived ? ' (arquivado)' : ''}
            </span>
        </div>
    );
}

function AmountField({ label, value, error, onChange, onBlur }) {
    const id = useId();
    const errorId = error ? `${id}-error` : undefined;

    return (
        <div className={error ? 'form-field form-field--error' : 'form-field'}>
            <label className="visually-hidden" htmlFor={id}>{label}</label>
            <div className="form-field__control">
                <input
                    id={id}
                    inputMode="decimal"
                    autoComplete="off"
                    aria-invalid={error ? 'true' : undefined}
                    aria-describedby={errorId}
                    value={value}
                    onChange={onChange}
                    onBlur={onBlur}
                />
            </div>
            {error ? <p className="form-field__error" id={errorId} role="alert">{error}</p> : null}
        </div>
    );
}

function MoneyText({ value, tone = 'text' }) {
    const negative = String(value ?? '').trim().startsWith('-');
    const className = negative ? 'plan-negative' : (tone === 'positive' ? 'plan-positive' : undefined);

    return <span className={className}>{plain(value)}</span>;
}

function buildForm(plan) {
    const rows = [...plan.spending, ...plan.reserves];

    return {
        expected_income: moneyText(plan.expected_income),
        amounts: Object.fromEntries(rows.map((row) => [row.category_id, moneyText(row.planned)])),
    };
}

export default function PlanMonth({ month, plan }) {
    const rows = [...plan.spending, ...plan.reserves];
    const signature = `${plan.expected_income}|${rows.map((row) => `${row.category_id}:${row.planned}`).join('|')}`;
    const seen = useRef(signature);
    const form = useForm(buildForm(plan));
    const setField = useFieldSetter(form);
    const [notice, setNotice] = useState('');
    const [confirmCopy, setConfirmCopy] = useState(false);
    const [copying, setCopying] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const [reopening, setReopening] = useState(false);

    useEffect(() => {
        if (seen.current === signature) {
            return;
        }

        seen.current = signature;
        form.setData(buildForm(plan));
        form.clearErrors();
        setNotice('Salvo');
    }, [signature]);

    function save(nextAmounts, nextIncome) {
        const payload = {
            expected_income: moneyPayload(nextIncome, '0'),
            items: rows.map((row) => ({
                category_id: row.category_id,
                amount: moneyPayload(nextAmounts[row.category_id], '0'),
            })),
        };

        form.transform(() => payload);
        form.put(`/planning/${month}`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setNotice('Salvo'),
            onError: () => setNotice(''),
        });
    }

    function blurIncome() {
        save(form.data.amounts, form.data.expected_income);
    }

    function blurAmount(categoryId) {
        save(form.data.amounts, form.data.expected_income);
    }

    function runCopy() {
        setCopying(true);
        router.post(`/planning/${month}/copy`, {}, {
            preserveScroll: true,
            onFinish: () => {
                setCopying(false);
                setConfirmCopy(false);
            },
        });
    }

    function askCopy() {
        if (plan.has_values) {
            setConfirmCopy(true);
            return;
        }

        runCopy();
    }

    if (rows.length === 0) {
        return (
            <Card className="card--fit">
                <EmptyState
                    icon={Tags}
                    message="Cadastre uma categoria de despesa para planejar o mês."
                    shortcutLabel="Ir para Gerenciar"
                    shortcutHref="/manage"
                />
            </Card>
        );
    }

    const summary = plan.summary;
    const confirmed = plan.status === 'confirmed';

    return (
        <>
            <div className="plan-layout">
                <Card>
                    <div className="plan-toolbar">
                        <div className="plan-toolbar__income">
                            <div className="plan-income">
                                <FormField label="Renda esperada" error={form.errors.expected_income}>
                                    <input
                                        inputMode="decimal"
                                        autoComplete="off"
                                        value={form.data.expected_income}
                                        onChange={(event) => {
                                            setNotice('');
                                            setField('expected_income', event.target.value);
                                        }}
                                        onBlur={blurIncome}
                                    />
                                </FormField>
                            </div>
                            <span className="plan-chip">{confirmed ? 'Confirmado' : 'Rascunho'}</span>
                        </div>
                        <div className="plan-toolbar__actions">
                            <p className="plan-save" role="status">
                                {form.processing ? 'Salvando…' : notice}
                            </p>
                            <Button variant="secondary" onClick={askCopy} disabled={copying}>
                                Copiar do mês anterior
                            </Button>
                        </div>
                    </div>
                    <div className="plan-table-wrap">
                        <table className="plan-table">
                            <thead>
                                <tr>
                                    <th>Categoria</th>
                                    <th>Valor do mês</th>
                                    <th className="is-right">Gasto no mês</th>
                                    <th className="is-right">Usado</th>
                                    <th className="is-right">Resta</th>
                                </tr>
                            </thead>
                            <tbody>
                                {plan.spending.map((row) => {
                                    const index = rows.findIndex((item) => item.category_id === row.category_id);
                                    const error = form.errors[`items.${index}.amount`] || form.errors[`items.${index}.category_id`];

                                    return (
                                        <tr key={row.category_id}>
                                            <td><CategoryName row={row} /></td>
                                            <td className="plan-table__amount">
                                                <AmountField
                                                    label={`Valor do mês de ${row.name}`}
                                                    value={form.data.amounts[row.category_id] ?? ''}
                                                    error={error}
                                                    onChange={(event) => {
                                                        setNotice('');
                                                        setField((data) => ({
                                                            ...data,
                                                            amounts: {
                                                                ...data.amounts,
                                                                [row.category_id]: event.target.value,
                                                            },
                                                        }), `items.${index}.amount`, `items.${index}.category_id`);
                                                    }}
                                                    onBlur={() => blurAmount(row.category_id)}
                                                />
                                            </td>
                                            <td className="is-right"><MoneyText value={row.spent} /></td>
                                            <td className="is-right">
                                                {isZero(row.planned) ? (
                                                    <span className="plan-muted">—</span>
                                                ) : (
                                                    <UsageBar
                                                        percent={row.used_percent}
                                                        over={row.over}
                                                        nearLimit={row.near_limit}
                                                    />
                                                )}
                                            </td>
                                            <td className="is-right">
                                                <MoneyText value={row.remaining} />
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    {plan.reserves.length > 0 ? (
                        <>
                            <p className="plan-section">Reservas (quanto vou guardar no mês)</p>
                            <table className="plan-table">
                                <tbody>
                                    {plan.reserves.map((row) => {
                                        const index = rows.findIndex((item) => item.category_id === row.category_id);
                                        const error = form.errors[`items.${index}.amount`] || form.errors[`items.${index}.category_id`];

                                        return (
                                            <tr key={row.category_id}>
                                                <td><CategoryName row={row} /></td>
                                                <td className="plan-table__amount">
                                                    <AmountField
                                                        label={`Valor do mês de ${row.name}`}
                                                        value={form.data.amounts[row.category_id] ?? ''}
                                                        error={error}
                                                        onChange={(event) => {
                                                            setNotice('');
                                                            setField((data) => ({
                                                                ...data,
                                                                amounts: {
                                                                    ...data.amounts,
                                                                    [row.category_id]: event.target.value,
                                                                },
                                                            }), `items.${index}.amount`, `items.${index}.category_id`);
                                                        }}
                                                        onBlur={() => blurAmount(row.category_id)}
                                                    />
                                                </td>
                                                <td className="is-right plan-muted">—</td>
                                                <td className="is-right plan-muted" colSpan={2}>Guardado em Metas</td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </>
                    ) : null}
                </Card>
                <Card title="Visão final" className="card--highlight plan-vision">
                    <p className="plan-kicker">Renda esperada</p>
                    <p className="plan-figure"><MoneyText value={summary.expected_income} /></p>
                    <p className="plan-kicker">Total planejado</p>
                    <p className="plan-figure"><MoneyText value={summary.total_planned} /></p>
                    <p className="plan-kicker">Sobra a alocar</p>
                    <p className="plan-allocate">
                        <MoneyText
                            value={summary.to_allocate}
                            tone={String(summary.to_allocate).startsWith('-') ? 'text' : 'positive'}
                        />
                    </p>
                    {String(summary.to_allocate).startsWith('-') ? (
                        <p className="plan-alert">Você planejou mais que a renda esperada</p>
                    ) : null}
                    <svg className="plan-donut" viewBox="0 0 120 120" role="img" aria-label="Distribuição do planejado">
                        <circle cx="60" cy="60" r="44" className="plan-donut__track" />
                        <circle
                            cx="60"
                            cy="60"
                            r="44"
                            className="plan-donut__spend"
                            strokeDasharray={`${summary.ring.spending} ${summary.ring.circumference}`}
                        />
                        <circle
                            cx="60"
                            cy="60"
                            r="44"
                            className="plan-donut__reserve"
                            strokeDasharray={`${summary.ring.reserve} ${summary.ring.circumference}`}
                            strokeDashoffset={`-${summary.ring.spending}`}
                        />
                    </svg>
                    <p className="plan-note">
                        Gastos {plain(summary.spending_planned)} · Reservas {plain(summary.reserve_planned)}
                    </p>
                    <p className="plan-note">
                        Fixos e parcelas: {plain(summary.committed)}, dentro do planejado
                    </p>
                    {confirmed ? (
                        <div className="plan-confirmed">
                            <p>Planejamento confirmado</p>
                            <Button
                                variant="quiet"
                                disabled={reopening}
                                onClick={() => {
                                    setReopening(true);
                                    router.post(`/planning/${month}/reopen`, {}, {
                                        preserveScroll: true,
                                        onFinish: () => setReopening(false),
                                    });
                                }}
                            >
                                {reopening ? 'Voltando…' : 'Voltar para rascunho'}
                            </Button>
                        </div>
                    ) : (
                        <Button
                            className="plan-confirm"
                            disabled={confirming}
                            onClick={() => {
                                setConfirming(true);
                                router.post(`/planning/${month}/confirm`, {}, {
                                    preserveScroll: true,
                                    onFinish: () => setConfirming(false),
                                });
                            }}
                        >
                            {confirming ? 'Confirmando…' : 'Confirmar planejamento'}
                        </Button>
                    )}
                </Card>
            </div>
            <ConfirmDialog
                open={confirmCopy}
                title="Copiar do mês anterior?"
                message="A renda esperada e os valores deste mês serão substituídos pelos do mês anterior."
                confirmLabel="Copiar"
                busyLabel="Copiando…"
                busy={copying}
                onConfirm={runCopy}
                onCancel={() => setConfirmCopy(false)}
            />
        </>
    );
}
