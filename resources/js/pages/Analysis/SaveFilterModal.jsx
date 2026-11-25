import { useForm } from '@inertiajs/react';
import Button from '../../components/Button/Button';
import FormField from '../../components/FormField/FormField';
import Modal from '../../components/Modal/Modal';
import TextInput from '../../components/TextInput/TextInput';
import { useFieldSetter } from '../../lib/fields';

function storedFilters(filters, period, includePeriod) {
    const payload = {};

    if (filters.type) payload.type = filters.type;
    if (filters.status) payload.status = filters.status;
    if (filters.category_id) payload.category_id = Number(filters.category_id);
    if (filters.bank_id) payload.bank_id = Number(filters.bank_id);
    if (filters.card_id) payload.card_id = Number(filters.card_id);
    if (filters.payment_method_id) payload.payment_method_id = Number(filters.payment_method_id);
    if (filters.min_amount) payload.min_amount = filters.min_amount;
    if (filters.max_amount) payload.max_amount = filters.max_amount;
    if (filters.q) payload.q = filters.q;
    if (filters.sort && filters.sort !== 'date_desc') payload.sort = filters.sort;

    if (includePeriod) {
        payload.period = period.key;

        if (period.key === 'custom') {
            payload.from = period.from;
            payload.to = period.to;
        }
    }

    return payload;
}

export default function SaveFilterModal({ filters, period, chips, onClose }) {
    const form = useForm({
        name: '',
        include_period: false,
        filters: {},
    });
    const setField = useFieldSetter(form);

    function submit(event) {
        event.preventDefault();
        form.transform((data) => ({
            name: data.name,
            include_period: data.include_period,
            filters: storedFilters(filters, period, data.include_period),
        }));
        form.post('/saved-filters', {
            preserveScroll: true,
            preserveState: true,
            only: ['savedFilters'],
            onSuccess: () => onClose(),
        });
    }

    return (
        <Modal
            open
            className="modal--filter"
            title="Salvar filtro"
            onClose={onClose}
            footer={(
                <>
                    <Button variant="secondary" onClick={onClose} disabled={form.processing}>Cancelar</Button>
                    <Button type="submit" form="save-filter-form" disabled={form.processing}>
                        {form.processing ? 'Salvando…' : 'Salvar'}
                    </Button>
                </>
            )}
        >
            <form id="save-filter-form" className="tx-form" noValidate onSubmit={submit}>
                <p className="tx-lead">Dê um nome para aplicar esses filtros com um clique.</p>
                <FormField label="Nome do filtro" error={form.errors.name}>
                    <TextInput
                        maxLength={40}
                        value={form.data.name}
                        onChange={(value) => setField('name', value, 'filters')}
                    />
                </FormField>
                <div>
                    <p className="save-filter__label">Filtros incluídos</p>
                    {chips.length === 0 ? (
                        <p className="tx-hint">Nenhum filtro além do período.</p>
                    ) : (
                        <div className="filter-chips filter-chips--inline">
                            {chips.map((chip) => (
                                <span key={chip.key} className="filter-chip">{chip.label}</span>
                            ))}
                        </div>
                    )}
                </div>
                <label className="save-filter__check">
                    <input
                        type="checkbox"
                        checked={form.data.include_period}
                        onChange={(event) => setField('include_period', event.target.checked, 'filters')}
                    />
                    <span>{`Guardar também o período (${period.label})`}</span>
                </label>
                {form.errors.filters ? <p className="tx-inline-error" role="alert">{form.errors.filters}</p> : null}
            </form>
        </Modal>
    );
}
