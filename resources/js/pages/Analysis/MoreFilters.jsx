import { useEffect, useRef, useState } from 'react';
import Button from '../../components/Button/Button';
import MoneyInput from '../../components/MoneyInput/MoneyInput';
import Select from '../../components/Select/Select';
import { cardOption } from '../../lib/catalogOptions';
import { moneyPayload } from '../../lib/moneyField';
import { useFocusTrap } from '../../lib/dialog';

const SORTS = [
    { value: 'date_desc', label: 'Data, mais recentes' },
    { value: 'date_asc', label: 'Data, mais antigas' },
    { value: 'amount_desc', label: 'Valor, maior' },
    { value: 'amount_asc', label: 'Valor, menor' },
];

function readAmount(value) {
    const raw = moneyPayload(value);

    if (raw === '') {
        return { ok: true, value: '' };
    }

    if (!/^\d+(\.\d{1,2})?$/.test(raw) || raw === '0' || raw === '0.0' || raw === '0.00') {
        return { ok: false, value: raw };
    }

    return { ok: true, value: raw };
}

function amountAbove(left, right) {
    const [leftWhole, leftFraction = ''] = left.split('.');
    const [rightWhole, rightFraction = ''] = right.split('.');
    const leftInt = leftWhole.replace(/^0+/, '') || '0';
    const rightInt = rightWhole.replace(/^0+/, '') || '0';

    if (leftInt.length !== rightInt.length) {
        return leftInt.length > rightInt.length;
    }

    if (leftInt !== rightInt) {
        return leftInt > rightInt;
    }

    return leftFraction.padEnd(2, '0') > rightFraction.padEnd(2, '0');
}

export default function MoreFilters({ open, filters, cards, onClose, onApply, onClear }) {
    const dialogRef = useFocusTrap(open, onClose);
    const filtersRef = useRef(filters);
    const [draft, setDraft] = useState(filters);
    const [error, setError] = useState('');

    filtersRef.current = filters;

    useEffect(() => {
        if (!open) {
            return;
        }

        const current = filtersRef.current;
        setDraft({
            type: current.type ?? '',
            status: current.status ?? '',
            min_amount: current.min_amount ? String(current.min_amount) : '',
            max_amount: current.max_amount ? String(current.max_amount) : '',
            card_id: current.card_id ? String(current.card_id) : '',
            sort: current.sort ?? 'date_desc',
        });
        setError('');
    }, [open]);

    if (!open) {
        return null;
    }

    function apply() {
        const min = readAmount(draft.min_amount);
        const max = readAmount(draft.max_amount);

        if (!min.ok) {
            setError('Informe um valor mínimo maior que zero.');
            return;
        }

        if (!max.ok) {
            setError('Informe um valor máximo maior que zero.');
            return;
        }

        if (min.value && max.value && amountAbove(min.value, max.value)) {
            setError('O valor máximo não pode ser menor que o mínimo.');
            return;
        }

        onApply({
            type: draft.type,
            status: draft.status,
            min_amount: min.value,
            max_amount: max.value,
            card_id: draft.card_id,
            sort: draft.sort,
        });
    }

    return (
        <div className="popover popover--filters" role="dialog" aria-label="Mais filtros" ref={dialogRef} tabIndex={-1}>
            <p className="popover__title">Mais filtros</p>
            <div className="popover__stack">
                <div className="filter-field">
                    <span>Tipo</span>
                    <div className="tx-segment" role="radiogroup" aria-label="Tipo">
                        {[
                            { value: '', label: 'Todos' },
                            { value: 'income', label: 'Receita' },
                            { value: 'expense', label: 'Despesa' },
                            { value: 'reserve', label: 'Reserva' },
                        ].map((option) => (
                            <button
                                key={option.label}
                                type="button"
                                role="radio"
                                aria-checked={draft.type === option.value}
                                className={draft.type === option.value ? 'tx-segment__option tx-segment__option--on' : 'tx-segment__option'}
                                onClick={() => setDraft((current) => ({ ...current, type: option.value }))}
                            >
                                {option.label}
                            </button>
                        ))}
                    </div>
                </div>
                <div className="filter-field">
                    <span>Status</span>
                    <div className="tx-segment" role="radiogroup" aria-label="Status">
                        {[
                            { value: '', label: 'Todos' },
                            { value: 'paid', label: 'Pagas' },
                            { value: 'pending', label: 'Pendentes' },
                        ].map((option) => (
                            <button
                                key={option.label}
                                type="button"
                                role="radio"
                                aria-checked={draft.status === option.value}
                                className={draft.status === option.value ? 'tx-segment__option tx-segment__option--on' : 'tx-segment__option'}
                                onClick={() => setDraft((current) => ({ ...current, status: option.value }))}
                            >
                                {option.label}
                            </button>
                        ))}
                    </div>
                </div>
                <div className="popover__dates">
                    <label className="filter-field">
                        <span>Valor mínimo</span>
                        <MoneyInput
                            value={draft.min_amount}
                            onChange={(value) => setDraft((current) => ({ ...current, min_amount: value }))}
                        />
                    </label>
                    <label className="filter-field">
                        <span>Valor máximo</span>
                        <MoneyInput
                            value={draft.max_amount}
                            onChange={(value) => setDraft((current) => ({ ...current, max_amount: value }))}
                        />
                    </label>
                </div>
                <label className="filter-field">
                    <span>Cartão</span>
                    <Select
                        value={draft.card_id}
                        emptyOption="Todos"
                        placeholder="Todos"
                        options={cards.map((card) => cardOption(card))}
                        onChange={(value) => setDraft((current) => ({ ...current, card_id: value }))}
                    />
                </label>
                <label className="filter-field">
                    <span>Ordenar por</span>
                    <Select
                        value={draft.sort}
                        options={SORTS}
                        onChange={(value) => setDraft((current) => ({ ...current, sort: value }))}
                    />
                </label>
            </div>
            {error ? <p className="popover__error" role="alert">{error}</p> : null}
            <div className="popover__footer">
                <Button variant="secondary" onClick={onClear}>Limpar</Button>
                <Button onClick={apply}>Aplicar</Button>
            </div>
        </div>
    );
}
