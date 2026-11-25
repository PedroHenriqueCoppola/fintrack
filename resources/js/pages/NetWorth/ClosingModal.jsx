import { useEffect, useRef, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Landmark } from 'lucide-react';
import Button from '../../components/Button/Button';
import ConfirmDialog from '../../components/ConfirmDialog/ConfirmDialog';
import FormField from '../../components/FormField/FormField';
import Modal from '../../components/Modal/Modal';
import MoneyInput from '../../components/MoneyInput/MoneyInput';
import TextInput from '../../components/TextInput/TextInput';
import MonthField from '../../components/MonthField/MonthField';
import { useFieldSetter } from '../../lib/fields';
import {
    formatMoney,
    formatPlainMoney,
} from '../../lib/format';
import { moneyPayload } from '../../lib/moneyField';
import { decimalFromCents, sumCents, toCents } from '../../lib/cents';
import './ClosingModal.css';

const MONTHS = [
    'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
    'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
];

function fieldMonth(key) {
    const match = /^(\d{4})-(\d{2})$/.exec(key ?? '');

    if (!match) {
        return '';
    }

    return `${match[2]}/${match[1]}`;
}

function keyMonth(text) {
    const match = /^(\d{2})\/(\d{4})$/.exec(text ?? '');

    if (!match) {
        return null;
    }

    const month = parseInt(match[1], 10);
    const year = parseInt(match[2], 10);

    if (month < 1 || month > 12) {
        return null;
    }

    return `${year}-${String(month).padStart(2, '0')}`;
}

function phraseFromKey(key) {
    const match = /^(\d{4})-(\d{2})$/.exec(key ?? '');

    if (!match) {
        return '';
    }

    return `${MONTHS[parseInt(match[2], 10) - 1]} de ${match[1]}`;
}

function message(errors, key) {
    const value = errors?.[key];

    if (!value) {
        return '';
    }

    return Array.isArray(value) ? value[0] : value;
}

function payloadBalance(value) {
    return moneyPayload(value);
}

