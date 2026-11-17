import { useEffect } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import Button from '../../components/Button/Button';
import DatePicker from '../../components/DatePicker/DatePicker';
import FormField from '../../components/FormField/FormField';
import Modal from '../../components/Modal/Modal';
import MonthField from '../../components/MonthField/MonthField';
import { formatPlainMoney } from '../../lib/format';
import { useFieldSetter } from '../../lib/fields';
import { datePayload, formatDate, moneyPayload, moneyText, monthPayload, monthText } from './fields';

function PaymentFields({ form, setField, options }) {
    const method = options.payment_methods.find((item) => String(item.id) === String(form.data.payment_method_id));
    const card = options.cards.find((item) => String(item.id) === String(form.data.card_id));
    const requiresCard = Boolean(method?.requires_card);
    const bankName = card?.bank_name
        || options.banks.find((item) => String(item.id) === String(card?.bank_id))?.name
        || '';

    return (
        <>
            <FormField label="Forma de pagamento" error={form.errors.payment_method_id}>
                <select
                    value={form.data.payment_method_id}
                    onChange={(event) => {
                        const next = options.payment_methods.find((item) => String(item.id) === event.target.value);
                        setField((data) => ({
                            ...data,
                            payment_method_id: event.target.value,
                            card_id: next?.requires_card ? data.card_id : '',
                        }), 'payment_method_id', 'card_id', 'bank_id');
                    }}
                >
                    <option value="">Escolha</option>
                    {options.payment_methods.map((item) => (
                        <option key={item.id} value={item.id}>{item.name}</option>
                    ))}
                </select>
            </FormField>
            {requiresCard ? (
                <FormField label="Cartão" error={form.errors.card_id}>
                    <select
                        value={form.data.card_id}
                        onChange={(event) => {
                            const next = options.cards.find((item) => String(item.id) === event.target.value);
                            setField((data) => ({
                                ...data,
                                card_id: event.target.value,
                                bank_id: next ? String(next.bank_id) : data.bank_id,
                            }), 'card_id', 'bank_id');
                        }}
                    >
                        <option value="">Escolha</option>
                        {options.cards.map((item) => (
                            <option key={item.id} value={item.id}>{item.name}</option>
                        ))}
                    </select>
                </FormField>
            ) : null}
            <FormField label="Banco" error={form.errors.bank_id}>
                {requiresCard && card ? (
                    <input type="text" value={bankName ? `${bankName} · do cartão` : 'Do cartão'} disabled readOnly />
                ) : (
                    <select
                        value={form.data.bank_id}
                        onChange={(event) => setField('bank_id', event.target.value)}
                    >
                        <option value="">Escolha</option>
                        {options.banks.map((item) => (
                            <option key={item.id} value={item.id}>{item.name}</option>
                        ))}
                    </select>
                )}
            </FormField>
        </>
    );
}

function Footer({ formId, processing, onClose }) {
    return (
        <>
            <Button variant="secondary" onClick={onClose} disabled={processing}>Cancelar</Button>
            <Button type="submit" form={formId} disabled={processing}>
                {processing ? 'Salvando…' : 'Salvar'}
            </Button>
        </>
    );
}

function categoryOptions(options, current) {
    const list = [...options.categories];

    if (current && !list.some((item) => item.id === current.id)) {
        list.push(current);
    }

    return list;
}

