import { useEffect, useRef, useState } from 'react';
import './TextInput.css';

const PLAIN_CHARACTERS = '\\p{L}\\p{M}0-9 .,\\-/&()\'#+%:;!?"@$*';
export const PLAIN_TEXT_MESSAGE = 'Use letras, números e pontuação simples.';

export function keepPlainText(value, multiline = false) {
    const extra = multiline ? '\\n' : '';
    const pattern = new RegExp(`[^${PLAIN_CHARACTERS}${extra}]`, 'gu');

    return String(value ?? '').replace(pattern, '');
}

export default function TextInput({
    value,
    onChange,
    maxLength,
    rows,
    placeholder,
    disabled = false,
    name,
    autoComplete = 'off',
    autoFocus = false,
    id,
    'aria-invalid': ariaInvalid,
    'aria-describedby': ariaDescribedBy,
}) {
    const inputRef = useRef(null);
    const timer = useRef(null);
    const [notice, setNotice] = useState('');

    useEffect(() => () => window.clearTimeout(timer.current), []);

    function warn() {
        setNotice(PLAIN_TEXT_MESSAGE);
        window.clearTimeout(timer.current);
        timer.current = window.setTimeout(() => setNotice(''), 4000);
    }

    const multiline = Boolean(rows);

    function apply(raw, cursor) {
        const clean = keepPlainText(raw, multiline);

        if (clean !== raw) {
            warn();
        }

        const kept = keepPlainText(String(raw).slice(0, cursor), multiline).length;
        onChange(clean);

        requestAnimationFrame(() => {
            const input = inputRef.current;

            if (!input || document.activeElement !== input) {
                return;
            }

            const next = Math.min(kept, clean.length);
            input.setSelectionRange(next, next);
        });
    }

    function beforeInput(event) {
        if (!event.data || event.inputType === 'insertFromPaste' || event.inputType === 'insertFromDrop') {
            return;
        }

        if (keepPlainText(event.data, multiline) !== event.data) {
            event.preventDefault();
            warn();
        }
    }

    const describedBy = [ariaDescribedBy, notice ? `${id || 'text'}-plain` : null].filter(Boolean).join(' ') || undefined;
    const shared = {
        ref: inputRef,
        id,
        name,
        maxLength,
        placeholder,
        disabled,
        autoComplete,
        autoFocus,
        'aria-invalid': ariaInvalid || notice ? 'true' : undefined,
        'aria-describedby': describedBy,
        value: value ?? '',
        onBeforeInput: beforeInput,
        onChange: (event) => apply(event.target.value, event.target.selectionStart ?? event.target.value.length),
    };

    return (
        <div className="text-input">
            {rows ? <textarea rows={rows} {...shared} /> : <input type="text" {...shared} />}
            {notice ? (
                <p className="text-input__notice" id={`${id || 'text'}-plain`} role="alert">{notice}</p>
            ) : null}
        </div>
    );
}
