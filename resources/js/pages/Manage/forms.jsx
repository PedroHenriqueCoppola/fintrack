import { useForm } from '@inertiajs/react';
import Button from '../../components/Button/Button';
import FormField from '../../components/FormField/FormField';
import Modal from '../../components/Modal/Modal';
import { formatMoneyInput, parseMoneyInput } from '../../lib/format';
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
            <Button variant="quiet" onClick={onClose} disabled={processing}>Cancelar</Button>
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
                        onChange={(event) => form.setData('name', event.target.value)}
                    />
                </FormField>
                <ChoiceGroup
                    legend="Cor"
                    name="bank-color"
                    value={form.data.color}
                    options={colorOptions(colors)}
                    onChange={(value) => form.setData('color', value)}
                    error={form.errors.color}
                />
            </form>
        </Modal>
    );
}

export function PocketForm({ item, banks, colors, pocketTypes, onClose }) {
    const form = useForm({
        name: item?.name ?? '',
        bank_id: item?.bank_id ? String(item.bank_id) : '',
        type: item?.type ?? '',
        color: item?.color ?? '',
    });
    const activeBanks = banks.filter((bank) => !bank.archived);
    const bankMissing = Boolean(form.data.bank_id)
        && !activeBanks.some((bank) => String(bank.id) === form.data.bank_id);

    function submit(event) {
        event.preventDefault();
        visit(form, item, '/manage/pockets', {
            name: form.data.name,
            bank_id: form.data.bank_id || null,
            type: form.data.type || null,
            color: form.data.color || null,
        }, onClose);
    }

    return (
        <Modal
            open
            title={item ? 'Editar caixinha' : 'Nova caixinha'}
            onClose={onClose}
            footer={<FormFooter formId="pocket-form" processing={form.processing} onClose={onClose} />}
        >
            <form id="pocket-form" className="manage-form" noValidate onSubmit={submit}>
                <FormField label="Nome" error={form.errors.name}>
                    <input
                        type="text"
                        name="name"
                        autoComplete="off"
                        value={form.data.name}
                        onChange={(event) => form.setData('name', event.target.value)}
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
                        onChange={(event) => form.setData('bank_id', event.target.value)}
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
                <ChoiceGroup
                    legend="Tipo"
                    name="pocket-type"
                    value={form.data.type}
                    options={[{ value: '', label: 'Nenhum' }, ...pocketTypes]}
                    onChange={(value) => form.setData('type', value)}
                    error={form.errors.type}
                />
                <ChoiceGroup
                    legend="Cor"
                    name="pocket-color"
                    value={form.data.color}
                    options={colorOptions(colors)}
                    onChange={(value) => form.setData('color', value)}
                    error={form.errors.color}
                />
            </form>
        </Modal>
    );
}

export function CategoryForm({ item, pockets, colors, icons, categoryTypes, natures, onClose }) {
    const form = useForm({
        name: item?.name ?? '',
        applies_to: item?.applies_to ?? '',
        nature: item?.nature ?? '',
        icon: item?.icon ?? '',
        color: item?.color ?? '',
        pocket_id: item?.pocket_id ? String(item.pocket_id) : '',
        initial_balance: item && item.applies_to === 'expense' ? formatMoneyInput(item.initial_balance) : '',
    });
    const expense = (item?.applies_to ?? form.data.applies_to) === 'expense';
    const activePockets = pockets.filter((pocket) => !pocket.archived);
    const pocketMissing = Boolean(form.data.pocket_id)
        && !activePockets.some((pocket) => String(pocket.id) === form.data.pocket_id);

    function submit(event) {
        event.preventDefault();
        const appliesTo = item ? item.applies_to : form.data.applies_to;
        const payload = {
            name: form.data.name,
            icon: form.data.icon || null,
            color: form.data.color || null,
            pocket_id: form.data.pocket_id || null,
        };

        if (!item) {
            payload.applies_to = form.data.applies_to;
            if (form.data.applies_to === 'expense') {
                payload.nature = form.data.nature || null;
            }
        }

        if (appliesTo === 'expense') {
            const parsed = parseMoneyInput(form.data.initial_balance);
            payload.initial_balance = parsed === '' ? null : parsed;
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
                <FormField label="Nome" error={form.errors.name}>
                    <input
                        type="text"
                        name="name"
                        autoComplete="off"
                        value={form.data.name}
                        onChange={(event) => form.setData('name', event.target.value)}
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
                    <ChoiceGroup
                        legend="Tipo"
                        name="category-type"
                        value={form.data.applies_to}
                        options={categoryTypes}
                        onChange={(value) => form.setData((data) => ({
                            ...data,
                            applies_to: value,
                            nature: value === 'expense' ? data.nature : '',
                            initial_balance: value === 'expense' ? data.initial_balance : '',
                        }))}
                        error={form.errors.applies_to}
                    />
                )}
                {!item && expense ? (
                    <ChoiceGroup
                        legend="Natureza"
                        name="category-nature"
                        value={form.data.nature}
                        options={natures}
                        onChange={(value) => form.setData('nature', value)}
                        error={form.errors.nature}
                    />
                ) : null}
                <ChoiceGroup
                    legend="Ícone"
                    name="category-icon"
                    value={form.data.icon}
                    options={iconOptions(icons)}
                    onChange={(value) => form.setData('icon', value)}
                    error={form.errors.icon}
                />
                <ChoiceGroup
                    legend="Cor"
                    name="category-color"
                    value={form.data.color}
                    options={colorOptions(colors)}
                    onChange={(value) => form.setData('color', value)}
                    error={form.errors.color}
                />
                <FormField
                    label="Caixinha"
                    error={form.errors.pocket_id}
                    hint={pocketMissing ? 'A caixinha atual está arquivada. Escolha outra ou nenhuma.' : 'Opcional.'}
                >
                    <select
                        name="pocket_id"
                        value={form.data.pocket_id}
                        onChange={(event) => form.setData('pocket_id', event.target.value)}
                    >
                        <option value="">Nenhuma</option>
                        {pocketMissing ? (
                            <option value={form.data.pocket_id}>{item?.pocket_name} (arquivada)</option>
                        ) : null}
                        {activePockets.map((pocket) => (
                            <option key={pocket.id} value={String(pocket.id)}>{pocket.name}</option>
                        ))}
                    </select>
                </FormField>
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
                            onChange={(event) => form.setData('initial_balance', event.target.value)}
                        />
                    </FormField>
                ) : null}
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
                        onChange={(event) => form.setData('name', event.target.value)}
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
                        onChange={(event) => form.setData('bank_id', event.target.value)}
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
                        onChange={(event) => form.setData('closing_day', event.target.value)}
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
                        onChange={(event) => form.setData('due_day', event.target.value)}
                    />
                </FormField>
                <ChoiceGroup
                    legend="Cor"
                    name="card-color"
                    value={form.data.color}
                    options={colorOptions(colors)}
                    onChange={(value) => form.setData('color', value)}
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
                        onChange={(event) => form.setData('name', event.target.value)}
                    />
                </FormField>
                <FormField label="Exige cartão" error={form.errors.requires_card}>
                    <input
                        type="checkbox"
                        name="requires_card"
                        checked={form.data.requires_card}
                        onChange={(event) => form.setData('requires_card', event.target.checked)}
                    />
                </FormField>
            </form>
        </Modal>
    );
}
