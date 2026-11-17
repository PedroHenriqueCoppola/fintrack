import { useForm } from '@inertiajs/react';
import Button from '../../components/Button/Button';
import FormField from '../../components/FormField/FormField';
import Modal from '../../components/Modal/Modal';
import { blurMoneyInput, formatMoneyInput, parseMoneyInput } from '../../lib/format';
import { useFieldSetter } from '../../lib/fields';
import ChoiceGroup from './ChoiceGroup';

function colorOptions(colors) {
    return [
        { value: '', label: 'Nenhuma' },
        ...colors.map((color) => ({
            value: color.value,
            label: color.label,
            swatch: color.value,
        })),
    ];
}

function iconOptions(icons) {
    return [
        { value: '', label: 'Nenhum' },
        ...icons.map((icon) => ({
            value: icon.value,
            label: icon.label,
            icon: icon.value,
        })),
    ];
}

function labelFor(options, value) {
    return options.find((option) => option.value === value)?.label ?? '';
}

function visit(form, item, url, payload, onClose) {
    form.transform(() => payload);
    const options = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => onClose(),
    };

    if (item) {
        form.put(`${url}/${item.id}`, options);
        return;
    }

    form.post(url, options);
}

function FormFooter({ formId, processing, onClose }) {
    return (
        <>
            <Button variant="secondary" onClick={onClose} disabled={processing}>Cancelar</Button>
            <Button type="submit" form={formId} disabled={processing}>
                {processing ? 'Salvando…' : 'Salvar'}
            </Button>
        </>
    );
}

export function BankForm({ item, colors, onClose }) {
    const form = useForm({
        name: item?.name ?? '',
        color: item?.color ?? '',
    });
    const setField = useFieldSetter(form);

    function submit(event) {
        event.preventDefault();
        visit(form, item, '/manage/banks', {
            name: form.data.name,
            color: form.data.color || null,
        }, onClose);
    }

    return (
        <Modal
            open
            title={item ? 'Editar banco' : 'Novo banco'}
            onClose={onClose}
            footer={<FormFooter formId="bank-form" processing={form.processing} onClose={onClose} />}
        >
            <form id="bank-form" className="manage-form" noValidate onSubmit={submit}>
                <FormField label="Nome" error={form.errors.name}>
                    <input
                        type="text"
                        name="name"
                        autoComplete="off"
                        value={form.data.name}
                        onChange={(event) => setField('name', event.target.value)}
                    />
                </FormField>
                <ChoiceGroup
                    legend="Cor"
                    name="bank-color"
                    appearance="swatch"
                    value={form.data.color}
                    options={colorOptions(colors)}
                    onChange={(value) => setField('color', value)}
                    error={form.errors.color}
                />
            </form>
        </Modal>
    );
}

