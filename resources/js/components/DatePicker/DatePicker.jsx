import { useEffect, useId, useLayoutEffect, useRef, useState } from 'react';
import { Calendar as CalendarIcon } from 'lucide-react';
import { useFocusTrap } from '../../lib/dialog';
import {
    formatDate,
    isoFromDate,
    maskDate,
    parseBrDate,
    parseIsoDate,
    useToday,
} from '../../lib/format';
import Calendar from './Calendar';
import './DatePicker.css';

export default function DatePicker({
    label,
    value,
    onChange,
    max,
    min,
    error,
    id,
}) {
    const generatedId = useId();
    const fieldId = id || generatedId;
    const errorId = error ? `${fieldId}-error` : undefined;
    const today = useToday();
    const rootRef = useRef(null);
    const restoreFocus = useRef(true);
    const [open, setOpen] = useState(false);
    const [dropUp, setDropUp] = useState(false);
    const parsed = parseBrDate(value);
    const selected = parsed ? isoFromDate(parsed) : '';
    const anchor = parseIsoDate(selected) || parseIsoDate(max || today) || parseIsoDate(today);
    const [month, setMonth] = useState(() => new Date(anchor.getFullYear(), anchor.getMonth(), 1));
    const focusIso = (selected && (!max || selected <= max) && (!min || selected >= min))
        ? selected
        : (max || today);
    const popoverRef = useFocusTrap(open, () => close(true), {
        capture: true,
        initialFocus: `[data-date="${focusIso}"]`,
        shouldRestore: () => restoreFocus.current,
    });

    useLayoutEffect(() => {
        if (!open || !rootRef.current) {
            return;
        }

        const rect = rootRef.current.getBoundingClientRect();
        const height = popoverRef.current?.offsetHeight ?? 320;
        const spaceBelow = window.innerHeight - rect.bottom;
        const spaceAbove = rect.top;
        setDropUp(spaceBelow < height + 12 && spaceAbove > spaceBelow);
    }, [open, month, popoverRef]);

    function close(restore) {
        restoreFocus.current = restore;
        setOpen(false);
    }

    function toggle() {
        if (open) {
            close(true);
            return;
        }

        const start = parseIsoDate(selected) || parseIsoDate(max || today) || new Date();
        setMonth(new Date(start.getFullYear(), start.getMonth(), 1));
        setOpen(true);
    }

    function choose(iso) {
        onChange(formatDate(iso));
        close(true);
    }

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        function onMouseDown(event) {
            if (rootRef.current?.contains(event.target)) {
                return;
            }

            const backdrop = event.target instanceof Element && event.target.classList.contains('modal-backdrop');
            close(backdrop);

            if (!backdrop) {
                return;
            }

            function stopClick(clickEvent) {
                clickEvent.preventDefault();
                clickEvent.stopPropagation();
                document.removeEventListener('click', stopClick, true);
            }

            document.addEventListener('click', stopClick, true);
        }

        document.addEventListener('mousedown', onMouseDown);

        return () => document.removeEventListener('mousedown', onMouseDown);
    }, [open]);

    return (
        <div className={error ? 'form-field form-field--error' : 'form-field'}>
            <label className="form-field__label" htmlFor={fieldId}>{label}</label>
            <div className="form-field__control date-picker" ref={rootRef}>
                <input
                    id={fieldId}
                    type="text"
                    inputMode="numeric"
                    autoComplete="off"
                    placeholder="dd/mm/aaaa"
                    maxLength={10}
                    value={value}
                    aria-invalid={error ? 'true' : undefined}
                    aria-describedby={errorId}
                    onChange={(event) => onChange(maskDate(event.target.value))}
                />
                <button
                    type="button"
                    className="date-picker__button"
                    aria-label="Abrir calendário"
                    aria-expanded={open}
                    aria-haspopup="dialog"
                    onClick={toggle}
                >
                    <CalendarIcon aria-hidden="true" strokeWidth={1.75} />
                </button>
                {open ? (
                    <div
                        className={dropUp ? 'date-picker__popover date-picker__popover--up' : 'date-picker__popover date-picker__popover--down'}
                        role="dialog"
                        aria-label={label}
                        ref={popoverRef}
                        tabIndex={-1}
                    >
                        <Calendar
                            mode="single"
                            selected={selected}
                            month={month}
                            onMonthChange={setMonth}
                            onSelect={choose}
                            min={min}
                            max={max}
                        />
                    </div>
                ) : null}
            </div>
            {error ? (
                <p className="form-field__error" id={errorId} role="alert">{error}</p>
            ) : null}
        </div>
    );
}
