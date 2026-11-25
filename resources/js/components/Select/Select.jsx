import { useEffect, useId, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Check, ChevronDown } from 'lucide-react';
import CatalogIcon from '../../pages/Manage/icons';
import './Select.css';

const SEARCH_AFTER = 8;

function fold(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/\p{M}/gu, '')
        .toLowerCase();
}

function OptionMark({ option }) {
    if (option.mark === 'category') {
        return (
            <span
                className={option.color ? 'select__tile' : 'select__tile select__tile--plain'}
                style={option.color ? { '--item-color': option.color } : undefined}
                aria-hidden="true"
            >
                <CatalogIcon name={option.icon} />
            </span>
        );
    }

    if (option.mark === 'dot') {
        return (
            <span
                className="select__dot"
                style={option.color ? { background: option.color } : undefined}
                aria-hidden="true"
            />
        );
    }

    return null;
}

function OptionText({ option }) {
    return (
        <span className="select__copy">
            <span className="select__label">
                {option.label}
                {option.archived ? <span className="select__archived"> (arquivado)</span> : null}
            </span>
            {option.hint ? <span className="select__hint">{option.hint}</span> : null}
        </span>
    );
}

export default function Select({
    value,
    onChange,
    options = [],
    placeholder = 'Escolha',
    emptyOption = null,
    searchable,
    multiple = false,
    disabled = false,
    id,
    'aria-invalid': ariaInvalid,
    'aria-describedby': ariaDescribedBy,
}) {
    const listId = useId();
    const buttonRef = useRef(null);
    const listRef = useRef(null);
    const searchRef = useRef(null);
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);
    const [typed, setTyped] = useState('');
    const [position, setPosition] = useState(null);
    const typeTimer = useRef(null);
    const stateRef = useRef({});
    const showSearch = searchable ?? options.length > SEARCH_AFTER;
    const selectedIds = new Set((Array.isArray(value) ? value : []).map(String));
    const selected = multiple ? null : options.find((option) => String(option.value) === String(value ?? ''));
    const empty = !multiple && emptyOption ? { value: '', label: emptyOption, empty: true } : null;
    const folded = fold(query);
    const visible = useMemo(() => {
        const matches = options.filter((option) => {
            if (!folded) {
                return true;
            }

            return fold(`${option.label} ${option.hint ?? ''}`).includes(folded);
        });

        return empty ? [empty, ...matches] : matches;
    }, [options, folded, empty]);
    const enabledIndexes = visible
        .map((option, index) => (option.disabled ? -1 : index))
        .filter((index) => index >= 0);
    stateRef.current = {
        active, visible, enabledIndexes, showSearch, typed, onChange, multiple, value,
    };

    useLayoutEffect(() => {
        if (!open || !buttonRef.current || !listRef.current) {
            return;
        }

        const rect = buttonRef.current.getBoundingClientRect();
        const gap = 6;
        const maxHeight = 320;
        const spaceBelow = window.innerHeight - rect.bottom - gap;
        const spaceAbove = rect.top - gap;
        const needed = Math.min(maxHeight, listRef.current.scrollHeight);
        const openUp = spaceBelow < needed && spaceAbove > spaceBelow;
        const room = Math.max(120, Math.min(maxHeight, openUp ? spaceAbove : spaceBelow));
        const width = rect.width;
        const left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8));

        setPosition({
            left,
            width,
            maxHeight: room,
            top: openUp ? undefined : rect.bottom + gap,
            bottom: openUp ? window.innerHeight - rect.top + gap : undefined,
        });
    }, [open, query, visible.length]);

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        function close() {
            setOpen(false);
            setQuery('');
        }

        function onPointer(event) {
            if (buttonRef.current?.contains(event.target) || listRef.current?.contains(event.target)) {
                return;
            }

            close();
        }

        function onScroll(event) {
            if (listRef.current?.contains(event.target)) {
                return;
            }

            close();
        }

        document.addEventListener('mousedown', onPointer);
        window.addEventListener('scroll', onScroll, true);
        window.addEventListener('resize', close);

        return () => {
            document.removeEventListener('mousedown', onPointer);
            window.removeEventListener('scroll', onScroll, true);
            window.removeEventListener('resize', close);
        };
    }, [open]);

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        (showSearch ? searchRef.current : buttonRef.current)?.focus();

        return undefined;
    }, [open, showSearch]);

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        function move(offset) {
            const { enabledIndexes: indexes, active: current } = stateRef.current;

            if (indexes.length === 0) {
                return;
            }

            const place = indexes.indexOf(current);
            const next = place === -1
                ? indexes[offset > 0 ? 0 : indexes.length - 1]
                : indexes[(place + offset + indexes.length) % indexes.length];
            setActive(next);
            document.getElementById(`${listId}-${next}`)?.scrollIntoView({ block: 'nearest' });
        }

        function choose(index) {
            const { visible: items, onChange: change, multiple: many, value: currentValue } = stateRef.current;
            const option = items[index];

            if (!option || option.disabled) {
                return;
            }

            if (many) {
                const current = Array.isArray(currentValue) ? currentValue.map(String) : [];
                const id = String(option.value);
                const next = current.includes(id) ? current.filter((item) => item !== id) : [...current, id];
                change(next);
                return;
            }

            change(option.value);
            setOpen(false);
            setQuery('');
            buttonRef.current?.focus();
        }

        function jump(char) {
            const { typed: soFar, enabledIndexes: indexes, active: current, visible: items } = stateRef.current;
            const nextTyped = `${soFar}${fold(char)}`;
            setTyped(nextTyped);
            window.clearTimeout(typeTimer.current);
            typeTimer.current = window.setTimeout(() => setTyped(''), 700);
            const start = indexes.find((index) => index > current) ?? indexes[0];
            const ordered = [
                ...indexes.filter((index) => index >= start),
                ...indexes.filter((index) => index < start),
            ];
            const found = ordered.find((index) => fold(items[index].label).startsWith(nextTyped));

            if (found !== undefined) {
                setActive(found);
                document.getElementById(`${listId}-${found}`)?.scrollIntoView({ block: 'nearest' });
            }
        }

        function onKey(event) {
            const { showSearch: searching, active: current } = stateRef.current;

            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                setOpen(false);
                setQuery('');
                buttonRef.current?.focus();
                return;
            }

            if (event.key === 'Tab') {
                setOpen(false);
                setQuery('');
                buttonRef.current?.focus();
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                move(1);
                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                move(-1);
                return;
            }

            if (event.key === 'Home') {
                event.preventDefault();
                const first = stateRef.current.enabledIndexes[0];

                if (first !== undefined) {
                    setActive(first);
                }
                return;
            }

            if (event.key === 'End') {
                event.preventDefault();
                const indexes = stateRef.current.enabledIndexes;
                const last = indexes[indexes.length - 1];

                if (last !== undefined) {
                    setActive(last);
                }
                return;
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                choose(current);
                return;
            }

            if (event.key === ' ' && !searching) {
                event.preventDefault();
                choose(current);
                return;
            }

            if (!searching && event.key.length === 1 && /\p{L}|\d/u.test(event.key)) {
                event.preventDefault();
                jump(event.key);
            }
        }

        document.addEventListener('keydown', onKey, true);

        return () => document.removeEventListener('keydown', onKey, true);
    }, [open, listId]);

    function openList() {
        if (disabled) {
            return;
        }

        const current = visible.findIndex((option) => String(option.value) === String(value ?? ''));
        setActive(current >= 0 ? current : (enabledIndexes[0] ?? 0));
        setQuery('');
        setOpen(true);
    }

    function onButtonKey(event) {
        if (open) {
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            openList();
        }
    }

    const groups = [];
    visible.forEach((option, index) => {
        const name = option.group || '';
        const last = groups[groups.length - 1];

        if (!last || last.name !== name) {
            groups.push({ name, items: [{ option, index }] });
            return;
        }

        last.items.push({ option, index });
    });

    const list = open ? (
        <div
            ref={listRef}
            className="select__popover"
            style={{
                left: position?.left ?? -9999,
                width: position?.width,
                top: position?.top,
                bottom: position?.bottom,
            }}
        >
            {showSearch ? (
                <input
                    ref={searchRef}
                    className="select__search"
                    value={query}
                    placeholder="Buscar"
                    aria-label="Buscar"
                    aria-controls={listId}
                    onChange={(event) => {
                        setQuery(event.target.value);
                        setActive(0);
                    }}
                />
            ) : null}
            <ul
                id={listId}
                role="listbox"
                aria-multiselectable={multiple ? 'true' : undefined}
                className="select__list"
                style={{ maxHeight: position?.maxHeight ?? 320 }}
            >
                {visible.length === 0 || (visible.length === 1 && visible[0].empty && folded) ? (
                    <li className="select__empty">Nenhum resultado</li>
                ) : null}
                {groups.map((group) => (
                    <li key={group.name || 'all'}>
                        {group.name ? <p className="select__group">{group.name}</p> : null}
                        <ul>
                            {group.items.map(({ option, index }) => {
                                const chosen = multiple
                                    ? selectedIds.has(String(option.value))
                                    : (option.empty ? String(value ?? '') === '' : String(option.value) === String(value ?? ''));

                                return (
                                    <li key={`${option.value}-${index}`}>
                                        <button
                                            type="button"
                                            id={`${listId}-${index}`}
                                            role="option"
                                            aria-selected={chosen}
                                            disabled={option.disabled}
                                            className={index === active ? 'select__option select__option--active' : 'select__option'}
                                            onMouseEnter={() => setActive(index)}
                                            onMouseDown={(event) => event.preventDefault()}
                                            onClick={() => {
                                                if (option.disabled) {
                                                    return;
                                                }

                                                if (multiple) {
                                                    const current = Array.isArray(value) ? value.map(String) : [];
                                                    const id = String(option.value);
                                                    const next = current.includes(id)
                                                        ? current.filter((item) => item !== id)
                                                        : [...current, id];
                                                    onChange(next);
                                                    return;
                                                }

                                                onChange(option.value);
                                                setOpen(false);
                                                setQuery('');
                                                buttonRef.current?.focus();
                                            }}
                                        >
                                            <OptionMark option={option} />
                                            <OptionText option={option} />
                                            {chosen ? <Check aria-hidden="true" strokeWidth={1.75} /> : null}
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    </li>
                ))}
            </ul>
        </div>
    ) : null;

    const multipleLabels = multiple
        ? options.filter((option) => selectedIds.has(String(option.value))).map((option) => option.label)
        : [];
    const closedLabel = multiple
        ? multipleLabels.join(', ')
        : (selected?.label || (String(value ?? '') === '' && empty ? empty.label : ''));

    return (
        <div className={disabled ? 'select select--disabled' : 'select'}>
            <button
                ref={buttonRef}
                id={id}
                type="button"
                className="select__button"
                role="combobox"
                aria-expanded={open}
                aria-controls={listId}
                aria-haspopup="listbox"
                aria-invalid={ariaInvalid}
                aria-describedby={ariaDescribedBy}
                aria-activedescendant={open ? `${listId}-${active}` : undefined}
                disabled={disabled}
                onClick={() => (open ? setOpen(false) : openList())}
                onKeyDown={onButtonKey}
            >
                {selected ? <OptionMark option={selected} /> : null}
                <span className={closedLabel ? 'select__value' : 'select__value select__value--placeholder'} title={multipleLabels.join(', ') || undefined}>
                    {closedLabel || placeholder}
                    {selected?.archived ? <span className="select__archived"> (arquivado)</span> : null}
                </span>
                <ChevronDown aria-hidden="true" strokeWidth={1.75} className={open ? 'select__chevron select__chevron--open' : 'select__chevron'} />
            </button>
            {list && typeof document !== 'undefined' ? createPortal(list, document.body) : null}
        </div>
    );
}
