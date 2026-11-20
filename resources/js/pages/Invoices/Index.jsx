import { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { CreditCard } from 'lucide-react';
import Button from '../../components/Button/Button';
import Card from '../../components/Card/Card';
import ConfirmDialog from '../../components/ConfirmDialog/ConfirmDialog';
import DatePicker from '../../components/DatePicker/DatePicker';
import EmptyState from '../../components/EmptyState/EmptyState';
import Modal from '../../components/Modal/Modal';
import MonthNav from '../../components/MonthNav/MonthNav';
import PageHeader from '../../components/PageHeader/PageHeader';
import TransactionModal from '../../components/TransactionModal/TransactionModal';
import AppLayout from '../../layouts/AppLayout/AppLayout';
import { useFieldSetter } from '../../lib/fields';
import { formatDate, formatPlainMoney, isoFromDate, parseBrDate, useToday } from '../../lib/format';
import ReferenceSeal from '../../components/ReferenceSeal/ReferenceSeal';
import CatalogIcon from '../Manage/icons';
import './Index.css';

function shortDate(value) {
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value ?? ''));

    return match ? `${match[3]}/${match[2]}` : '';
}

function situationTone(situation) {
    if (situation === 'paid') {
        return 'ok';
    }

    if (situation === 'closed') {
        return 'default';
    }

    return 'neutral';
}

function Chip({ tone = 'default', children }) {
    return <span className={`invoice-chip invoice-chip--${tone}`}>{children}</span>;
}

function InvoiceRow({ transaction, label, tone, onEdit }) {
    function open() {
        onEdit(transaction);
    }

    return (
        <tr
            className="invoice-row"
            tabIndex={0}
            aria-label={`Editar ${transaction.display_name || transaction.description}`}
            onClick={open}
            onKeyDown={(event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    open();
                }
            }}
        >
            <td>{shortDate(transaction.date)}</td>
            <td>
                <span className="invoice-desc">
                    <span
                        className={transaction.category?.color ? 'invoice-tile' : 'invoice-tile invoice-tile--plain'}
                        style={transaction.category?.color ? { '--item-color': transaction.category.color } : undefined}
                        aria-hidden="true"
                    >
                        <CatalogIcon name={transaction.category?.icon} />
                    </span>
                    <span className="invoice-desc__name">{transaction.display_name || transaction.description}</span>
                    <ReferenceSeal label={transaction.competence_label} />
                </span>
            </td>
            <td>{transaction.category?.name}</td>
            <td><Chip tone={tone}>{label}</Chip></td>
            <td className="invoice-money">{formatPlainMoney(transaction.amount)}</td>
        </tr>
    );
}

