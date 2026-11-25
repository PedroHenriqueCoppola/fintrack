import { useForm } from '@inertiajs/react';
import Button from '../../components/Button/Button';
import FormField from '../../components/FormField/FormField';
import Modal from '../../components/Modal/Modal';
import MoneyInput from '../../components/MoneyInput/MoneyInput';
import MonthField from '../../components/MonthField/MonthField';
import Select from '../../components/Select/Select';
import TextInput from '../../components/TextInput/TextInput';
import { categoryOption } from '../../lib/catalogOptions';
import { useFieldSetter } from '../../lib/fields';
import { useToday } from '../../lib/format';
import { moneyPayload } from '../Planning/fields';

function categoryNote(category) {
    if (!category) {
        return null;
    }

    if (category.open_goal?.state === 'completed') {
        const name = category.open_goal.name;

        return `A meta '${name}' está concluída e continuará assim, com o valor de hoje. Esta começa do zero.`;
    }

    if (category.open_goal?.state === 'active') {
        const name = category.open_goal.name;

        return `Já existe a meta '${name}' em andamento nessa categoria. Conclua ou arquive-a antes.`;
    }

    if (category.restarts) {
        return 'Esta meta começa do zero e conta só o que for guardado a partir de hoje.';
    }

    return null;
}

function monthText(value) {
    const match = /^(\d{4})-(\d{2})/.exec(String(value ?? ''));

    return match ? `${match[2]}/${match[1]}` : '';
}

export default function GoalModal({ goal = null, categories, onClose }) {
    const today = useToday();
    const min = `${today.slice(5, 7)}/${today.slice(0, 4)}`;
    const editing = Boolean(goal);
    const frozen = Boolean(goal?.frozen);
    const lockedHint = 'Meta concluída: só o nome e a observação podem mudar.';
    const form = useForm({
        name: goal?.name ?? '',
        target_amount: goal?.target_amount == null ? '' : String(goal.target_amount),
        category_id: goal ? String(goal.category_id) : '',
        deadline: goal?.deadline ? monthText(goal.deadline) : '',
        notes: goal?.notes ?? '',
    });
    const setField = useFieldSetter(form);
    const selected = categories.find((category) => String(category.id) === String(form.data.category_id));
    const categoryHint = frozen
        ? null
        : (editing ? 'Para trocar a categoria, crie outra meta.' : categoryNote(selected));

    function submit(event) {
        event.preventDefault();
        form.transform((data) => ({
            name: data.name.trim(),
            target_amount: moneyPayload(data.target_amount),
            category_id: data.category_id || null,
            deadline: data.deadline || null,
            notes: data.notes.trim() === '' ? null : data.notes.trim(),
        }));
        const visit = { preserveScroll: true, preserveState: true, onSuccess: () => onClose() };

        if (editing) {
            form.put(`/goals/${goal.id}`, visit);
            return;
        }

        form.post('/goals', visit);
    }

    return (
        <Modal
            open
            title={editing ? 'Editar meta' : 'Nova meta'}
            onClose={onClose}
            footer={(
                <>
                    <Button variant="secondary" onClick={onClose} disabled={form.processing}>Cancelar</Button>
                    <Button type="submit" form="goal-form" disabled={form.processing}>
                        {form.processing ? 'Salvando…' : 'Salvar'}
                    </Button>
                </>
            )}
        >
            <form id="goal-form" className="goal-form" noValidate onSubmit={submit}>
                <p className="goal-lead">O progresso vem do que foi guardado e resgatado na categoria.</p>
                {frozen ? <p className="goals-note">{lockedHint}</p> : null}
                <FormField label="Nome" error={form.errors.name}>
                    <TextInput
                        maxLength={60}
                        value={form.data.name}
                        onChange={(value) => setField('name', value)}
                    />
                </FormField>
                <FormField label="Valor alvo" error={form.errors.target_amount}>
                    <MoneyInput
                        value={form.data.target_amount}
                        disabled={frozen}
                        onChange={(value) => setField('target_amount', value)}
                    />
                </FormField>
                <FormField label="Categoria de Reserva" error={form.errors.category_id} hint={categoryHint}>
                    <Select
                        value={form.data.category_id}
                        disabled={editing || frozen}
                        placeholder="Escolha"
                        emptyOption="Escolha"
                        options={categories.map((category) => categoryOption(category))}
                        onChange={(value) => setField('category_id', value)}
                    />
                </FormField>
                <MonthField
                    label="Prazo (opcional)"
                    value={form.data.deadline}
                    min={min}
                    clearable
                    disabled={frozen}
                    error={form.errors.deadline}
                    onChange={(value) => setField('deadline', value)}
                />
                <FormField label="Observação (opcional)" error={form.errors.notes}>
                    <TextInput
                        maxLength={255}
                        value={form.data.notes}
                        onChange={(value) => setField('notes', value)}
                    />
                </FormField>
            </form>
        </Modal>
    );
}