export default function ClosingModal({ month, maxMonth, onClose }) {
    const open = month !== null;
    const [monthText, setMonthText] = useState('');
    const [monthError, setMonthError] = useState('');
    const [reference, setReference] = useState(null);
    const [phrase, setPhrase] = useState('');
    const [existingId, setExistingId] = useState(null);
    const [closed, setClosed] = useState(null);
    const [ready, setReady] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const requestId = useRef(0);
    const form = useForm({ month: '', notes: '', items: [] });
    const setField = useFieldSetter(form);

    useEffect(() => {
        if (!open) {
            setReady(false);
            setConfirming(false);
            setClosed(null);
            setMonthError('');

            return;
        }

        setPhrase(phraseFromKey(month));
        setMonthText(fieldMonth(month));
    }, [open, month]);

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const key = keyMonth(monthText);

        if (!key) {
            return undefined;
        }

        const current = requestId.current + 1;
        requestId.current = current;
        const timer = window.setTimeout(() => {
            fetch(`/net-worth/form?month=${encodeURIComponent(key)}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then(async (response) => {
                    const body = await response.json().catch(() => null);

                    if (requestId.current !== current) {
                        return;
                    }

                    if (!response.ok) {
                        setMonthError(body?.errors?.month?.[0] || 'Informe um mês válido.');
                        return;
                    }

                    setMonthError('');
                    setPhrase(body.month_phrase || phraseFromKey(key));
                    setExistingId(body.existing_id ?? null);
                    setClosed(Boolean(body.existing_id));
                    setReference(body.reference ?? null);
                    form.setData({
                        month: body.month,
                        notes: body.notes ?? '',
                        items: (body.banks ?? []).map((bank) => ({
                            bank_id: bank.bank_id,
                            name: bank.name,
                            color: bank.color,
                            balance: bank.balance == null ? '' : String(bank.balance),
                            reference_balance: bank.reference_balance,
                        })),
                    });
                    form.clearErrors();
                    setReady(true);
                })
                .catch(() => {
                    if (requestId.current === current) {
                        setMonthError('Não foi possível carregar o fechamento.');
                    }
                });
        }, 250);

        return () => window.clearTimeout(timer);
    }, [open, monthText]);

    if (confirming && existingId) {
        return (
            <ConfirmDialog
                open
                title={`Excluir fechamento de ${phrase}?`}
                message="Essa ação não pode ser desfeita."
                confirmLabel="Excluir"
                busy={deleting}
                onCancel={() => setConfirming(false)}
                onConfirm={() => {
                    setDeleting(true);
                    router.delete(`/net-worth/${existingId}`, {
                        preserveScroll: true,
                        onSuccess: () => onClose(),
                        onFinish: () => setDeleting(false),
                    });
                }}
            />
        );
    }

    const items = form.data.items;
    const total = sumCents(items.map((item) => item.balance));
    const referenceCents = reference ? toCents(reference.total) : null;
    let variationNote = 'Sem fechamento anterior';
    let variationTone = '';

    if (referenceCents !== null) {
        const diff = total - referenceCents;

        if (diff === 0n) {
            variationNote = `Sem alteração em relação a ${reference.name_lower}`;
        } else {
            variationNote = `${formatMoney(decimalFromCents(diff))} em relação a ${reference.name_lower}`;
            variationTone = diff < 0n ? 'is-negative' : 'is-positive';
        }
    }

    function changeMonth(text) {
        setMonthText(text);
        setMonthError('');
        form.clearErrors('month');
    }

    function changeBalance(index, value) {
        setField((data) => ({
            ...data,
            items: data.items.map((item, itemIndex) => (
                itemIndex === index ? { ...item, balance: value } : item
            )),
        }), `items.${index}.balance`, 'items');
    }

    function submit(event) {
        event.preventDefault();
        const key = keyMonth(monthText);

        if (!key) {
            setMonthError('Informe um mês válido.');
            return;
        }

        form.transform((data) => ({
            month: key,
            notes: (data.notes ?? '').trim(),
            items: data.items.map((item) => ({
                bank_id: item.bank_id,
                balance: payloadBalance(item.balance),
            })),
        }));

        if (key !== form.data.month) {
            return;
        }

        const options = {
            preserveScroll: true,
            onSuccess: () => onClose(),
        };

        if (existingId) {
            form.put(`/net-worth/${existingId}`, options);
        } else {
            form.post('/net-worth', options);
        }
    }

    const monthReady = ready && keyMonth(monthText) === form.data.month;

    const title = existingId ? `Editar fechamento de ${phrase}` : `Fechar ${phrase}`;
    const itemsError = message(form.errors, 'items');

    return (
        <Modal
            open={open}
            title={title || 'Fechamento'}
            onClose={onClose}
            footer={existingId ? (
                <div className="closing-footer">
                    <Button
                        variant="quiet"
                        className="closing-delete"
                        onClick={() => setConfirming(true)}
                        disabled={form.processing}
                    >
                        Excluir
                    </Button>
                    <div className="closing-footer__side">
                        <Button variant="secondary" onClick={onClose} disabled={form.processing}>Cancelar</Button>
                        <Button type="submit" form="closing-form" disabled={form.processing || !monthReady}>
                            {form.processing ? 'Salvando…' : 'Salvar fechamento'}
                        </Button>
                    </div>
                </div>
            ) : (
                <>
                    <Button variant="secondary" onClick={onClose} disabled={form.processing}>Cancelar</Button>
                    <Button type="submit" form="closing-form" disabled={form.processing || !monthReady}>
                        {form.processing ? 'Salvando…' : 'Salvar fechamento'}
                    </Button>
                </>
            )}
        >
            <form id="closing-form" className="closing-form" noValidate onSubmit={submit}>
                <p className="closing-lead">Os valores vêm do mês passado. Ajuste o que mudou em cada banco.</p>
                <div className="closing-month">
                    <MonthField
                        label="Mês"
                        value={monthText}
                        min="01/2000"
                        max={fieldMonth(maxMonth)}
                        error={monthError || message(form.errors, 'month')}
                        onChange={changeMonth}
                    />
                    {closed === null ? null : (
                        <p className={closed ? 'closing-chip' : 'closing-chip closing-chip--open'}>
                            {closed ? 'Já fechado: você está editando' : 'Sem fechamento'}
                        </p>
                    )}
                </div>
                {itemsError ? <p className="closing-form-error" role="alert">{itemsError}</p> : null}
                {ready && items.length === 0 ? (
                    <p className="closing-notice">Não há banco para este fechamento.</p>
                ) : null}
                <div className="closing-banks">
                    {['account', 'investment'].map((kind) => {
                        const rows = items
                            .map((item, index) => ({ item, index }))
                            .filter(({ item }) => (kind === 'investment' ? item.kind === 'investment' : item.kind !== 'investment'));

                        if (rows.length === 0) {
                            return null;
                        }

                        return (
                            <div key={kind}>
                                <p className="closing-group__title">{kind === 'investment' ? 'Investimentos' : 'Contas'}</p>
                                {rows.map(({ item, index }) => (
                                    <div className="closing-bank" key={item.bank_id}>
                                        <span
                                            className={item.color ? 'closing-tile' : 'closing-tile closing-tile--plain'}
                                            style={item.color ? { '--item-color': item.color } : undefined}
                                        >
                                            <Landmark aria-hidden="true" strokeWidth={1.75} />
                                        </span>
                                        <div className="closing-bank__name">
                                            <strong>{item.name}</strong>
                                            <span>
                                                {!reference
                                                    ? 'Sem fechamento anterior'
                                                    : `${reference.name}: ${formatPlainMoney(item.reference_balance ?? '0.00')}`}
                                            </span>
                                        </div>
                                        <FormField label="Saldo" error={message(form.errors, `items.${index}.balance`)}>
                                            <MoneyInput
                                                value={item.balance}
                                                onChange={(value) => changeBalance(index, value)}
                                            />
                                        </FormField>
                                    </div>
                                ))}
                                <p className="closing-group__total">
                                    <span>Subtotal</span>
                                    <strong>{formatPlainMoney(decimalFromCents(sumCents(rows.map(({ item }) => item.balance))))}</strong>
                                </p>
                            </div>
                        );
                    })}
                </div>
                {ready ? (
                    <div className="closing-summary">
                        <div className="closing-total">
                            <span>Total do patrimônio</span>
                            <strong>{formatPlainMoney(decimalFromCents(total))}</strong>
                        </div>
                        <p className={variationTone ? `closing-variation ${variationTone}` : 'closing-variation'}>
                            {variationNote}
                        </p>
                    </div>
                ) : (
                    <p className="closing-notice">Carregando…</p>
                )}
                <FormField label="Observações (opcional)" error={message(form.errors, 'notes')}>
                    <TextInput
                        maxLength={500}
                        value={form.data.notes}
                        onChange={(value) => setField('notes', value)}
                    />
                </FormField>
            </form>
        </Modal>
    );
}
