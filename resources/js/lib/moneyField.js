const INTEGER_DIGITS = 13;
const DECIMAL_DIGITS = 2;

function stripNoise(value) {
    return String(value ?? '')
        .replace(/\u00a0/g, '')
        .replace(/\s/g, '')
        .replace(/r\$/gi, '');
}

function integerDigits(value) {
    const digits = String(value ?? '').replace(/\D/g, '').replace(/^0+(?=\d)/, '').slice(0, INTEGER_DIGITS);

    return digits === '' ? '0' : digits;
}

function isSingleEdit(previous, next) {
    if (Math.abs(String(previous).length - String(next).length) !== 1) {
        return false;
    }

    const longer = next.length > previous.length ? next : previous;
    const shorter = next.length > previous.length ? previous : next;
    let index = 0;

    while (index < shorter.length && longer[index] === shorter[index]) {
        index += 1;
    }

    return longer.slice(index + 1) === shorter.slice(index);
}

function canonicalFromSkeleton(skeleton) {
    const cleaned = String(skeleton ?? '').replace(/[^\d,]/g, '');

    if (cleaned === '') {
        return '';
    }

    const comma = cleaned.indexOf(',');

    if (comma === -1) {
        return integerDigits(cleaned);
    }

    const whole = cleaned.slice(0, comma);
    const fraction = cleaned.slice(comma + 1).replace(/\D/g, '').slice(0, DECIMAL_DIGITS);

    return `${whole === '' ? '0' : integerDigits(whole)}.${fraction}`;
}

/**
 * Typed text keeps the field's own dots as thousand separators.
 * A paste of "R$ 1.234,56", "1234.56" or "1.234,56" is normalized.
 * A trailing dot means the comma was typed and the cents are still empty.
 * Returns null when the text is not a money value.
 */
export function toCanonical(value, previousDisplay = '') {
    const raw = String(value ?? '');

    if (raw.trim() === '') {
        return '';
    }

    if (isSingleEdit(previousDisplay, raw) || raw === previousDisplay) {
        return canonicalFromSkeleton(raw.replace(/\./g, ''));
    }

    const text = stripNoise(raw);

    if (text === '') {
        return '';
    }

    if (!/^[\d.,]+$/.test(text)) {
        const digits = text.replace(/[^\d.,]/g, '');

        return digits === '' ? null : toCanonical(digits, '');
    }

    const lastComma = text.lastIndexOf(',');
    const lastDot = text.lastIndexOf('.');

    if (lastComma !== -1 && lastDot !== -1) {
        if (lastComma > lastDot) {
            return canonicalFromSkeleton(`${text.slice(0, lastComma).replace(/\./g, '')},${text.slice(lastComma + 1).replace(/\./g, '')}`);
        }

        return canonicalFromSkeleton(`${text.slice(0, lastDot).replace(/,/g, '')},${text.slice(lastDot + 1).replace(/,/g, '')}`);
    }

    if (lastComma !== -1) {
        const parts = text.split(',');

        if (parts.length > 2 && parts.slice(1).every((part) => part.length === 3)) {
            return integerDigits(parts.join(''));
        }

        if (parts.length === 2 && parts[1].length === 3 && parts[0].length > 0 && parts[0].length <= 3 && !parts[0].includes('.')) {
            return integerDigits(`${parts[0]}${parts[1]}`);
        }

        return canonicalFromSkeleton(text);
    }

    if (/^\d{1,3}(\.\d{3})+$/.test(text)) {
        return integerDigits(text);
    }

    if (lastDot !== -1) {
        return canonicalFromSkeleton(`${text.slice(0, lastDot).replace(/\./g, '')},${text.slice(lastDot + 1)}`);
    }

    return integerDigits(text);
}

export function canonicalToDisplay(canonical) {
    const raw = String(canonical ?? '');

    if (raw === '') {
        return '';
    }

    const match = /^(\d+)\.(\d{0,2})$/.exec(raw) || /^(\d+)$/.exec(raw);

    if (!match) {
        return '';
    }

    const grouped = match[1].replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    if (match[2] === undefined) {
        return grouped;
    }

    return `${grouped},${match[2]}`;
}

export function moneyPayload(value, empty = '') {
    const canonical = toCanonical(value);

    if (canonical === null) {
        const raw = stripNoise(value);

        return raw === '' ? empty : raw;
    }

    const ready = canonical.endsWith('.') ? canonical.slice(0, -1) : canonical;

    return ready === '' ? empty : ready;
}

export function editMode(previousDisplay, raw) {
    if (isSingleEdit(previousDisplay, raw) || raw === previousDisplay) {
        return 'local';
    }

    const text = stripNoise(raw);
    const lastComma = text.lastIndexOf(',');
    const lastDot = text.lastIndexOf('.');

    if (lastComma === -1 && lastDot === -1) {
        return 'digits';
    }

    if (lastComma !== -1 && (lastDot === -1 || lastComma > lastDot)) {
        const parts = text.split(',');

        if (parts.length === 2 && parts[1].length === 3 && parts[0].length <= 3) {
            return 'digits';
        }

        return 'br';
    }

    if (/^\d{1,3}(\.\d{3})+$/.test(text)) {
        return 'digits';
    }

    return 'us';
}

export function marksBeforeCursor(raw, cursor, mode) {
    const slice = String(raw ?? '').slice(0, Math.max(0, cursor ?? 0));

    if (mode === 'us') {
        const text = stripNoise(raw);
        const dot = String(raw ?? '').lastIndexOf('.');
        const digits = (slice.match(/\d/g) || []).length;

        return digits + (dot !== -1 && cursor > dot && text.includes('.') ? 1 : 0);
    }

    const digits = (slice.match(/\d/g) || []).length;
    const comma = slice.indexOf(',');

    return digits + (comma === -1 ? 0 : 1);
}

export function cursorAfterMarks(display, marks) {
    const text = String(display ?? '');

    if (marks <= 0) {
        return 0;
    }

    let seen = 0;

    for (let index = 0; index < text.length; index += 1) {
        const char = text[index];

        if ((char >= '0' && char <= '9') || char === ',') {
            seen += 1;

            if (seen === marks) {
                return index + 1;
            }
        }
    }

    return text.length;
}
