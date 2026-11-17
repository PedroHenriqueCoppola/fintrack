import { useEffect, useState } from 'react';
import Button from '../../components/Button/Button';
import Calendar, { CalendarNav } from '../../components/DatePicker/Calendar';
import { useFocusTrap } from '../../lib/dialog';
import {
    formatDate,
    isoFromDate,
    maskDate,
    parseBrDate,
    parseIsoDate,
    useToday,
} from '../../lib/format';

export default function PeriodPopover({ open, initialFrom, initialTo, onClose, onApply }) {
    const dialogRef = useFocusTrap(open, onClose);
    const today = useToday();
    const start = parseIsoDate(initialFrom) ?? parseIsoDate(today) ?? new Date();
    const [fromText, setFromText] = useState(formatDate(initialFrom || today));
    const [toText, setToText] = useState(formatDate(initialTo || initialFrom || today));
    const [fromIso, setFromIso] = useState(initialFrom || today);
    const [toIso, setToIso] = useState(initialTo || initialFrom || today);
    const [anchor, setAnchor] = useState(initialFrom || today);
    const [finished, setFinished] = useState(true);
    const [month, setMonth] = useState(new Date(start.getFullYear(), start.getMonth(), 1));
    const [error, setError] = useState('');

    useEffect(() => {
        if (!open) {
            return;
        }

        const from = initialFrom || today;
        const to = initialTo || from;
        const parsed = parseIsoDate(from) ?? parseIsoDate(today);
        setFromText(formatDate(from));
        setToText(formatDate(to));
        setFromIso(from);
        setToIso(to);
        setAnchor(from);
        setFinished(true);
        setMonth(new Date(parsed.getFullYear(), parsed.getMonth(), 1));
        setError('');
    }, [open, initialFrom, initialTo, today]);

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
                <CalendarNav month={month} onMonthChange={setMonth} />
            </div>
            <Calendar
                mode="range"
                showNav={false}
                from={fromIso}
                to={toIso}
                month={month}
                onMonthChange={setMonth}
                onSelect={selectDay}
            />
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
