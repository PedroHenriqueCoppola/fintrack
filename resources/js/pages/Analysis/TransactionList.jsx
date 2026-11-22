import { formatLongDate, formatPlainMoney } from '../../lib/format';
import Money from '../../components/Money/Money';
import ReferenceSeal from '../../components/ReferenceSeal/ReferenceSeal';
import CatalogIcon from '../Manage/icons';

function paymentLabel(transaction) {
    return transaction.card
        ? `${transaction.payment_method?.name ?? ''} ${transaction.card.name}`.trim()
        : transaction.payment_method?.name;
}

function metaTail(transaction) {
    return [transaction.bank?.name, paymentLabel(transaction), transaction.notes].filter(Boolean).join(' · ');
}

function signedAmount(transaction) {
    if (transaction.type === 'expense') {
        return `-${transaction.amount}`;
    }

    return transaction.amount;
}

function Row({ transaction, onEdit, onPay, paying }) {
    return (
        <div
            className="tx-row"
            role="button"
            tabIndex={0}
            aria-label={`Editar ${transaction.description}`}
            onClick={() => onEdit(transaction)}
            onKeyDown={(event) => {
                if (event.target !== event.currentTarget) {
                    return;
                }

                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    onEdit(transaction);
                }
            }}
        >
            <span
                className={transaction.category?.color ? 'tx-tile' : 'tx-tile tx-tile--plain'}
                style={transaction.category?.color ? { '--item-color': transaction.category.color } : undefined}
                aria-hidden="true"
            >
                <CatalogIcon name={transaction.category?.icon} />
            </span>
            <div className="tx-row__copy">
                <div className="tx-row__title">
                    <span className="tx-row__name">{transaction.description}</span>
                    <ReferenceSeal label={transaction.competence_label} />
                    {transaction.status === 'pending' ? <span className="tx-badge">Pendente</span> : null}
                    {transaction.fixed_expense ? <span className="tx-badge tx-badge--neutral">Fixo</span> : null}
                    {transaction.installment ? (
                        <span className="tx-badge tx-badge--neutral">
                            {`Parcela ${transaction.installment.number}/${transaction.installment.count}`}
                        </span>
                    ) : null}
                </div>
                <p className="tx-row__meta">
                    {transaction.category?.name}
                    {transaction.type === 'reserve' ? (
                        <span className="tx-badge tx-badge--neutral">
                            {transaction.reserve_action === 'withdraw' ? 'Resgate' : 'Reserva'}
                        </span>
                    ) : null}
                    {metaTail(transaction) ? `${transaction.category?.name || transaction.type === 'reserve' ? ' · ' : ''}${metaTail(transaction)}` : null}
                </p>
            </div>
            {transaction.status === 'pending' ? (
                <button
                    type="button"
                    className="tx-pay"
                    disabled={paying}
                    onClick={(event) => {
                        event.stopPropagation();
                        onPay(transaction);
                    }}
                >
                    {paying ? 'Salvando…' : 'Marcar como paga'}
                </button>
            ) : null}
            <span className="tx-row__amount">
                {transaction.type === 'reserve' ? (
                    <span className="tx-row__plain">{formatPlainMoney(transaction.amount)}</span>
                ) : (
                    <Money value={signedAmount(transaction)} />
                )}
            </span>
        </div>
    );
}

function groupsFor(transactions) {
    const groups = [];

    transactions.forEach((transaction) => {
        const last = groups[groups.length - 1];

        if (!last || last.date !== transaction.date) {
            groups.push({ date: transaction.date, total: transaction.day_total, items: [transaction] });
            return;
        }

        last.items.push(transaction);
    });

    return groups;
}

export default function TransactionList({ transactions, grouped, onEdit, onPay, payingId }) {
    if (!grouped) {
        return (
            <div>
                {transactions.map((transaction) => (
                    <Row
                        key={transaction.id}
                        transaction={transaction}
                        onEdit={onEdit}
                        onPay={onPay}
                        paying={payingId === transaction.id}
                    />
                ))}
            </div>
        );
    }

    return (
        <div>
            {groupsFor(transactions).map((group, index) => (
                <section key={group.date} className={index === 0 ? 'tx-group' : 'tx-group tx-group--next'}>
                    <div className="tx-day">
                        <span>{formatLongDate(group.date)}</span>
                        {group.total != null ? <Money value={group.total} /> : null}
                    </div>
                    {group.items.map((transaction) => (
                        <Row
                            key={transaction.id}
                            transaction={transaction}
                            onEdit={onEdit}
                            onPay={onPay}
                            paying={payingId === transaction.id}
                        />
                    ))}
                </section>
            ))}
        </div>
    );
}
