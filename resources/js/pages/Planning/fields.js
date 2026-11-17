import {
    formatDate,
    formatMoneyInput,
    isoFromDate,
    parseBrDate,
    parseMoneyInput,
} from '../../lib/format';

export function moneyPayload(value, empty = '') {
    const parsed = parseMoneyInput(String(value ?? '').replace(/\s/g, '').replace(/^R\$/i, ''));

    return parsed === '' ? empty : parsed;
}

export function datePayload(value) {
    if (!value) {
        return null;
    }

    const parsed = parseBrDate(value);

    return parsed ? isoFromDate(parsed) : value;
}

export function monthPayload(value) {
    const match = /^(\d{2})\/(\d{4})$/.exec(String(value ?? '').trim());

    if (!match) {
        return value || null;
    }

    return `${match[2]}-${match[1]}`;
}

export function monthText(value) {
    const match = /^(\d{4})-(\d{2})/.exec(String(value ?? ''));

    if (!match) {
        return '';
    }

    return `${match[2]}/${match[1]}`;
}

export function moneyText(value) {
    return formatMoneyInput(value);
}

export { formatDate };
