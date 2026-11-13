export function formatMoney(value) {
    const amount = Number(value);
    const safe = Number.isFinite(amount) ? amount : 0;
    const formatted = new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(Math.abs(safe));
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
    const normalized = unsigned.replace(/\./g, '').replace(',', '.');

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
