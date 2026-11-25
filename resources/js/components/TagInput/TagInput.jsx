import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { X } from 'lucide-react';
import { keepPlainText, PLAIN_TEXT_MESSAGE } from '../TextInput/TextInput';
import './TagInput.css';

const LIMIT = 10;

function fold(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/\p{M}/gu, '')
        .toLowerCase()
        .trim();
}

export default function TagInput({
    value = [],
    suggestions = [],
    onChange,
    id,
    'aria-invalid': ariaInvalid,
    'aria-describedby': ariaDescribedBy,
}) {
    const listId = useId();
    const inputRef = useRef(null);
    const timer = useRef(null);
    const [draft, setDraft] = useState('');
    const [notice, setNotice] = useState('');
    const [open, setOpen] = useState(false);
    const names = Array.isArray(value) ? value : [];
    const taken = new Set(names.map((name) => fold(name)));
    const folded = fold(draft);
    const matches = useMemo(() => suggestions.filter((item) => {
        const key = fold(item.name);

        if (taken.has(key)) {
            return false;
        }

        return folded === '' || key.includes(folded);
    }).slice(0, 8), [suggestions, folded, names.join('|')]);

    useEffect(() => () => window.clearTimeout(timer.current), []);

    function warn(message) {
        setNotice(message);
        window.clearTimeout(timer.current);
        timer.current = window.setTimeout(() => setNotice(''), 4000);
    }

    function add(raw) {
        const withoutComma = String(raw ?? '').replace(/,/g, '');
        const label = keepPlainText(withoutComma).replace(/\s+/g, ' ').trim();

        if (label === '') {
            if (withoutComma.trim() !== '') {
                warn(PLAIN_TEXT_MESSAGE);
            }

            setDraft('');
            return;
        }

        if (label !== withoutComma.replace(/\s+/g, ' ').trim()) {
            warn(PLAIN_TEXT_MESSAGE);
        }

        if (taken.has(fold(label))) {
            setDraft('');
            setOpen(false);
            return;
        }

        if (names.length >= LIMIT) {
            warn('Uma transação pode ter no máximo 10 tags.');
            return;
        }

        onChange([...names, label.slice(0, 30)]);
        setDraft('');
        setOpen(false);
    }

    function remove(name) {
        onChange(names.filter((item) => item !== name));
    }

    return (
        <div className="tag-input">
            <div className={names.length ? 'tag-input__box tag-input__box--filled' : 'tag-input__box'}>
                {names.map((name) => (
                    <span key={fold(name)} className="tag-input__chip">
                        {name}
                        <button type="button" aria-label={`Remover tag ${name}`} onClick={() => remove(name)}>
                            <X aria-hidden="true" strokeWidth={1.75} />
                        </button>
                    </span>
                ))}
                <input
                    ref={inputRef}
                    id={id}
                    value={draft}
                    maxLength={30}
                    placeholder={names.length ? '' : 'Digite e pressione Enter'}
                    autoComplete="off"
                    aria-invalid={ariaInvalid}
                    aria-describedby={ariaDescribedBy}
                    aria-expanded={open && matches.length > 0}
                    aria-controls={listId}
                    role="combobox"
                    onFocus={() => setOpen(true)}
                    onBlur={() => setOpen(false)}
                    onChange={(event) => {
                        const raw = event.target.value;

                        if (raw.includes(',')) {
                            add(raw);
                            return;
                        }

                        const clean = keepPlainText(raw);

                        if (clean !== raw) {
                            warn(PLAIN_TEXT_MESSAGE);
                        }

                        setDraft(clean);
                        setOpen(true);
                    }}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter' || event.key === ',') {
                            event.preventDefault();
                            add(draft);
                            return;
                        }

                        if (event.key === 'Backspace' && draft === '' && names.length > 0) {
                            remove(names[names.length - 1]);
                        }
                    }}
                />
            </div>
            {open && matches.length > 0 ? (
                <ul id={listId} className="tag-input__list" role="listbox">
                    {matches.map((item) => (
                        <li key={item.id}>
                            <button
                                type="button"
                                role="option"
                                onMouseDown={(event) => {
                                    event.preventDefault();
                                    add(item.name);
                                    inputRef.current?.focus();
                                }}
                            >
                                {item.name}
                            </button>
                        </li>
                    ))}
                </ul>
            ) : null}
            {notice ? <p className="tag-input__notice" role="status">{notice}</p> : null}
        </div>
    );
}
