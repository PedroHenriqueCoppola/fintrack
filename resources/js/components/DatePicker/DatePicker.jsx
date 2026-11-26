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
    const [place, setPlace] = useState(null);
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
        if (!open || !rootRef.current || !popoverRef.current) {
            return undefined;
        }

        function measure() {
            const rect = rootRef.current.getBoundingClientRect();
            const pop = popoverRef.current;

            if (!pop) {
                return;
            }

            const margin = 8;
            const width = Math.min(pop.offsetWidth || 360, window.innerWidth - margin * 2);
            const height = pop.offsetHeight || 320;
            const spaceBelow = window.innerHeight - rect.bottom;
            const spaceAbove = rect.top;
            const openUp = spaceBelow < height + 12 && spaceAbove > spaceBelow;
            let top = openUp ? rect.top - height - 6 : rect.bottom + 6;
            let left = rect.right - width;
            left = Math.max(margin, Math.min(left, window.innerWidth - width - margin));
            top = Math.max(margin, Math.min(top, window.innerHeight - height - margin));
            setPlace({ top, left, width });
        }

        measure();
        window.addEventListener('resize', measure);
        window.addEventListener('scroll', measure, true);

        return () => {
            window.removeEventListener('resize', measure);
            window.removeEventListener('scroll', measure, true);
        };
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

        function onPointerDown(event) {
            if (rootRef.current?.contains(event.target)) {
                return;
            }

            close(false);
        }

        document.addEventListener('pointerdown', onPointerDown);

        return () => document.removeEventListener('pointerdown', onPointerDown);
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
                        className="date-picker__popover"
                        style={place
                            ? { top: place.top, left: place.left, width: place.width }
                            : { top: -9999, left: 0 }}
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
