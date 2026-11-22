import { useEffect, useRef, useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { ChevronDown, Plus } from 'lucide-react';
import Button from '../Button/Button';
import ConfirmDialog from '../ConfirmDialog/ConfirmDialog';
import DatePicker from '../DatePicker/DatePicker';
import FormField from '../FormField/FormField';
import Modal from '../Modal/Modal';
import ReferenceSeal from '../ReferenceSeal/ReferenceSeal';
import { useFieldSetter } from '../../lib/fields';
import {
    blurMoneyInput,
    formatDate,
    formatMoneyInput,
    formatPlainMoney,
    isoFromDate,
    parseBrDate,
    parseMoneyInput,
    useToday,
} from '../../lib/format';
import './TransactionModal.css';

const FUTURE_PAID_ERROR = 'Transação paga não pode ter data futura.';

function isoDate(value) {
    const parsed = parseBrDate(value);

    return parsed ? isoFromDate(parsed) : '';
}

function plusYears(iso, years) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso);

    if (!match) {
        return iso;
    }

    const year = Number(match[1]) + years;
    const month = Number(match[2]);
    const day = Number(match[3]);
    const probe = new Date(year, month - 1, day);

    if (probe.getFullYear() === year && probe.getMonth() === month - 1 && probe.getDate() === day) {
        return `${String(year).padStart(4, '0')}-${match[2]}-${match[3]}`;
    }

    const last = new Date(year, month, 0).getDate();

    return `${String(year).padStart(4, '0')}-${match[2]}-${String(last).padStart(2, '0')}`;
}

const EMPTY_OPTIONS = {
    categories: { expense: [], income: [] },
    banks: [],
    cards: [],
    paymentMethods: [],
};

export function NewTransactionButton({ onClick }) {
    return (
        <Button onClick={onClick}>
            <Plus aria-hidden="true" strokeWidth={1.75} />
            Nova transação
        </Button>
    );
}

function moneyPayload(value) {
    return parseMoneyInput(String(value ?? '').replace(/\s/g, '').replace(/^R\$/i, ''));
}

function datePayload(value) {
    if (!value) {
        return null;
    }

    const parsed = parseBrDate(value);

    return parsed ? isoFromDate(parsed) : value;
}

function known(list, id) {
    if (!id) {
        return '';
    }

    return list.some((item) => String(item.id) === String(id)) ? String(id) : '';
}

function withCurrent(list, current) {
    if (!current || list.some((item) => item.id === current.id)) {
        return list;
    }

    return [...list, current];
}

function optionLabel(item) {
    return item.archived ? `${item.name} (arquivado)` : item.name;
}

function buildInitial(mode, transaction, options, today) {
    if (!transaction || mode === 'create') {
        return {
            type: 'expense',
            status: 'paid',
            amount: '',
            date: formatDate(today),
            description: '',
            category_id: '',
            bank_id: '',
            payment_method_id: '',
            card_id: '',
            competence_date: '',
            notes: '',
            apply_amount_forward: false,
        };
    }

    const duplicate = mode === 'duplicate';
    const categories = options.categories?.[transaction.type] ?? [];

    return {
        type: transaction.type,
        status: duplicate ? 'paid' : transaction.status,
        amount: formatMoneyInput(transaction.amount),
        date: formatDate(duplicate ? today : transaction.date),
        description: transaction.description ?? '',
        category_id: duplicate ? known(categories, transaction.category?.id) : String(transaction.category?.id ?? ''),
        bank_id: duplicate ? known(options.banks, transaction.bank?.id) : String(transaction.bank?.id ?? ''),
        payment_method_id: duplicate
            ? known(options.paymentMethods, transaction.payment_method?.id)
            : String(transaction.payment_method?.id ?? ''),
        card_id: transaction.card
            ? (duplicate ? known(options.cards, transaction.card.id) : String(transaction.card.id))
            : '',
        competence_date: transaction.competence_date ? formatDate(transaction.competence_date) : '',
        notes: transaction.notes ?? '',
        apply_amount_forward: false,
    };
}