export function CategoryForm({ item, colors, icons, categoryTypes, natures, onClose }) {
    const form = useForm({
        name: item?.name ?? '',
        applies_to: item?.applies_to ?? '',
        nature: item?.nature ?? '',
        icon: item?.icon ?? '',
        color: item?.color ?? '',
        initial_balance: item && item.applies_to === 'expense' ? formatMoneyInput(item.initial_balance) : '',
    });
    const setField = useFieldSetter(form);
    const expense = (item?.applies_to ?? form.data.applies_to) === 'expense';

    function submit(event) {
        event.preventDefault();
        const appliesTo = item ? item.applies_to : form.data.applies_to;
        const payload = {
            name: form.data.name,
            icon: form.data.icon || null,
            color: form.data.color || null,
        };

        if (!item) {
            payload.applies_to = form.data.applies_to;
            if (form.data.applies_to === 'expense') {
                payload.nature = form.data.nature || null;
            }
        }

        if (appliesTo === 'expense') {
            const rawBalance = String(form.data.initial_balance ?? '').trim();
            const parsed = parseMoneyInput(rawBalance);
            const kept = rawBalance !== '' && parsed === rawBalance && /[.,]/.test(rawBalance);
            // PHP treats "12.5" as a number. A rejected dot-decimal is posted
            // with a trailing comma so the server returns the format error.
            const serverValue = kept && /^-?\d+(\.\d+)?$/.test(parsed) ? `${parsed},` : parsed;

            payload.initial_balance = rawBalance === '' ? null : serverValue;
        }

        visit(form, item, '/manage/categories', payload, onClose);
    }

    return (
        <Modal
            open
            title={item ? 'Editar categoria' : 'Nova categoria'}
            onClose={onClose}
            footer={<FormFooter formId="category-form" processing={form.processing} onClose={onClose} />}
        >
            <form id="category-form" className="manage-form" noValidate onSubmit={submit}>
                <p className="manage-lead">Organize o orçamento do jeito que faz sentido para você.</p>
                <FormField label="Nome" error={form.errors.name}>
                    <input
                        type="text"
                        name="name"
                        autoComplete="off"
                        value={form.data.name}
                        onChange={(event) => setField('name', event.target.value)}
                    />
                </FormField>
                {item ? (
                    <div className="manage-readonly">
                        <p><span>Tipo</span> {labelFor(categoryTypes, item.applies_to)}</p>
                        {item.nature ? <p><span>Natureza</span> {labelFor(natures, item.nature)}</p> : null}
                        <p className="manage-hint">
                            {item.applies_to === 'expense'
                                ? 'O tipo e a natureza não podem ser alterados. Isso protege o histórico.'
                                : 'O tipo não pode ser alterado. Isso protege o histórico.'}
                        </p>
                    </div>
                ) : (
                    <div className="manage-form__row">
                        <ChoiceGroup
                            legend="Tipo"
                            name="category-type"
                            appearance="segment"
                            value={form.data.applies_to}
                            options={categoryTypes}
                            onChange={(value) => setField((data) => ({
                                ...data,
                                applies_to: value,
                                nature: value === 'expense' ? data.nature : '',
                                initial_balance: value === 'expense' ? data.initial_balance : '',
                            }), 'applies_to', 'nature', 'initial_balance')}
                            error={form.errors.applies_to}
                        />
                        {expense ? (
                            <ChoiceGroup
                                legend="Natureza"
                                name="category-nature"
                                appearance="segment"
                                value={form.data.nature}
                                options={natures}
                                onChange={(value) => setField('nature', value)}
                                error={form.errors.nature}
                            />
                        ) : null}
                    </div>
                )}
                <div className="manage-form__row">
                    {expense ? (
                        <FormField
                            label="Saldo inicial"
                            hint="Opcional. Exemplo: 1.234,56."
                            error={form.errors.initial_balance}
                        >
                            <input
                                type="text"
                                name="initial_balance"
                                inputMode="decimal"
                                autoComplete="off"
                                placeholder="0,00"
                                value={form.data.initial_balance}
                                onChange={(event) => setField('initial_balance', event.target.value)}
                                onBlur={() => {
                                    const formatted = blurMoneyInput(form.data.initial_balance);
                                    if (formatted !== null) {
                                        setField('initial_balance', formatted);
                                    }
                                }}
                            />
                        </FormField>
                    ) : null}
                    <ChoiceGroup
                        legend="Cor"
                        name="category-color"
                        appearance="swatch"
                        value={form.data.color}
                        options={colorOptions(colors)}
                        onChange={(value) => setField('color', value)}
                        error={form.errors.color}
                    />
                </div>
                <ChoiceGroup
                    legend="Ícone"
                    name="category-icon"
                    appearance="icons"
                    value={form.data.icon}
                    options={iconOptions(icons)}
                    onChange={(value) => setField('icon', value)}
                    error={form.errors.icon}
                />
            </form>
        </Modal>
    );
}

