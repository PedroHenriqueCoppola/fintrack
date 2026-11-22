import { useEffect, useRef, useState } from 'react';
import { ChevronDown } from 'lucide-react';
import Button from '../../components/Button/Button';
import { blurMoneyInput, formatMoneyInput, parseMoneyInput } from '../../lib/format';
import { useFocusTrap } from '../../lib/dialog';

const SORTS = [
    { value: 'date_desc', label: 'Data, mais recentes' },
    { value: 'date_asc', label: 'Data, mais antigas' },
    { value: 'amount_desc', label: 'Valor, maior' },
    { value: 'amount_asc', label: 'Valor, menor' },
];

function readAmount(value) {
    const raw = String(value ?? '').trim();

    if (raw === '') {
        return { ok: true, value: '' };
    }

    const parsed = parseMoneyInput(raw.replace(/\s/g, '').replace(/^R\$/i, ''));

    if (!/^\d+(\.\d{1,2})?$/.test(parsed) || Number(parsed) <= 0) {
        return { ok: false, value: parsed };
    }

    return { ok: true, value: parsed };
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
            min_amount: current.min_amount ? formatMoneyInput(current.min_amount) : '',
            max_amount: current.max_amount ? formatMoneyInput(current.max_amount) : '',
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

        if (min.value && max.value && Number(min.value) > Number(max.value)) {
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
                        <input
                            className="filter-money"
                            inputMode="decimal"
                            autoComplete="off"
                            value={draft.min_amount}
                            onChange={(event) => setDraft((current) => ({ ...current, min_amount: event.target.value }))}
                            onBlur={() => {
                                const formatted = blurMoneyInput(draft.min_amount);
                                if (formatted !== null) {
                                    setDraft((current) => ({ ...current, min_amount: formatted }));
                                }
                            }}
                        />
                    </label>
                    <label className="filter-field">
                        <span>Valor máximo</span>
                        <input
                            className="filter-money"
                            inputMode="decimal"
                            autoComplete="off"
                            value={draft.max_amount}
                            onChange={(event) => setDraft((current) => ({ ...current, max_amount: event.target.value }))}
                            onBlur={() => {
                                const formatted = blurMoneyInput(draft.max_amount);
                                if (formatted !== null) {
                                    setDraft((current) => ({ ...current, max_amount: formatted }));
                                }
                            }}
                        />
                    </label>
                </div>
                <label className="filter-field">
                    <span>Cartão</span>
                    <div className="tx-select">
                        <select
                            value={draft.card_id}
                            onChange={(event) => setDraft((current) => ({ ...current, card_id: event.target.value }))}
                        >
                            <option value="">Todos</option>
                            {cards.map((card) => (
                                <option key={card.id} value={card.id}>{card.name}</option>
                            ))}
                        </select>
                        <ChevronDown aria-hidden="true" strokeWidth={1.75} />
                    </div>
                </label>
                <label className="filter-field">
                    <span>Ordenar por</span>
                    <div className="tx-select">
                        <select
                            value={draft.sort}
                            onChange={(event) => setDraft((current) => ({ ...current, sort: event.target.value }))}
                        >
                            {SORTS.map((option) => (
                                <option key={option.value} value={option.value}>{option.label}</option>
                            ))}
                        </select>
                        <ChevronDown aria-hidden="true" strokeWidth={1.75} />
                    </div>
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
