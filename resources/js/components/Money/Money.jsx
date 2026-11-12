import { formatMoney } from '../../lib/format';
import './Money.css';

export default function Money({ value }) {
    const amount = Number(value);
    const negative = Number.isFinite(amount) && amount < 0;

    return (
        <span className={negative ? 'money money--negative' : 'money money--positive'}>
            <span className="visually-hidden">{negative ? 'negativo' : 'positivo'}: </span>
            {formatMoney(amount)}
        </span>
    );
}
