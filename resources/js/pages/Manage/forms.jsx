import { useForm } from '@inertiajs/react';
import Button from '../../components/Button/Button';
import FormField from '../../components/FormField/FormField';
import IntegerInput from '../../components/IntegerInput/IntegerInput';
import Modal from '../../components/Modal/Modal';
import Select from '../../components/Select/Select';
import TextInput from '../../components/TextInput/TextInput';
import { bankOption } from '../../lib/catalogOptions';
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
        kind: item?.kind ?? 'account',
    });
    const setField = useFieldSetter(form);

    function submit(event) {
        event.preventDefault();
        visit(form, item, '/manage/banks', {
            name: form.data.name,
            color: form.data.color || null,
            kind: form.data.kind,
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
                    <TextInput
                        name="name"
                        maxLength={255}
                        value={form.data.name}
                        onChange={(value) => setField('name', value)}
                    />
                </FormField>
                <ChoiceGroup
                    legend="Tipo"
                    name="bank-kind"
                    value={form.data.kind}
                    options={[
                        { value: 'account', label: 'Conta' },
                        { value: 'investment', label: 'Investimento' },
                    ]}
                    onChange={(value) => setField('kind', value)}
                    error={form.errors.kind}
                />
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
    });
    const setField = useFieldSetter(form);
    const expense = (item?.applies_to ?? form.data.applies_to) === 'expense';

    function submit(event) {
        event.preventDefault();
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
                    <TextInput
                        name="name"
                        maxLength={255}
                        value={form.data.name}
                        onChange={(value) => setField('name', value)}
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
                            }), 'applies_to', 'nature')}
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
    const activeBanks = banks.filter((bank) => !bank.archived && bank.kind !== 'investment');
    const bankMissing = Boolean(form.data.bank_id)
        && !activeBanks.some((bank) => String(bank.id) === form.data.bank_id);
    const archivedBank = banks.find((bank) => String(bank.id) === form.data.bank_id);
    const bankOptions = [
        ...(bankMissing ? [bankOption(archivedBank ?? {
            id: form.data.bank_id,
            name: item?.bank_name ?? 'Banco',
            archived: true,
        })] : []),
        ...activeBanks.map((bank) => bankOption(bank)),
    ];

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
                    <TextInput
                        name="name"
                        maxLength={255}
                        value={form.data.name}
                        onChange={(value) => setField('name', value)}
                    />
                </FormField>
                <FormField
                    label="Banco"
                    error={form.errors.bank_id}
                    hint={activeBanks.length === 0
                        ? 'Cadastre um banco ativo antes.'
                        : (bankMissing ? 'O banco atual está arquivado. Escolha um banco ativo.' : undefined)}
                >
                    <Select
                        value={form.data.bank_id}
                        placeholder="Selecione"
                        emptyOption="Selecione"
                        options={bankOptions}
                        onChange={(value) => setField('bank_id', value)}
                    />
                </FormField>
                <FormField label="Dia de fechamento" error={form.errors.closing_day}>
                    <IntegerInput
                        name="closing_day"
                        min={1}
                        max={31}
                        value={form.data.closing_day}
                        onChange={(value) => setField('closing_day', value)}
                    />
                </FormField>
                <FormField label="Dia de vencimento" hint="Opcional." error={form.errors.due_day}>
                    <IntegerInput
                        name="due_day"
                        min={1}
                        max={31}
                        value={form.data.due_day}
                        onChange={(value) => setField('due_day', value)}
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
                    <TextInput
                        name="name"
                        maxLength={255}
                        value={form.data.name}
                        onChange={(value) => setField('name', value)}
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