export function FixedExpenseModal({ item, options, onClose }) {
    const form = useForm({
        name: item?.name ?? '',
        category_id: item?.category?.id ? String(item.category.id) : '',
        amount: item ? moneyText(item.amount) : '',
        due_day: item?.due_day ? String(item.due_day) : '',
        start_month: item ? monthText(item.start_month) : '',
        end_month: item?.end_month ? monthText(item.end_month) : '',
        bank_id: item?.bank?.id ? String(item.bank.id) : '',
        payment_method_id: item?.payment_method?.id ? String(item.payment_method.id) : '',
        card_id: item?.card?.id ? String(item.card.id) : '',
        notes: item?.notes ?? '',
    });
    const setField = useFieldSetter(form);
    const categories = categoryOptions(options, item?.category);

    function submit(event) {
        event.preventDefault();
        const method = options.payment_methods.find((entry) => String(entry.id) === String(form.data.payment_method_id));
        const card = options.cards.find((entry) => String(entry.id) === String(form.data.card_id));
        form.transform(() => ({
            name: form.data.name,
            category_id: form.data.category_id || null,
            amount: moneyPayload(form.data.amount),
            due_day: form.data.due_day,
            start_month: monthPayload(form.data.start_month),
            end_month: form.data.end_month ? monthPayload(form.data.end_month) : null,
            payment_method_id: form.data.payment_method_id || null,
            bank_id: method?.requires_card ? (card?.bank_id ?? null) : (form.data.bank_id || null),
            card_id: method?.requires_card ? (form.data.card_id || null) : null,
            notes: form.data.notes.trim() === '' ? null : form.data.notes.trim(),
        }));
        const visit = { preserveScroll: true, preserveState: true, onSuccess: () => onClose() };

        if (item) {
            form.put(`/planning/fixed-expenses/${item.id}`, visit);
            return;
        }

        form.post('/planning/fixed-expenses', visit);
    }

    return (
        <Modal
            open
            title={item ? 'Editar gasto fixo' : 'Novo gasto fixo'}
            onClose={onClose}
            footer={<Footer formId="fixed-form" processing={form.processing} onClose={onClose} />}
        >
            <form id="fixed-form" className="plan-form" noValidate onSubmit={submit}>
                <p className="plan-lead">O valor padrão vira uma despesa pendente quando o mês é confirmado.</p>
                <FormField label="Nome" error={form.errors.name}>
                    <input value={form.data.name} autoComplete="off" onChange={(event) => setField('name', event.target.value)} />
                </FormField>
                <FormField label="Categoria" error={form.errors.category_id}>
                    <select value={form.data.category_id} onChange={(event) => setField('category_id', event.target.value)}>
                        <option value="">Escolha</option>
                        {categories.map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.archived ? `${category.name} (arquivado)` : category.name}
                            </option>
                        ))}
                    </select>
                </FormField>
                <div className="plan-form__row">
                    <FormField label="Valor padrão" error={form.errors.amount}>
                        <input
                            inputMode="decimal"
                            autoComplete="off"
                            value={form.data.amount}
                            onChange={(event) => setField('amount', event.target.value)}
                        />
                    </FormField>
                    <FormField label="Dia do vencimento" error={form.errors.due_day}>
                        <input
                            inputMode="numeric"
                            autoComplete="off"
                            value={form.data.due_day}
                            onChange={(event) => setField('due_day', event.target.value.replace(/\D/g, '').slice(0, 2))}
                        />
                    </FormField>
                </div>
                <div className="plan-form__row">
                    <MonthField
                        label="Mês de início"
                        value={form.data.start_month}
                        error={form.errors.start_month}
                        onChange={(value) => setField('start_month', value)}
                    />
                    <MonthField
                        label="Mês de fim"
                        value={form.data.end_month}
                        error={form.errors.end_month}
                        hint="Opcional."
                        onChange={(value) => setField('end_month', value)}
                    />
                </div>
                <PaymentFields form={form} setField={setField} options={options} />
                <FormField label="Observação" error={form.errors.notes}>
                    <textarea
                        rows={3}
                        maxLength={500}
                        value={form.data.notes}
                        onChange={(event) => setField('notes', event.target.value)}
                    />
                </FormField>
            </form>
        </Modal>
    );
}

