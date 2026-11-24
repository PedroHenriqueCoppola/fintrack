import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Calendar } from 'lucide-react';
import { formatDate } from '../../lib/format';
import PeriodPopover from '../Analysis/PeriodPopover';

export const PERIODS = [
    { key: 'current_month', label: 'Mês atual' },
    { key: 'previous_month', label: 'Mês anterior' },
    { key: 'quarter', label: 'Trimestre' },
    { key: 'semester', label: 'Semestre' },
    { key: 'year', label: 'Ano' },
    { key: 'all', label: 'Todo o período' },
    { key: 'custom', label: 'Personalizado' },
];

export function goalParams(next, { keepStatus = false } = {}) {
    const params = {};
    const period = next.period || 'current_month';

    if (period !== 'current_month') {
        params.period = period;
    }

    if (period === 'custom') {
        params.period = 'custom';
        params.from = next.from;
        params.to = next.to;
    }

    if (keepStatus && next.status && next.status !== 'active') {
        params.status = next.status;
    }

    return params;
}

export function visitGoals(path, next, options = {}) {
    router.get(path, goalParams(next, options), {
        preserveScroll: true,
        preserveState: true,
    });
}

export default function PeriodBar({ path, filters, period, keepStatus = false }) {
    const [calendarOpen, setCalendarOpen] = useState(false);
    const current = {
        period: filters.period,
        from: filters.from,
        to: filters.to,
        status: filters.status,
    };

    function choosePeriod(key) {
        if (key === 'custom') {
            setCalendarOpen(true);
            return;
        }

        setCalendarOpen(false);
        visitGoals(path, { ...current, period: key, from: null, to: null }, { keepStatus });
    }

    return (
        <div className="goals-tools">
            {calendarOpen ? (
                <button
                    type="button"
                    className="popover-backdrop"
                    aria-label="Fechar calendário"
                    onClick={() => setCalendarOpen(false)}
                />
            ) : null}
            <div className="period-bar">
                <div className="period-seg" role="group" aria-label="Período">
                    {PERIODS.map((item) => (
                        <button
                            key={item.key}
                            type="button"
                            aria-pressed={period.key === item.key}
                            onClick={() => choosePeriod(item.key)}
                        >
                            {item.label}
                        </button>
                    ))}
                </div>
                <button type="button" className="period-range" onClick={() => choosePeriod('custom')}>
                    <Calendar aria-hidden="true" strokeWidth={1.75} />
                    {period.from && period.to ? (
                        <>
                            <span>{formatDate(period.from)}</span>
                            <i>–</i>
                            <span>{formatDate(period.to)}</span>
                        </>
                    ) : (
                        <span>Todo o período</span>
                    )}
                </button>
                <PeriodPopover
                    open={calendarOpen}
                    initialFrom={period.from}
                    initialTo={period.to}
                    onClose={() => setCalendarOpen(false)}
                    onApply={(from, to) => {
                        setCalendarOpen(false);
                        visitGoals(path, { ...current, period: 'custom', from, to }, { keepStatus });
                    }}
                />
            </div>
        </div>
    );
}
