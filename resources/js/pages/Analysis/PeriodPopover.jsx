import { useEffect, useState } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import Button from '../../components/Button/Button';
import {
    MONTH_HEADINGS,
    WEEKDAY_INITIALS,
    formatDate,
    isoFromDate,
    maskDate,
    parseBrDate,
    parseIsoDate,
    todayIso,
} from '../../lib/format';
import { useFocusTrap } from '../../lib/dialog';

function cellsFor(monthDate) {
    const year = monthDate.getFullYear();
    const month = monthDate.getMonth();
    const lead = new Date(year, month, 1).getDay();
    const days = new Date(year, month + 1, 0).getDate();
    const cells = [];

    for (let index = 0; index < lead; index += 1) {
        const date = new Date(year, month, index - lead + 1);
        cells.push({ iso: isoFromDate(date), label: date.getDate(), outside: true });
    }

    for (let day = 1; day <= days; day += 1) {
        cells.push({ iso: isoFromDate(new Date(year, month, day)), label: day, outside: false });
    }

    let extra = 1;

    while (cells.length % 7 !== 0) {
        const date = new Date(year, month + 1, extra);
        cells.push({ iso: isoFromDate(date), label: date.getDate(), outside: true });
        extra += 1;
    }

    return cells;
}

function shiftIso(iso, days) {
    const date = parseIsoDate(iso);

    if (!date) {
        return iso;
    }

    date.setDate(date.getDate() + days);

    return isoFromDate(date);
}

