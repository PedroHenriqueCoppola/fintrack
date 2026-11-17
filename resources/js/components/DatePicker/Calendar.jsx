import { useEffect, useRef } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import {
    MONTH_HEADINGS,
    WEEKDAY_INITIALS,
    formatDate,
    isoFromDate,
    parseIsoDate,
} from '../../lib/format';

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

function isBlocked(iso, min, max) {
    if (min && iso < min) {
        return true;
    }

    if (max && iso > max) {
        return true;
    }

    return false;
}

function stepToEnabled(iso, step, min, max) {
    const direction = step > 0 ? 1 : -1;
    let cursor = iso;
    let moved = 0;
    let hops = 0;

    while (moved < Math.abs(step) && hops < 400) {
        const next = shiftIso(cursor, direction);
        hops += 1;

        if (isBlocked(next, min, max)) {
            return cursor;
        }

        cursor = next;
        moved += 1;
    }

    return cursor;
}

export function CalendarNav({ month, onMonthChange }) {
    return (
        <div className="calendar-nav">
            <button
                type="button"
                aria-label="Mês anterior"
                onClick={() => onMonthChange(new Date(month.getFullYear(), month.getMonth() - 1, 1))}
            >
                <ChevronLeft aria-hidden="true" strokeWidth={1.75} />
            </button>
            <span>{`${MONTH_HEADINGS[month.getMonth()]} ${month.getFullYear()}`}</span>
            <button
                type="button"
                aria-label="Próximo mês"
                onClick={() => onMonthChange(new Date(month.getFullYear(), month.getMonth() + 1, 1))}
            >
                <ChevronRight aria-hidden="true" strokeWidth={1.75} />
            </button>
        </div>
    );
}

export default function Calendar({
    mode = 'single',
    selected = '',
    from = '',
    to = '',
    month,
    onMonthChange,
    onSelect,
    min,
    max,
    showNav = true,
}) {
    const gridRef = useRef(null);
    const pendingFocus = useRef(null);
    const cells = cellsFor(month);

    useEffect(() => {
        if (!pendingFocus.current || !gridRef.current) {
            return;
        }

        const target = pendingFocus.current;
        pendingFocus.current = null;
        gridRef.current.querySelector(`[data-date="${target}"]`)?.focus();
    });

    function onDayKeyDown(event, iso) {
        const steps = {
            ArrowLeft: -1,
            ArrowRight: 1,
            ArrowUp: -7,
            ArrowDown: 7,
        };
        const step = steps[event.key];

        if (event.key === 'Enter') {
            event.preventDefault();
            onSelect(iso);
            return;
        }

        if (!step) {
            return;
        }

        event.preventDefault();
        const next = stepToEnabled(iso, step, min, max);

        if (!next || next === iso) {
            return;
        }

        const parsed = parseIsoDate(next);

        if (parsed) {
            onMonthChange(new Date(parsed.getFullYear(), parsed.getMonth(), 1));
        }

        pendingFocus.current = next;
    }

    return (
        <div>
            {showNav ? <CalendarNav month={month} onMonthChange={onMonthChange} /> : null}
            <div className="calendar" role="grid" aria-label="Calendário" ref={gridRef}>
                {WEEKDAY_INITIALS.map((label, index) => (
                    <span key={`${label}-${index}`} className="calendar__weekday" role="columnheader">{label}</span>
                ))}
                {cells.map((cell) => {
                    const blocked = isBlocked(cell.iso, min, max);
                    const edge = mode === 'range'
                        ? cell.iso === from || cell.iso === to
                        : cell.iso === selected;
                    const inside = mode === 'range' && from && to && cell.iso > from && cell.iso < to;
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
                            data-selected={edge ? 'true' : undefined}
                            aria-selected={edge || inside}
                            aria-label={formatDate(cell.iso)}
                            disabled={blocked}
                            onClick={() => onSelect(cell.iso)}
                            onKeyDown={(event) => onDayKeyDown(event, cell.iso)}
                        >
                            {cell.label}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
