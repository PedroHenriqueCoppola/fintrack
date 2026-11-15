import { formatMoney } from '../../lib/format';
import './Money.css';

export default function Money({ value }) {
    const amount = Number(value);
    const negative = Number.isFinite(amount) && amount < 0;
    const positive = Number.isFinite(amount) && amount > 0;
    const tone = negative ? 'money money--negative' : (positive ? 'money money--positive' : 'money');
    const spoken = negative ? 'negativo' : (positive ? 'positivo' : 'zero');

    return (
        <span className={tone}>
            <span className="visually-hidden">{spoken}: </span>
            {formatMoney(amount)}
        </span>
    );
}
