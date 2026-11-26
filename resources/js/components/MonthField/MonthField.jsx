import { useEffect, useId, useLayoutEffect, useRef, useState } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useFocusTrap } from '../../lib/dialog';
import { MONTH_HEADINGS, useToday } from '../../lib/format';
import './MonthField.css';

const MONTH_SHORT = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

function formatMonth(month, year) {
    return `${String(month).padStart(2, '0')}/${year}`;
}

function parseMonth(text) {
    const match = /^(\d{2})\/(\d{4})$/.exec(String(text ?? ''));

    if (!match) {
        return null;
    }

    const month = parseInt(match[1], 10);
    const year = parseInt(match[2], 10);

    if (month < 1 || month > 12) {
        return null;
    }

    return { month, year };
}

function shiftMonth(text, delta) {
    const parsed = parseMonth(text);

    if (!parsed) {
        return null;
    }

    let month = parsed.month + delta;
    let year = parsed.year;

    while (month < 1) {
        month += 12;
        year -= 1;
    }

    while (month > 12) {
        month -= 12;
        year += 1;
    }

    if (year < 2000 || year > 2100) {
        return null;
    }

    return formatMonth(month, year);
}

function rank(text) {
    const parsed = parseMonth(text);

    if (!parsed) {
        return null;
    }

    return parsed.year * 12 + parsed.month;
}

function allowed(text, min, max) {
    const value = rank(text);

    if (value === null) {
        return false;
    }

    const low = rank(min);
    const high = rank(max);

    if (low !== null && value < low) {
        return false;
    }

    if (high !== null && value > high) {
        return false;
    }

    return true;
}

function yearHasMonth(year, min, max) {
    if (year < 2000 || year > 2100) {
        return false;
    }

    for (let month = 1; month <= 12; month += 1) {
        if (allowed(formatMonth(month, year), min, max)) {
            return true;
        }
    }

    return false;
}