export function CardForm({ item, banks, colors, onClose }) {
    const form = useForm({
        name: item?.name ?? '',
        bank_id: item?.bank_id ? String(item.bank_id) : '',
        closing_day: item?.closing_day ?? '',
        due_day: item?.due_day ?? '',
        color: item?.color ?? '',
    });
    const setField = useFieldSetter(form);
    const activeBanks = banks.filter((bank) => !bank.archived);
    const bankMissing = Boolean(form.data.bank_id)
        && !activeBanks.some((bank) => String(bank.id) === form.data.bank_id);

    function submit(event) {
        event.preventDefault();
        visit(form, item, '/manage/cards', {
            name: form.data.name,
            bank_id: form.data.bank_id || null,
            closing_day: form.data.closing_day,
            due_day: form.data.due_day === '' ? null : form.data.due_day,
            color: form.data.color || null,
        }, onClose);
    }

    return (
        <Modal
            open
            title={item ? 'Editar cartão' : 'Novo cartão'}
            onClose={onClose}
            footer={<FormFooter formId="card-form" processing={form.processing} onClose={onClose} />}
        >
            <form id="card-form" className="manage-form" noValidate onSubmit={submit}>
                <FormField label="Nome" error={form.errors.name}>
                    <input
                        type="text"
                        name="name"
                        autoComplete="off"
                        value={form.data.name}
                        onChange={(event) => setField('name', event.target.value)}
                    />
                </FormField>
                <FormField
                    label="Banco"
                    error={form.errors.bank_id}
                    hint={activeBanks.length === 0
                        ? 'Cadastre um banco ativo antes.'
                        : (bankMissing ? 'O banco atual está arquivado. Escolha um banco ativo.' : undefined)}
                >
                    <select
                        name="bank_id"
                        value={form.data.bank_id}
                        onChange={(event) => setField('bank_id', event.target.value)}
                    >
                        <option value="">Selecione</option>
                        {bankMissing ? (
                            <option value={form.data.bank_id}>{item?.bank_name} (arquivado)</option>
                        ) : null}
                        {activeBanks.map((bank) => (
                            <option key={bank.id} value={String(bank.id)}>{bank.name}</option>
                        ))}
                    </select>
                </FormField>
                <FormField label="Dia de fechamento" error={form.errors.closing_day}>
                    <input
                        type="number"
                        name="closing_day"
                        min="1"
                        max="31"
                        inputMode="numeric"
                        value={form.data.closing_day}
                        onChange={(event) => setField('closing_day', event.target.value)}
                    />
                </FormField>
                <FormField label="Dia de vencimento" hint="Opcional." error={form.errors.due_day}>
                    <input
                        type="number"
                        name="due_day"
                        min="1"
                        max="31"
                        inputMode="numeric"
                        value={form.data.due_day}
                        onChange={(event) => setField('due_day', event.target.value)}
                    />
                </FormField>
                <ChoiceGroup
                    legend="Cor"
                    name="card-color"
                    appearance="swatch"
                    value={form.data.color}
                    options={colorOptions(colors)}
                    onChange={(value) => setField('color', value)}
                    error={form.errors.color}
                />
            </form>
        </Modal>
    );
}

export function PaymentMethodForm({ item, onClose }) {
    const form = useForm({
        name: item?.name ?? '',
        requires_card: item?.requires_card ?? false,
    });
    const setField = useFieldSetter(form);

    function submit(event) {
        event.preventDefault();
        visit(form, item, '/manage/payment-methods', {
            name: form.data.name,
            requires_card: form.data.requires_card,
        }, onClose);
    }

    return (
        <Modal
            open
            title={item ? 'Editar forma de pagamento' : 'Nova forma de pagamento'}
            onClose={onClose}
            footer={<FormFooter formId="payment-method-form" processing={form.processing} onClose={onClose} />}
        >
            <form id="payment-method-form" className="manage-form" noValidate onSubmit={submit}>
                <FormField label="Nome" error={form.errors.name}>
                    <input
                        type="text"
                        name="name"
                        autoComplete="off"
                        value={form.data.name}
                        onChange={(event) => setField('name', event.target.value)}
                    />
                </FormField>
                <FormField label="Exige cartão" error={form.errors.requires_card}>
                    <input
                        className="manage-check"
                        type="checkbox"
                        name="requires_card"
                        checked={form.data.requires_card}
                        onChange={(event) => setField('requires_card', event.target.checked)}
                    />
                </FormField>
            </form>
        </Modal>
    );
}
