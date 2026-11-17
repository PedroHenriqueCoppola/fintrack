import { usePage } from '@inertiajs/react';

export function formatMoney(value) {
    const amount = Number(value);
    const safe = Number.isFinite(amount) ? amount : 0;
    const formatted = new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(Math.abs(safe));

    if (safe === 0) {
        return formatted;
    }

    const sign = safe < 0 ? '−' : '+';

    return `${sign}${formatted}`;
}

export function parseMoneyInput(value) {
    const raw = String(value ?? '').trim();

    if (raw === '') {
        return '';
    }

    const negative = raw.startsWith('-');
    const unsigned = (negative ? raw.slice(1) : raw).trim();
    let normalized = unsigned;

    if (unsigned.includes('.')) {
        if (!/^\d{1,3}(\.\d{3})+(,\d{0,2})?$/.test(unsigned)) {
            return raw;
        }

        normalized = unsigned.replace(/\./g, '');
    }

    normalized = normalized.replace(',', '.');

    if (!/^\d+(\.\d{0,2})?$/.test(normalized)) {
        return raw;
    }

    return negative ? `-${normalized}` : normalized;
}

export function formatMoneyInput(value) {
    const raw = String(value ?? '').trim();

    if (raw === '') {
        return '';
    }

    const negative = raw.startsWith('-');
    const unsigned = negative ? raw.slice(1) : raw;

    if (!/^\d+(\.\d+)?$/.test(unsigned)) {
        return '';
    }

    const [whole, fraction = ''] = unsigned.split('.');
    const cents = `${fraction}00`.slice(0, 2);
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    return `${negative ? '-' : ''}${grouped},${cents}`;
}

export function blurMoneyInput(value) {
    const parsed = parseMoneyInput(String(value ?? '').replace(/\s/g, '').replace(/^R\$/i, ''));

    if (!/^\d+(\.\d{1,2})?$/.test(parsed)) {
        return null;
    }

    return formatMoneyInput(parsed);
}

export function formatPlainMoney(value) {
    const amount = Number(value);
    const safe = Number.isFinite(amount) ? Math.abs(amount) : 0;

    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(safe);
}

const WEEKDAYS = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
const MONTHS = [
    'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
    'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
];

export const WEEKDAY_INITIALS = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'];
export const MONTH_HEADINGS = [
    'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
    'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
];

export function todayIso() {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${now.getFullYear()}-${month}-${day}`;
}

export function useToday() {
    const { today } = usePage().props;

    if (typeof today === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(today)) {
        return today;
    }

    return todayIso();
}

export function isoFromDate(date) {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

export function parseIsoDate(value) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value ?? ''));

    if (!match) {
        return null;
    }

    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);
    const date = new Date(year, month - 1, day);

    if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
        return null;
    }

    return date;
}

export function parseBrDate(value) {
    const match = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(String(value ?? '').trim());

    if (!match) {
        return null;
    }

    const day = Number(match[1]);
    const month = Number(match[2]);
    const year = Number(match[3]);
    const date = new Date(year, month - 1, day);

    if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
        return null;
    }

    return date;
}

export function maskDate(value) {
    const digits = String(value ?? '').replace(/\D/g, '').slice(0, 8);
    const parts = [];

    if (digits.length > 0) {
        parts.push(digits.slice(0, 2));
    }

    if (digits.length > 2) {
        parts.push(digits.slice(2, 4));
    }

    if (digits.length > 4) {
        parts.push(digits.slice(4, 8));
    }

    return parts.join('/');
}

export function formatLongDate(value) {
    const date = parseIsoDate(value);

    if (!date) {
        return '';
    }

    return `${WEEKDAYS[date.getDay()]}, ${date.getDate()} de ${MONTHS[date.getMonth()]}`;
}

export function formatDate(value) {
    if (!value) {
        return '';
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value));

    if (match) {
        return `${match[3]}/${match[2]}/${match[1]}`;
    }

    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');

    return `${day}/${month}/${date.getFullYear()}`;
}