function InvoiceHint({ cardId, date, enabled }) {
    const [text, setText] = useState('');
    const requestId = useRef(0);

    useEffect(() => {
        if (!enabled || !cardId || !date) {
            setText('');
            return undefined;
        }

        const iso = datePayload(date);

        if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
            setText('');
            return undefined;
        }

        setText('');
        const current = requestId.current + 1;
        requestId.current = current;
        const timer = window.setTimeout(() => {
            fetch(`/cards/${cardId}/invoice-preview?date=${encodeURIComponent(iso)}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((response) => (response.ok ? response.json() : null))
                .then((body) => {
                    if (requestId.current !== current) {
                        return;
                    }

                    setText(typeof body?.label === 'string' ? body.label : '');
                })
                .catch(() => {
                    if (requestId.current === current) {
                        setText('');
                    }
                });
        }, 300);

        return () => {
            window.clearTimeout(timer);
            requestId.current += 1;
        };
    }, [cardId, date, enabled]);

    if (!text) {
        return null;
    }

    return <p className="tx-hint">{text}</p>;
}

function Segment({ label, value, options, onChange }) {
    return (
        <div className="tx-segment" role="radiogroup" aria-label={label}>
            {options.map((option) => {
                const selected = value === option.value;

                return (
                    <button
                        key={option.value}
                        type="button"
                        role="radio"
                        aria-checked={selected}
                        className={selected ? 'tx-segment__option tx-segment__option--on' : 'tx-segment__option'}
                        onClick={() => onChange(option.value)}
                    >
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}

function SelectField({ value, onChange, children }) {
    return (
        <div className="tx-select">
            <select value={value} onChange={(event) => onChange(event.target.value)}>
                {children}
            </select>
            <ChevronDown aria-hidden="true" strokeWidth={1.75} />
        </div>
    );
}

export default function TransactionModal({
    mode,
    transaction = null,
    options = EMPTY_OPTIONS,
    onClose,
}) {
    const today = useToday();
    const catalog = {
        categories: options.categories ?? EMPTY_OPTIONS.categories,
        banks: options.banks ?? [],
        cards: options.cards ?? [],
        paymentMethods: options.paymentMethods ?? [],
    };
    const form = useForm(() => buildInitial(mode, transaction, catalog, today));
    const setField = useFieldSetter(form);
    const [screen, setScreen] = useState(mode === 'edit' ? 'edit' : 'create');
    const [more, setMore] = useState(() => Boolean(
        transaction && (transaction.competence_date || transaction.notes) && mode !== 'create',
    ));
    const [confirming, setConfirming] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const editing = screen === 'edit';
    const categories = withCurrent(
        catalog.categories[form.data.type] ?? [],
        editing ? transaction?.category : null,
    );
    const banks = withCurrent(catalog.banks, editing ? transaction?.bank : null);
    const methodSource = form.data.type === 'reserve'
        ? catalog.paymentMethods.filter((item) => !item.requires_card)
        : catalog.paymentMethods;
    const currentMethod = editing ? transaction?.payment_method : null;
    const paymentMethods = withCurrent(
        methodSource,
        currentMethod && !(form.data.type === 'reserve' && currentMethod.requires_card) ? currentMethod : null,
    );
    const cards = withCurrent(catalog.cards, editing && transaction?.card ? {
        ...transaction.card,
        bank_name: transaction.bank?.name,
    } : null);
    const method = paymentMethods.find((item) => String(item.id) === String(form.data.payment_method_id));
    const card = cards.find((item) => String(item.id) === String(form.data.card_id));
    const requiresCard = Boolean(method?.requires_card);
    const lockedBank = requiresCard && card;
    const bankName = card?.bank_name
        || banks.find((item) => String(item.id) === String(card?.bank_id))?.name
        || '';
    const dateLimit = form.data.status === 'pending' ? plusYears(today, 5) : today;
    const missing = [];

    const noReserveCategory = form.data.type === 'reserve' && (catalog.categories.reserve ?? []).length === 0;

    if (!noReserveCategory && (catalog.categories[form.data.type] ?? []).length === 0) {
        missing.push('uma categoria');
    }

    if (catalog.banks.length === 0) {
        missing.push('um banco');
    }

    if (catalog.paymentMethods.length === 0) {
        missing.push('uma forma de pagamento');
    }

    function changeStatus(status) {
        setField('status', status);

        if (status === 'paid' && isoDate(form.data.date) > today) {
            form.setError('date', FUTURE_PAID_ERROR);
            return;
        }

        if (status === 'pending') {
            form.clearErrors('date');
        }
    }

    function changeDate(value) {
        setField('date', value);

        if (form.data.status === 'paid' && isoDate(value) > today) {
            form.setError('date', FUTURE_PAID_ERROR);
        }
    }

    function changeType(type) {
        setField((data) => {
            const current = catalog.paymentMethods.find((item) => String(item.id) === String(data.payment_method_id));
            const dropCard = type === 'reserve' && Boolean(current?.requires_card);

            return {
                ...data,
                type,
                category_id: '',
                payment_method_id: dropCard ? '' : data.payment_method_id,
                card_id: type === 'reserve' ? '' : data.card_id,
            };
        }, 'type', 'category_id', 'payment_method_id', 'card_id');
    }

    function changeMethod(id) {
        const next = paymentMethods.find((item) => String(item.id) === id);
        setField((data) => ({
            ...data,
            payment_method_id: id,
            card_id: next?.requires_card ? data.card_id : '',
        }), 'payment_method_id', 'card_id', 'bank_id');
    }

    function changeCard(id) {
        const next = cards.find((item) => String(item.id) === id);
        setField((data) => ({
            ...data,
            card_id: id,
            bank_id: next ? String(next.bank_id) : data.bank_id,
        }), 'card_id', 'bank_id');
    }

    function duplicate() {
        setScreen('create');
        setMore(Boolean(form.data.competence_date || form.data.notes));
        form.clearErrors();
        form.setData((data) => ({
            ...data,
            date: formatDate(today),
            status: 'paid',
            category_id: known(catalog.categories[data.type] ?? [], data.category_id),
            bank_id: known(catalog.banks, data.bank_id),
            payment_method_id: known(catalog.paymentMethods, data.payment_method_id),
            card_id: known(catalog.cards, data.card_id),
        }));
    }

    function submit(event) {
        event.preventDefault();

        if (form.data.type === 'reserve' && (catalog.categories.reserve ?? []).length === 0) {
            return;
        }

        const payload = {
            type: form.data.type,
            status: form.data.status,
            amount: moneyPayload(form.data.amount),
            date: datePayload(form.data.date),
            description: form.data.description,
            category_id: form.data.category_id || null,
            payment_method_id: form.data.payment_method_id || null,
            bank_id: requiresCard ? (card?.bank_id ?? null) : (form.data.bank_id || null),
            card_id: requiresCard ? (form.data.card_id || null) : null,
            competence_date: form.data.competence_date ? datePayload(form.data.competence_date) : null,
            notes: form.data.notes.trim() === '' ? null : form.data.notes.trim(),
            apply_amount_forward: Boolean(editing && transaction?.fixed_expense && form.data.apply_amount_forward),
        };

        form.transform(() => payload);
        const visit = {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => onClose(),
        };

        if (editing && transaction) {
            form.put(`/transactions/${transaction.id}`, visit);
            return;
        }

        form.post('/transactions', visit);
    }

    function remove() {
        if (!transaction) {
            return;
        }

        setDeleting(true);
        router.delete(`/transactions/${transaction.id}`, {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onFinish: () => setDeleting(false),
        });
    }

    if (confirming && transaction) {
        return (
            <ConfirmDialog
                open
                title="Excluir transação?"
                confirmLabel="Excluir"
                busy={deleting}
                onConfirm={remove}
                onCancel={() => setConfirming(false)}
            >
                {`${transaction.description}, ${formatPlainMoney(transaction.amount)}, em ${formatDate(transaction.date)}. Essa ação não pode ser desfeita.`}
                {transaction.generated ? (
                    <>
                        {' '}
                        <strong>O sistema não vai recriá-la neste mês.</strong>
                    </>
                ) : null}
            </ConfirmDialog>
        );
    }

    return (
        <Modal
            open
            className="modal--transaction"
            title={editing ? 'Editar transação' : 'Nova transação'}
            onClose={onClose}
            footer={editing ? (
                <div className="tx-footer">
                    <div className="tx-footer__side">
                        <Button variant="quiet" onClick={duplicate} disabled={form.processing}>Duplicar</Button>
                        <Button variant="quiet" className="tx-delete" onClick={() => setConfirming(true)} disabled={form.processing}>
                            Excluir
                        </Button>
                    </div>
                    <div className="tx-footer__side">
                        <Button variant="secondary" onClick={onClose} disabled={form.processing}>Cancelar</Button>
                        <Button type="submit" form="transaction-form" disabled={form.processing || noReserveCategory}>
                            {form.processing ? 'Salvando…' : 'Salvar'}
                        </Button>
                    </div>
                </div>
            ) : (
                <>
                    <Button variant="secondary" onClick={onClose} disabled={form.processing}>Cancelar</Button>
                    <Button type="submit" form="transaction-form" disabled={form.processing || noReserveCategory}>
                        {form.processing ? 'Salvando…' : 'Salvar'}
                    </Button>
                </>
            )}
        >
            <form id="transaction-form" className="tx-form" noValidate onSubmit={submit}>
                <p className="tx-lead">
                    {editing
                        ? 'Altere o que precisar. Duplicar abre uma cópia com a data de hoje.'
                        : 'Registre uma despesa, uma receita ou uma reserva.'}
                </p>
                <div className="tx-types">
                    <Segment
                        label="Tipo"
                        value={form.data.type}
                        onChange={changeType}
                        options={[
                            { value: 'expense', label: 'Despesa' },
                            { value: 'income', label: 'Receita' },
                            { value: 'reserve', label: 'Reserva' },
                        ]}
                    />
                    <Segment
                        label="Status"
                        value={form.data.status}
                        onChange={changeStatus}
                        options={[
                            { value: 'paid', label: 'Paga' },
                            { value: 'pending', label: 'Pendente' },
                        ]}
                    />
                </div>
                {form.errors.type ? <p className="tx-inline-error" role="alert">{form.errors.type}</p> : null}
                {form.errors.status ? <p className="tx-inline-error" role="alert">{form.errors.status}</p> : null}
                <div className="tx-grid">
                    <FormField label="Valor" error={form.errors.amount}>
                        <input
                            className="tx-money"
                            inputMode="decimal"
                            autoComplete="off"
                            value={form.data.amount}
                            onChange={(event) => setField('amount', event.target.value)}
                            onBlur={() => {
                                const formatted = blurMoneyInput(form.data.amount);
                                if (formatted !== null) {
                                    setField('amount', formatted);
                                }
                            }}
                        />
                    </FormField>
                    <DatePicker
                        label="Data"
                        value={form.data.date}
                        max={dateLimit}
                        error={form.errors.date}
                        onChange={changeDate}
                    />
                </div>
                <InvoiceHint
                    cardId={form.data.card_id}
                    date={form.data.date}
                    enabled={form.data.type === 'expense' && requiresCard && Boolean(form.data.card_id)}
                />
                {editing && transaction?.fixed_expense ? (
                    <label className="tx-forward">
                        <input
                            type="checkbox"
                            checked={Boolean(form.data.apply_amount_forward)}
                            onChange={(event) => setField('apply_amount_forward', event.target.checked)}
                        />
                        Usar este valor daqui em diante
                    </label>
                ) : null}
                <FormField label="Descrição" error={form.errors.description}>
                    <input
                        type="text"
                        autoComplete="off"
                        maxLength={120}
                        placeholder={form.data.type === 'reserve' ? 'Ex.: Aporte reserva de emergência' : undefined}
                        value={form.data.description}
                        onChange={(event) => setField('description', event.target.value)}
                    />
                </FormField>
                <div className="tx-grid">
                    <FormField label="Categoria" error={form.errors.category_id}>
                        <SelectField
                            value={form.data.category_id}
                            onChange={(value) => setField('category_id', value)}
                        >
                            <option value="">Escolha</option>
                            {categories.map((item) => (
                                <option key={item.id} value={item.id}>{optionLabel(item)}</option>
                            ))}
                        </SelectField>
                    </FormField>
                    <div className={lockedBank ? 'tx-locked' : undefined}>
                        <FormField label="Banco" error={form.errors.bank_id}>
                            {lockedBank ? (
                                <input type="text" value={`${bankName} · do cartão`} disabled readOnly />
                            ) : (
                                <SelectField
                                    value={form.data.bank_id}
                                    onChange={(value) => setField('bank_id', value)}
                                >
                                    <option value="">Escolha</option>
                                    {banks.map((item) => (
                                        <option key={item.id} value={item.id}>{optionLabel(item)}</option>
                                    ))}
                                </SelectField>
                            )}
                        </FormField>
                    </div>
                </div>
                <div className={requiresCard ? 'tx-grid' : undefined}>
                    <FormField label="Forma de pagamento" error={form.errors.payment_method_id}>
                        <SelectField value={form.data.payment_method_id} onChange={changeMethod}>
                            <option value="">Escolha</option>
                            {paymentMethods.map((item) => (
                                <option key={item.id} value={item.id}>{optionLabel(item)}</option>
                            ))}
                        </SelectField>
                    </FormField>
                    {requiresCard ? (
                        <FormField label="Cartão" error={form.errors.card_id}>
                            <SelectField value={form.data.card_id} onChange={changeCard}>
                                <option value="">Escolha</option>
                                {cards.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {`${optionLabel(item)} · fecha dia ${item.closing_day}`}
                                    </option>
                                ))}
                            </SelectField>
                        </FormField>
                    ) : null}
                </div>
                <button
                    type="button"
                    className="tx-more"
                    aria-expanded={more}
                    onClick={() => setMore((open) => !open)}
                >
                    Mais opções
                    <ChevronDown aria-hidden="true" strokeWidth={1.75} className={more ? 'tx-more__icon tx-more__icon--open' : 'tx-more__icon'} />
                </button>
                {more ? (
                    <div className="tx-extra">
                        <div className="tx-competence">
                            <DatePicker
                                label="Data de competência (opcional)"
                                value={form.data.competence_date}
                                error={form.errors.competence_date}
                                onChange={(value) => setField('competence_date', value)}
                            />
                            <ReferenceSeal label={editing ? transaction?.competence_label : null} />
                        </div>
                        <FormField label="Observação (opcional)" error={form.errors.notes}>
                            <input
                                type="text"
                                autoComplete="off"
                                maxLength={500}
                                value={form.data.notes}
                                onChange={(event) => setField('notes', event.target.value)}
                            />
                        </FormField>
                    </div>
                ) : null}
                {noReserveCategory ? (
                    <p className="tx-hint">
                        Cadastre uma categoria de Reserva em <Link href="/manage">Gerenciar</Link>.
                    </p>
                ) : null}
                {missing.length > 0 ? (
                    <p className="tx-hint">
                        {`Cadastre ${missing.join(', ').replace(/, ([^,]*)$/, ' e $1')} em `}
                        <Link href="/manage">Gerenciar</Link>
                        {' para lançar.'}
                    </p>
                ) : null}
            </form>
        </Modal>
    );
}