export function InstallmentModal({ item, options, onClose }) {
    const page = usePage();
    const locked = Boolean(item?.locked);
    const form = useForm({
        name: item?.name ?? '',
        category_id: item?.category?.id ? String(item.category.id) : '',
        total_amount: item ? moneyText(item.total_amount) : '',
        installments_count: item ? String(item.installments_count) : '',
        first_date: item?.first_date ? formatDate(item.first_date) : '',
        bank_id: item?.bank?.id ? String(item.bank.id) : '',
        payment_method_id: item?.payment_method?.id ? String(item.payment_method.id) : '',
        card_id: item?.card?.id ? String(item.card.id) : '',
        notes: item?.notes ?? '',
    });
    const setField = useFieldSetter(form);
    const categories = categoryOptions(options, item?.category);
    const preview = page.props.installmentPreview ?? page.props.installment_preview;
    const previewMatches = preview
        && String(preview.installments_count) === String(form.data.installments_count).trim()
        && moneyPayload(form.data.total_amount) === String(preview.total_amount);

    useEffect(() => {
        if (locked) {
            return undefined;
        }

        const amount = moneyPayload(form.data.total_amount);
        const count = String(form.data.installments_count).trim();

        if (!/^\d+(\.\d{1,2})?$/.test(amount) || !/^\d+$/.test(count) || previewMatches) {
            return undefined;
        }

        const timer = setTimeout(() => {
            router.post('/planning/installments/preview', {
                total_amount: amount,
                installments_count: count,
            }, {
                preserveScroll: true,
                preserveState: true,
            });
        }, 400);

        return () => clearTimeout(timer);
    }, [form.data.total_amount, form.data.installments_count, locked, previewMatches]);

    function submit(event) {
        event.preventDefault();
        const method = options.payment_methods.find((entry) => String(entry.id) === String(form.data.payment_method_id));
        const card = options.cards.find((entry) => String(entry.id) === String(form.data.card_id));
        const payload = {
            name: form.data.name,
            category_id: form.data.category_id || null,
            payment_method_id: form.data.payment_method_id || null,
            bank_id: method?.requires_card ? (card?.bank_id ?? null) : (form.data.bank_id || null),
            card_id: method?.requires_card ? (form.data.card_id || null) : null,
            notes: form.data.notes.trim() === '' ? null : form.data.notes.trim(),
        };

        if (!locked) {
            payload.total_amount = moneyPayload(form.data.total_amount);
            payload.installments_count = form.data.installments_count;
            payload.first_date = datePayload(form.data.first_date);
        }

        form.transform(() => payload);
        const visit = { preserveScroll: true, preserveState: true, onSuccess: () => onClose() };

        if (item) {
            form.put(`/planning/installments/${item.id}`, visit);
            return;
        }

        form.post('/planning/installments', visit);
    }

    return (
        <Modal
            open
            title={item ? 'Editar parcelado' : 'Novo parcelado'}
            onClose={onClose}
            footer={<Footer formId="installment-form" processing={form.processing} onClose={onClose} />}
        >
            <form id="installment-form" className="plan-form" noValidate onSubmit={submit}>
                <p className="plan-lead">Cada parcela vira uma despesa pendente no mês confirmado.</p>
                <FormField label="Nome" error={form.errors.name}>
                    <input value={form.data.name} autoComplete="off" onChange={(event) => setField('name', event.target.value)} />
                </FormField>
                <FormField label="Categoria" error={form.errors.category_id}>
                    <select value={form.data.category_id} onChange={(event) => setField('category_id', event.target.value)}>
                        <option value="">Escolha</option>
                        {categories.map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.archived ? `${category.name} (arquivado)` : category.name}
                            </option>
                        ))}
                    </select>
                </FormField>
                <fieldset className="plan-fieldset" disabled={locked}>
                    <div className="plan-form__row">
                        <FormField label="Valor total" error={form.errors.total_amount || page.props.errors?.total_amount}>
                            <input
                                inputMode="decimal"
                                autoComplete="off"
                                value={form.data.total_amount}
                                onChange={(event) => setField('total_amount', event.target.value)}
                            />
                        </FormField>
                        <FormField label="Número de parcelas" error={form.errors.installments_count}>
                            <input
                                inputMode="numeric"
                                autoComplete="off"
                                value={form.data.installments_count}
                                onChange={(event) => setField('installments_count', event.target.value.replace(/\D/g, '').slice(0, 3))}
                            />
                        </FormField>
                    </div>
                    <DatePicker
                        label="Data da primeira parcela"
                        value={form.data.first_date}
                        error={form.errors.first_date}
                        onChange={(value) => setField('first_date', value)}
                    />
                </fieldset>
                {locked ? (
                    <p className="plan-hint">Para mudar o valor ou as parcelas, arquive e crie outro.</p>
                ) : null}
                {previewMatches ? (
                    <ul className="plan-preview">
                        {preview.amounts.map((amount, index) => (
                            <li key={`${index}-${amount}`}>
                                {index + 1}ª parcela: {formatPlainMoney(amount)}
                            </li>
                        ))}
                    </ul>
                ) : null}
                <PaymentFields form={form} setField={setField} options={options} />
                <FormField label="Observação" error={form.errors.notes}>
                    <textarea
                        rows={3}
                        maxLength={500}
                        value={form.data.notes}
                        onChange={(event) => setField('notes', event.target.value)}
                    />
                </FormField>
            </form>
        </Modal>
    );
}
