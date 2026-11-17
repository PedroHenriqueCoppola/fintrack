import { useEffect, useId, useState } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import './MonthField.css';

function maskMonth(value) {
    const digits = String(value ?? '').replace(/\D/g, '').slice(0, 6);

    if (digits.length <= 2) {
        return digits;
    }

    return `${digits.slice(0, 2)}/${digits.slice(2)}`;
}

function shiftMonth(text, delta) {
    const match = /^(\d{2})\/(\d{4})$/.exec(text);

    if (!match) {
        return null;
    }

    let month = parseInt(match[1], 10) + delta;
    let year = parseInt(match[2], 10);

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

    return `${String(month).padStart(2, '0')}/${year}`;
}

export default function MonthField({ label, value, onChange, error, hint }) {
    const id = useId();
    const hintId = hint ? `${id}-hint` : undefined;
    const errorId = error ? `${id}-error` : undefined;
    const describedBy = [hintId, errorId].filter(Boolean).join(' ') || undefined;
    const [text, setText] = useState(value ?? '');

    useEffect(() => {
        setText(value ?? '');
    }, [value]);

    function commit(next) {
        setText(next);
        onChange(next);
    }

    return (
        <div className={error ? 'form-field form-field--error' : 'form-field'}>
            <label className="form-field__label" htmlFor={id}>{label}</label>
            <div className="form-field__control">
                <span className="month-field">
                    <button
                        type="button"
                        aria-label={`Mês anterior de ${label}`}
                        onClick={() => {
                            const next = shiftMonth(text, -1);
                            if (next) {
                                commit(next);
                            }
                        }}
                    >
                        <ChevronLeft aria-hidden="true" strokeWidth={1.75} />
                    </button>
                    <input
                        id={id}
                        inputMode="numeric"
                        autoComplete="off"
                        placeholder="mm/aaaa"
                        aria-invalid={error ? 'true' : undefined}
                        aria-describedby={describedBy}
                        value={text}
                        onChange={(event) => commit(maskMonth(event.target.value))}
                    />
                    <button
                        type="button"
                        aria-label={`Próximo mês de ${label}`}
                        onClick={() => {
                            const next = shiftMonth(text, 1);
                            if (next) {
                                commit(next);
                            }
                        }}
                    >
                        <ChevronRight aria-hidden="true" strokeWidth={1.75} />
                    </button>
                </span>
            </div>
            {hint ? <p className="form-field__hint" id={hintId}>{hint}</p> : null}
            {error ? <p className="form-field__error" id={errorId} role="alert">{error}</p> : null}
        </div>
    );
}