export default function MonthField({ label, value, onChange, error, hint, min, max, clearable = false, disabled = false }) {
    const id = useId();
    const labelId = `${id}-label`;
    const valueId = `${id}-value`;
    const hintId = hint ? `${id}-hint` : undefined;
    const errorId = error ? `${id}-error` : undefined;
    const describedBy = [hintId, errorId].filter(Boolean).join(' ') || undefined;
    const today = useToday();
    const todayYear = Number(today.slice(0, 4));
    const todayMonth = Number(today.slice(5, 7));
    const rootRef = useRef(null);
    const restoreFocus = useRef(true);
    const [text, setText] = useState(value ?? '');
    const [open, setOpen] = useState(false);
    const [place, setPlace] = useState(null);
    const [year, setYear] = useState(() => parseMonth(value)?.year ?? todayYear);
    const parsed = parseMonth(text);
    const labelText = parsed ? `${MONTH_HEADINGS[parsed.month - 1]} ${parsed.year}` : '';
    const selectedInView = parsed && parsed.year === year ? formatMonth(parsed.month, parsed.year) : '';
    const initialFocus = selectedInView && allowed(selectedInView, min, max)
        ? `[data-month="${selectedInView}"]`
        : '.month-picker__month:not(:disabled)';
    const popoverRef = useFocusTrap(open, () => close(true), {
        capture: true,
        initialFocus,
        shouldRestore: () => restoreFocus.current,
    });

    useEffect(() => {
        setText(value ?? '');
    }, [value]);

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
            const width = Math.min(pop.offsetWidth || 280, window.innerWidth - margin * 2);
            const height = pop.offsetHeight || 280;
            const spaceBelow = window.innerHeight - rect.bottom;
            const spaceAbove = rect.top;
            const openUp = spaceBelow < height + 12 && spaceAbove > spaceBelow;
            let top = openUp ? rect.top - height - 6 : rect.bottom + 6;
            let left = rect.left;
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
    }, [open, year, popoverRef]);

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        function onMouseDown(event) {
            if (rootRef.current?.contains(event.target)) {
                return;
            }

            const backdrop = event.target instanceof Element && event.target.classList.contains('modal-backdrop');
            restoreFocus.current = backdrop;
            setOpen(false);

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

    function close(restore) {
        restoreFocus.current = restore;
        setOpen(false);
    }

    function commit(next) {
        setText(next);
        onChange(next);
        const picked = parseMonth(next);

        if (picked) {
            setYear(picked.year);
        }
    }

    function toggle() {
        if (disabled) {
            return;
        }

        if (open) {
            close(true);
            return;
        }

        setYear(parsed?.year ?? todayYear);
        setOpen(true);
    }

    function choose(next) {
        commit(next);
        close(true);
    }

    const previousLabel = `Mês anterior de ${label}`;
    const nextLabel = `Próximo mês de ${label}`;
    const previousYearLabel = 'Ano anterior';
    const nextYearLabel = 'Próximo ano';
    const previous = shiftMonth(text, -1);
    const next = shiftMonth(text, 1);

    return (
        <div className={error ? 'form-field form-field--error' : 'form-field'}>
            <label className="form-field__label" id={labelId} htmlFor={id}>{label}</label>
            <div className="form-field__control month-field" ref={rootRef}>
                <div className="month-field__row">
                    <button
                        type="button"
                        aria-label={previousLabel}
                        title={previousLabel}
                        disabled={disabled || previous === null || !allowed(previous, min, max)}
                        onClick={() => {
                            if (previous) {
                                commit(previous);
                            }
                        }}
                    >
                        <ChevronLeft aria-hidden="true" strokeWidth={1.75} />
                    </button>
                    <button
                        id={id}
                        type="button"
                        className={labelText ? 'month-field__value' : 'month-field__value month-field__value--empty'}
                        aria-labelledby={`${labelId} ${valueId}`}
                        aria-haspopup="dialog"
                        aria-expanded={open}
                        aria-invalid={error ? 'true' : undefined}
                        aria-describedby={describedBy}
                        disabled={disabled}
                        onClick={toggle}
                    >
                        <span id={valueId}>{labelText || 'Escolher mês'}</span>
                    </button>
                    <button
                        type="button"
                        aria-label={nextLabel}
                        title={nextLabel}
                        disabled={disabled || next === null || !allowed(next, min, max)}
                        onClick={() => {
                            if (next) {
                                commit(next);
                            }
                        }}
                    >
                        <ChevronRight aria-hidden="true" strokeWidth={1.75} />
                    </button>
                </div>
                {open ? (
                    <div
                        className="month-picker"
                        style={place
                            ? { top: place.top, left: place.left, width: place.width }
                            : { top: -9999, left: 0 }}
                        role="dialog"
                        aria-label={`Escolher ${label}`}
                        ref={popoverRef}
                        tabIndex={-1}
                    >
                        <div className="month-picker__nav">
                            <button
                                type="button"
                                aria-label={previousYearLabel}
                                title={previousYearLabel}
                                disabled={!yearHasMonth(year - 1, min, max)}
                                onClick={() => setYear((current) => current - 1)}
                            >
                                <ChevronLeft aria-hidden="true" strokeWidth={1.75} />
                            </button>
                            <span>{year}</span>
                            <button
                                type="button"
                                aria-label={nextYearLabel}
                                title={nextYearLabel}
                                disabled={!yearHasMonth(year + 1, min, max)}
                                onClick={() => setYear((current) => current + 1)}
                            >
                                <ChevronRight aria-hidden="true" strokeWidth={1.75} />
                            </button>
                        </div>
                        <div className="month-picker__grid">
                            {MONTH_SHORT.map((short, index) => {
                                const month = index + 1;
                                const nextValue = formatMonth(month, year);
                                const selected = parsed?.month === month && parsed?.year === year;
                                const current = todayMonth === month && todayYear === year;
                                const className = [
                                    'month-picker__month',
                                    selected ? 'month-picker__month--selected' : '',
                                    current ? 'month-picker__month--today' : '',
                                ].filter(Boolean).join(' ');

                                return (
                                    <button
                                        key={short}
                                        type="button"
                                        className={className}
                                        data-month={nextValue}
                                        aria-label={`${MONTH_HEADINGS[index]} ${year}`}
                                        aria-pressed={selected}
                                        disabled={!allowed(nextValue, min, max)}
                                        onClick={() => choose(nextValue)}
                                    >
                                        {short}
                                    </button>
                                );
                            })}
                        </div>
                        {clearable && text ? (
                            <button type="button" className="month-picker__clear" onClick={() => choose('')}>
                                Limpar
                            </button>
                        ) : null}
                    </div>
                ) : null}
            </div>
            {hint ? <p className="form-field__hint" id={hintId}>{hint}</p> : null}
            {error ? <p className="form-field__error" id={errorId} role="alert">{error}</p> : null}
        </div>
    );
}