export default function PeriodPopover({ open, initialFrom, initialTo, onClose, onApply }) {
    const dialogRef = useFocusTrap(open, onClose);
    const start = parseIsoDate(initialFrom) ?? parseIsoDate(todayIso());
    const [fromText, setFromText] = useState(formatDate(initialFrom || todayIso()));
    const [toText, setToText] = useState(formatDate(initialTo || initialFrom || todayIso()));
    const [fromIso, setFromIso] = useState(initialFrom || todayIso());
    const [toIso, setToIso] = useState(initialTo || initialFrom || todayIso());
    const [anchor, setAnchor] = useState(initialFrom || todayIso());
    const [finished, setFinished] = useState(true);
    const [month, setMonth] = useState(new Date(start.getFullYear(), start.getMonth(), 1));
    const [error, setError] = useState('');

    useEffect(() => {
        if (!open) {
            return;
        }

        const from = initialFrom || todayIso();
        const to = initialTo || from;
        const parsed = parseIsoDate(from) ?? new Date();
        setFromText(formatDate(from));
        setToText(formatDate(to));
        setFromIso(from);
        setToIso(to);
        setAnchor(from);
        setFinished(true);
        setMonth(new Date(parsed.getFullYear(), parsed.getMonth(), 1));
        setError('');
    }, [open, initialFrom, initialTo]);

    if (!open) {
        return null;
    }

    function selectDay(iso) {
        const parsed = parseIsoDate(iso);

        if (!finished) {
            const nextFrom = iso < anchor ? iso : anchor;
            const nextTo = iso < anchor ? anchor : iso;
            setFromIso(nextFrom);
            setToIso(nextTo);
            setFromText(formatDate(nextFrom));
            setToText(formatDate(nextTo));
            setFinished(true);
        } else {
            setAnchor(iso);
            setFromIso(iso);
            setToIso(iso);
            setFromText(formatDate(iso));
            setToText(formatDate(iso));
            setFinished(false);
        }

        setError('');

        if (parsed) {
            setMonth(new Date(parsed.getFullYear(), parsed.getMonth(), 1));
        }
    }

    function changeText(which, value) {
        const masked = maskDate(value);

        if (which === 'from') {
            setFromText(masked);
        } else {
            setToText(masked);
        }

        const parsed = parseBrDate(masked);

        if (!parsed) {
            return;
        }

        const iso = isoFromDate(parsed);

        if (which === 'from') {
            setFromIso(iso);
            setAnchor(iso);
            setMonth(new Date(parsed.getFullYear(), parsed.getMonth(), 1));
        } else {
            setToIso(iso);
        }

        setFinished(true);
        setError('');
    }

    function apply() {
        const from = parseBrDate(fromText);
        const to = parseBrDate(toText);

        if (!from || !to) {
            setError('Informe as datas no formato dd/mm/aaaa.');
            return;
        }

        const fromValue = isoFromDate(from);
        const toValue = isoFromDate(to);

        if (fromValue > toValue) {
            setError('A data inicial não pode ser maior que a final.');
            return;
        }

        onApply(fromValue, toValue);
    }

    function onDayKeyDown(event, iso) {
        const steps = {
            ArrowLeft: -1,
            ArrowRight: 1,
            ArrowUp: -7,
            ArrowDown: 7,
        };
        const step = steps[event.key];

        if (!step) {
            return;
        }

        event.preventDefault();
        const next = shiftIso(iso, step);
        const parsed = parseIsoDate(next);

        if (parsed) {
            setMonth(new Date(parsed.getFullYear(), parsed.getMonth(), 1));
        }

        requestAnimationFrame(() => {
            dialogRef.current?.querySelector(`[data-date="${next}"]`)?.focus();
        });
    }

    const cells = cellsFor(month);

    return (
        <div
            className="popover popover--calendar"
            role="dialog"
            aria-label="Período personalizado"
            ref={dialogRef}
            tabIndex={-1}
        >
            <div className="popover__title-row">
                <p className="popover__title">Período personalizado</p>
                <div className="calendar-nav">
                    <button type="button" aria-label="Mês anterior" onClick={() => setMonth(new Date(month.getFullYear(), month.getMonth() - 1, 1))}>
                        <ChevronLeft aria-hidden="true" strokeWidth={1.75} />
                    </button>
                    <span>{`${MONTH_HEADINGS[month.getMonth()]} ${month.getFullYear()}`}</span>
                    <button type="button" aria-label="Próximo mês" onClick={() => setMonth(new Date(month.getFullYear(), month.getMonth() + 1, 1))}>
                        <ChevronRight aria-hidden="true" strokeWidth={1.75} />
                    </button>
                </div>
            </div>
            <div className="calendar" role="grid" aria-label="Calendário">
                {WEEKDAY_INITIALS.map((label, index) => (
                    <span key={`${label}-${index}`} className="calendar__weekday" role="columnheader">{label}</span>
                ))}
                {cells.map((cell) => {
                    const edge = cell.iso === fromIso || cell.iso === toIso;
                    const inside = fromIso && toIso && cell.iso > fromIso && cell.iso < toIso;
                    const className = [
                        'calendar__day',
                        cell.outside ? 'calendar__day--outside' : '',
                        inside ? 'calendar__day--range' : '',
                        edge ? 'calendar__day--edge' : '',
                    ].filter(Boolean).join(' ');

                    return (
                        <button
                            key={cell.iso}
                            type="button"
                            role="gridcell"
                            className={className}
                            data-date={cell.iso}
                            aria-selected={edge || inside}
                            aria-label={formatDate(cell.iso)}
                            onClick={() => selectDay(cell.iso)}
                            onKeyDown={(event) => onDayKeyDown(event, cell.iso)}
                        >
                            {cell.label}
                        </button>
                    );
                })}
            </div>
            <div className="popover__dates">
                <label className="filter-field">
                    <span>De</span>
                    <input
                        inputMode="numeric"
                        autoComplete="off"
                        placeholder="dd/mm/aaaa"
                        maxLength={10}
                        value={fromText}
                        onChange={(event) => changeText('from', event.target.value)}
                    />
                </label>
                <label className="filter-field">
                    <span>Até</span>
                    <input
                        inputMode="numeric"
                        autoComplete="off"
                        placeholder="dd/mm/aaaa"
                        maxLength={10}
                        value={toText}
                        onChange={(event) => changeText('to', event.target.value)}
                    />
                </label>
            </div>
            {error ? <p className="popover__error" role="alert">{error}</p> : null}
            <div className="popover__footer">
                <Button variant="secondary" onClick={onClose}>Cancelar</Button>
                <Button onClick={apply}>Aplicar</Button>
            </div>
        </div>
    );
}
