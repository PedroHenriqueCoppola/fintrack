import { parseMoneyInput } from './format';

/**
 * Integer cents. Money never goes through a float.
 * Accepts "8200.00" and the field text "8.200,00".
 */
export function toCents(value) {
    const parsed = parseMoneyInput(String(value ?? '').trim());
    const match = /^(\d+)(?:\.(\d{1,2}))?$/.exec(parsed);

    if (!match) {
        return null;
    }

    return BigInt(`${match[1]}${(match[2] ?? '').padEnd(2, '0')}`);
}

export function sumCents(values) {
    return values.reduce((total, value) => total + (toCents(value) ?? 0n), 0n);
}

export function decimalFromCents(cents) {
    const negative = cents < 0n;
    const absolute = negative ? -cents : cents;
    const digits = absolute.toString().padStart(3, '0');
    const decimal = `${digits.slice(0, -2)}.${digits.slice(-2)}`;

    return negative ? `-${decimal}` : decimal;
}
