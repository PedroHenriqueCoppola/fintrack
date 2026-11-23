import { useForm } from '@inertiajs/react';
import Button from '../../components/Button/Button';
import FormField from '../../components/FormField/FormField';
import Modal from '../../components/Modal/Modal';
import MonthField from '../../components/MonthField/MonthField';
import { useFieldSetter } from '../../lib/fields';
import { blurMoneyInput, formatMoneyInput, useToday } from '../../lib/format';
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
        return 'Esta meta começa do zero, a partir do saldo de hoje.';
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
        target_amount: goal ? formatMoneyInput(goal.target_amount) : '',
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
                    <input
                        type="text"
                        autoComplete="off"
                        maxLength={60}
                        value={form.data.name}
                        onChange={(event) => setField('name', event.target.value)}
                    />
                </FormField>
                <FormField label="Valor alvo" error={form.errors.target_amount}>
                    <input
                        className="goal-money"
                        inputMode="decimal"
                        autoComplete="off"
                        disabled={frozen}
                        value={form.data.target_amount}
                        onChange={(event) => setField('target_amount', event.target.value)}
                        onBlur={() => {
                            const formatted = blurMoneyInput(form.data.target_amount);
                            if (formatted !== null) {
                                setField('target_amount', formatted);
                            }
                        }}
                    />
                </FormField>
                <FormField label="Categoria de Reserva" error={form.errors.category_id} hint={categoryHint}>
                    <select
                        value={form.data.category_id}
                        disabled={editing || frozen}
                        onChange={(event) => setField('category_id', event.target.value)}
                    >
                        <option value="">Escolha</option>
                        {categories.map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.archived ? `${category.name} (arquivada)` : category.name}
                            </option>
                        ))}
                    </select>
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
                    <input
                        type="text"
                        autoComplete="off"
                        maxLength={255}
                        value={form.data.notes}
                        onChange={(event) => setField('notes', event.target.value)}
                    />
                </FormField>
            </form>
        </Modal>
    );
}
