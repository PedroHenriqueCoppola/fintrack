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
