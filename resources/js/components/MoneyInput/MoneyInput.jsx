import { useLayoutEffect, useRef } from 'react';
import {
    canonicalToDisplay,
    cursorAfterMarks,
    editMode,
    marksBeforeCursor,
    toCanonical,
} from '../../lib/moneyField';
import './MoneyInput.css';

export default function MoneyInput({
    value,
    onChange,
    onFocus,
    onBlur,
    id,
    disabled = false,
    'aria-invalid': ariaInvalid,
    'aria-describedby': ariaDescribedBy,
}) {
    const inputRef = useRef(null);
    const cursorRef = useRef(null);
    const display = canonicalToDisplay(value);

    useLayoutEffect(() => {
        const input = inputRef.current;
        const marks = cursorRef.current;

        if (!input || marks == null || document.activeElement !== input) {
            return;
        }

        const next = cursorAfterMarks(display, marks);
        input.setSelectionRange(next, next);
        cursorRef.current = null;
    }, [display]);

    function restore(marks) {
        const input = inputRef.current;

        if (!input) {
            return;
        }

        input.value = display;
        const next = cursorAfterMarks(display, marks);
        input.setSelectionRange(next, next);
    }

    function apply(raw, cursor) {
        const canonical = toCanonical(raw, display);

        if (canonical === null || canonical === (value ?? '')) {
            restore(marksBeforeCursor(display, Math.max(0, cursor - 1), 'local'));
            return;
        }

        cursorRef.current = marksBeforeCursor(raw, cursor, editMode(display, raw));
        onChange(canonical);
    }

    function blur() {
        if (typeof value === 'string' && value.endsWith('.')) {
            onChange(value.slice(0, -1));
        }

        onBlur?.();
    }

    return (
        <div className="money-input">
            <span className="money-input__prefix" aria-hidden="true">R$</span>
            <input
                ref={inputRef}
                id={id}
                className="money-input__field"
                inputMode="decimal"
                autoComplete="off"
                disabled={disabled}
                aria-invalid={ariaInvalid}
                aria-describedby={ariaDescribedBy}
                value={display}
                onChange={(event) => apply(event.target.value, event.target.selectionStart ?? event.target.value.length)}
                onFocus={onFocus}
                onBlur={blur}
            />
        </div>
    );
}