function PayModal({ cardId, invoice, onClose }) {
    const today = useToday();
    const form = useForm({
        card_id: cardId,
        closing_month: `${invoice.key}-01`,
        paid_on: formatDate(today),
    });
    const setField = useFieldSetter(form);

    function submit(event) {
        event.preventDefault();
        const parsed = parseBrDate(form.data.paid_on);

        form.transform((data) => ({
            ...data,
            paid_on: parsed ? isoFromDate(parsed) : data.paid_on,
        }));
        form.post('/invoices/pay', {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    }

    return (
        <Modal
            open
            title="Marcar como paga"
            onClose={onClose}
            footer={(
                <>
                    <Button variant="secondary" onClick={onClose} disabled={form.processing}>Cancelar</Button>
                    <Button type="submit" form="invoice-pay-form" disabled={form.processing}>
                        {form.processing ? 'Salvando…' : 'Marcar como paga'}
                    </Button>
                </>
            )}
        >
            <form id="invoice-pay-form" className="invoice-pay-form" noValidate onSubmit={submit}>
                <p className="invoice-pay-form__lead">
                    Informe a data do pagamento. As transações desta fatura não mudam.
                </p>
                <DatePicker
                    label="Data do pagamento"
                    value={form.data.paid_on}
                    min={invoice.closing_date}
                    max={today}
                    error={form.errors.paid_on}
                    onChange={(value) => setField('paid_on', value)}
                />
                {form.errors.closing_month ? (
                    <p className="form-field__error" role="alert">{form.errors.closing_month}</p>
                ) : null}
            </form>
        </Modal>
    );
}

export default function Index({
    cards = [],
    selectedId = null,
    invoice = null,
    history = [],
    options,
}) {
    const pageErrors = usePage().props.errors ?? {};
    const [editing, setEditing] = useState(null);
    const [paying, setPaying] = useState(false);
    const [confirmUnpay, setConfirmUnpay] = useState(false);
    const [unpaying, setUnpaying] = useState(false);
    const hasLines = (invoice?.groups ?? []).some((group) => (group.transactions ?? []).length > 0);

    function openInvoice(key) {
        if (!key || !selectedId) {
            return;
        }

        router.get('/invoices', { card: selectedId, invoice: key }, { preserveScroll: true });
    }

    function unpay() {
        if (!invoice || !selectedId) {
            return;
        }

        setUnpaying(true);
        router.delete('/invoices/pay', {
            data: {
                card_id: selectedId,
                closing_month: `${invoice.key}-01`,
            },
            preserveScroll: true,
            onSuccess: () => setConfirmUnpay(false),
            onFinish: () => setUnpaying(false),
        });
    }

    return (
        <>
            <Head title="Faturas" />
            <div className="page invoices-page">
                <PageHeader
                    title="Faturas"
                    subtitle="Acompanhe o que cada cartão vai fechar e pagar."
                />
                {cards.length === 0 ? (
                    <Card>
                        <EmptyState
                            icon={CreditCard}
                            message="Cadastre um cartão em Gerenciar."
                            shortcutLabel="Gerenciar"
                            shortcutHref="/manage"
                        />
                    </Card>
                ) : (
                    <>
                        <div className="invoice-pills" role="tablist" aria-label="Cartões">
                            {cards.map((card) => {
                                const selected = card.id === selectedId;

                                return (
                                    <Link
                                        key={card.id}
                                        href={`/invoices?card=${card.id}`}
                                        className={selected ? 'invoice-pill invoice-pill--on' : 'invoice-pill'}
                                        role="tab"
                                        aria-selected={selected}
                                    >
                                        <span
                                            className="invoice-pill__dot"
                                            style={card.color ? { '--item-color': card.color } : undefined}
                                            aria-hidden="true"
                                        />
                                        <span className="invoice-pill__text">
                                            <span className="invoice-pill__name">{card.name}</span>
                                            {card.bank_name ? (
                                                <span className="invoice-pill__bank">{card.bank_name}</span>
                                            ) : null}
                                        </span>
                                    </Link>
                                );
                            })}
                        </div>
                        {invoice ? (
                            <div className="invoice-layout">
                                <Card>
                                    <div className="invoice-head">
                                        <div className="invoice-head__title">
                                            <h2>{invoice.title}</h2>
                                            <Chip tone={situationTone(invoice.situation)}>{invoice.situation_label}</Chip>
                                        </div>
                                        <MonthNav
                                            label={invoice.nav_label}
                                            previous={invoice.previous_key}
                                            next={invoice.next_key}
                                            onPrevious={() => openInvoice(invoice.previous_key)}
                                            onNext={() => openInvoice(invoice.next_key)}
                                            previousLabel="Fatura anterior"
                                            nextLabel="Próxima fatura"
                                        />
                                    </div>
                                    <p className="invoice-cycle">{invoice.cycle_label}</p>
                                    {hasLines ? (
                                        <div className="invoice-table-wrap">
                                            <table className="invoice-table">
                                                <thead>
                                                    <tr>
                                                        <th>Data</th>
                                                        <th>Descrição</th>
                                                        <th>Categoria</th>
                                                        <th>Situação</th>
                                                        <th className="invoice-money">Valor</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {invoice.groups.map((group) => (
                                                        <Group
                                                            key={group.key}
                                                            group={group}
                                                            onEdit={setEditing}
                                                        />
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    ) : (
                                        <EmptyState message="Nenhuma compra neste ciclo." />
                                    )}
                                </Card>
                                <div className="invoice-side">
                                    <Card className="card--highlight">
                                        <p className="invoice-total__label">Lançado até agora</p>
                                        <p className="invoice-total__value">{formatPlainMoney(invoice.posted)}</p>
                                        <p className="invoice-total__label">Previsto</p>
                                        <p className="invoice-total__amount invoice-total__amount--muted">{formatPlainMoney(invoice.forecast)}</p>
                                        <p className="invoice-total__label">Total estimado</p>
                                        <p className="invoice-total__amount">{formatPlainMoney(invoice.estimated)}</p>
                                        {invoice.closes_in_label ? (
                                            <p className="invoice-total__note">{invoice.closes_in_label}</p>
                                        ) : null}
                                        {invoice.situation === 'paid' ? (
                                            <button type="button" className="invoice-unpay" onClick={() => setConfirmUnpay(true)}>
                                                Desmarcar
                                            </button>
                                        ) : (
                                            <Button
                                                variant={invoice.situation === 'closed' ? 'primary' : 'secondary'}
                                                className="invoice-pay"
                                                disabled={invoice.situation !== 'closed'}
                                                onClick={() => setPaying(true)}
                                            >
                                                Marcar como paga
                                            </Button>
                                        )}
                                        {invoice.situation === 'open' ? (
                                            <p className="invoice-total__note invoice-total__note--center">Disponível depois do fechamento</p>
                                        ) : null}
                                        {pageErrors.closing_month && !paying ? (
                                            <p className="form-field__error" role="alert">{pageErrors.closing_month}</p>
                                        ) : null}
                                    </Card>
                                    <Card>
                                        <h2 className="invoice-history__title">Faturas anteriores</h2>
                                        {history.length === 0 ? (
                                            <p className="invoice-total__note">Nenhuma fatura anterior.</p>
                                        ) : (
                                            <div className="invoice-history">
                                                {history.map((item) => (
                                                    <Link
                                                        key={item.key}
                                                        href={`/invoices?card=${selectedId}&invoice=${item.key}`}
                                                        className="invoice-history__item"
                                                    >
                                                        <span className="invoice-history__copy">
                                                            <span className="invoice-history__name">{item.label}</span>
                                                            <Chip tone={situationTone(item.situation)}>{item.situation_label}</Chip>
                                                        </span>
                                                        <span className="invoice-money">{formatPlainMoney(item.total)}</span>
                                                    </Link>
                                                ))}
                                            </div>
                                        )}
                                    </Card>
                                </div>
                            </div>
                        ) : null}
                    </>
                )}
            </div>
            {editing ? (
                <TransactionModal
                    key={editing.id}
                    mode="edit"
                    transaction={editing}
                    options={options}
                    onClose={() => setEditing(null)}
                />
            ) : null}
            {paying && invoice ? (
                <PayModal cardId={selectedId} invoice={invoice} onClose={() => setPaying(false)} />
            ) : null}
            <ConfirmDialog
                open={confirmUnpay}
                title="Desmarcar pagamento?"
                message={invoice ? `${invoice.title} deixa de constar como paga. As transações não mudam.` : ''}
                confirmLabel="Desmarcar"
                busyLabel="Desmarcando…"
                busy={unpaying}
                onCancel={() => setConfirmUnpay(false)}
                onConfirm={unpay}
            />
        </>
    );
}

function Group({ group, onEdit }) {
    const tone = group.key === 'posted' ? 'ok' : 'neutral';

    return (
        <>
            <tr className={group.key === 'forecast' ? 'invoice-group invoice-group--muted' : 'invoice-group'}>
                <th colSpan={4} scope="colgroup">{group.label}</th>
                <th className="invoice-money" scope="col">{formatPlainMoney(group.total)}</th>
            </tr>
            {group.transactions.map((transaction) => (
                <InvoiceRow
                    key={transaction.id}
                    transaction={transaction}
                    label={group.label}
                    tone={tone}
                    onEdit={onEdit}
                />
            ))}
        </>
    );
}

Index.layout = (page) => <AppLayout>{page}</AppLayout>;
