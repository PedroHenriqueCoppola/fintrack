import { useState } from 'react';
import { router } from '@inertiajs/react';
import { CalendarClock } from 'lucide-react';
import Button from '../../components/Button/Button';
import Card from '../../components/Card/Card';
import ConfirmDialog from '../../components/ConfirmDialog/ConfirmDialog';
import EmptyState from '../../components/EmptyState/EmptyState';
import FormField from '../../components/FormField/FormField';
import { formatPlainMoney } from '../../lib/format';
import CatalogIcon from '../Manage/icons';
import { FixedExpenseModal, InstallmentModal } from './ExpenseModal';
import { moneyPayload, moneyText } from './fields';
import { visitPlanning } from './visit';

function plain(value) {
    return formatPlainMoney(value);
}

function RecordRow({ item, kind, onEdit, onArchive, onRestore }) {
    return (
        <div className="plan-record">
            <span
                className={item.category?.color ? 'plan-tile' : 'plan-tile plan-tile--plain'}
                style={item.category?.color ? { '--item-color': item.category.color } : undefined}
                aria-hidden="true"
            >
                <CatalogIcon name={item.category?.icon} />
            </span>
            <div className="plan-record__copy">
                <div className="plan-record__title">
                    <span>{item.name}</span>
                    {item.high_impact ? <span className="plan-chip">Alto impacto</span> : null}
                    {item.paid_early_label ? <span className="plan-chip plan-chip--ok">{item.paid_early_label}</span> : null}
                    {item.reason ? <span className="plan-chip plan-chip--muted">{item.reason}</span> : null}
                </div>
                <p className="plan-record__meta">
                    {[item.category?.name, item.due_label, item.payment_label].filter(Boolean).join(' · ')}
                </p>
                {kind === 'installment' && item.progress_label ? (
                    <div className="plan-record__progress">
                        <span className="plan-progress" aria-hidden="true">
                            <span style={{ width: `${item.progress_percent}%` }} />
                        </span>
                        <span>{item.progress_label}</span>
                    </div>
                ) : null}
            </div>
            <div className="plan-record__side">
                <strong>{plain(item.monthly_amount)}</strong>
                <div className="plan-record__actions">
                    <Button variant="quiet" onClick={onEdit}>Editar</Button>
                    {item.archived ? (
                        <Button variant="quiet" onClick={onRestore}>Restaurar</Button>
                    ) : (
                        <Button variant="quiet" onClick={onArchive}>Arquivar</Button>
                    )}
                </div>
            </div>
        </div>
    );
}

function RecordGroup({ title, items, kind, onEdit, onArchive, onRestore }) {
    if (items.length === 0) {
        return null;
    }

    return (
        <Card title={title}>
            {items.map((item) => (
                <RecordRow
                    key={`${kind}-${item.id}`}
                    item={item}
                    kind={kind}
                    onEdit={() => onEdit(item)}
                    onArchive={() => onArchive(item)}
                    onRestore={() => onRestore(item)}
                />
            ))}
        </Card>
    );
}

export default function FixedPanel({ page, editor, setEditor }) {
    const fixed = page.fixed;
    const indicators = fixed.indicators;
    const draft = page.plan.status !== 'confirmed';
    const [archive, setArchive] = useState(null);
    const [archiving, setArchiving] = useState(false);
    const [percent, setPercent] = useState(String(fixed.high_impact_percent));
    const [minAmount, setMinAmount] = useState(fixed.filters.min_amount ? moneyText(fixed.filters.min_amount) : '');
    const [maxAmount, setMaxAmount] = useState(fixed.filters.max_amount ? moneyText(fixed.filters.max_amount) : '');

    function applyFilters(overrides) {
        visitPlanning(page, {
            tab: 'fixed',
            category_id: fixed.filters.category_id || undefined,
            min_amount: fixed.filters.min_amount || undefined,
            max_amount: fixed.filters.max_amount || undefined,
            sort: fixed.filters.sort,
            ...overrides,
        });
    }

    function savePercent() {
        const digits = String(percent).replace(/\D/g, '');
        router.put('/planning/settings', { high_impact_percent: digits }, {
            preserveScroll: true,
            preserveState: true,
        });
    }

    function confirmArchive() {
        if (!archive) {
            return;
        }

        setArchiving(true);
        const url = archive.kind === 'fixed'
            ? `/planning/fixed-expenses/${archive.item.id}/archive`
            : `/planning/installments/${archive.item.id}/archive`;

        router.post(url, {}, {
            preserveScroll: true,
            onFinish: () => {
                setArchiving(false);
                setArchive(null);
            },
        });
    }

    function restore(kind, item) {
        const url = kind === 'fixed'
            ? `/planning/fixed-expenses/${item.id}/restore`
            : `/planning/installments/${item.id}/restore`;

        router.post(url, {}, { preserveScroll: true });
    }

    const outsideCount = fixed.outside_expenses.length + fixed.outside_installments.length;
    const listsEmpty = fixed.expenses.length === 0
        && fixed.installments.length === 0
        && outsideCount === 0
        && fixed.archived_expenses.length === 0
        && fixed.archived_installments.length === 0;

    return (
        <div className="plan-fixed">
            {page.navigation.is_future && draft ? (
                <p className="plan-draft-note">Os pendentes deste mês são gerados quando você confirma o planejamento.</p>
            ) : null}
            {page.navigation.is_current ? (
                <p className="plan-draft-note">Os pendentes deste mês já são gerados automaticamente.</p>
            ) : null}
            <div className="plan-stats">
                <Card className="plan-stat">
                    <p className="plan-kicker">Total mensal comprometido</p>
                    <p className="plan-stat__value">{plain(indicators.committed)}</p>
                </Card>
                <Card className="plan-stat">
                    <p className="plan-kicker">Fixos</p>
                    <p className="plan-stat__value">{indicators.fixed_count}</p>
                    <p className="plan-note">{plain(indicators.fixed_amount)}</p>
                </Card>
                <Card className="plan-stat">
                    <p className="plan-kicker">Parcelados ativos</p>
                    <p className="plan-stat__value">{indicators.active_installments}</p>
                </Card>
                <Card className="plan-stat">
                    <p className="plan-kicker">Parcelas pagas/totais</p>
                    <p className="plan-stat__value">
                        {indicators.installments_total === 0
                            ? '—'
                            : `${indicators.installments_paid}/${indicators.installments_total}`}
                    </p>
                </Card>
                <Card className="plan-stat">
                    <p className="plan-kicker">Já quitado no mês</p>
                    <p className="plan-stat__value">{plain(indicators.settled)}</p>
                </Card>
                <Card className="plan-stat">
                    <p className="plan-kicker">% do orçamento comprometido</p>
                    <p className="plan-stat__value">
                        {indicators.committed_percent == null ? '—' : `${indicators.committed_percent}%`}
                    </p>
                </Card>
            </div>
            <FormField label="Alto impacto a partir de" hint="Porcentagem do planejado de gastos.">
                <span className="plan-impact">
                    <input
                        inputMode="numeric"
                        aria-label="Alto impacto a partir de"
                        value={percent}
                        onChange={(event) => setPercent(event.target.value.replace(/\D/g, '').slice(0, 3))}
                        onBlur={savePercent}
                    />
                    <span>%</span>
                </span>
            </FormField>
            <div className="plan-filters">
                <FormField label="Categoria">
                    <select
                        value={fixed.filters.category_id ?? ''}
                        onChange={(event) => applyFilters({
                            category_id: event.target.value || undefined,
                        })}
                    >
                        <option value="">Todas</option>
                        {page.options.filter_categories.map((category) => (
                            <option key={category.id} value={category.id}>{category.name}</option>
                        ))}
                    </select>
                </FormField>
                <FormField label="Valor mínimo">
                    <input
                        inputMode="decimal"
                        value={minAmount}
                        onChange={(event) => setMinAmount(event.target.value)}
                        onBlur={() => applyFilters({
                            min_amount: moneyPayload(minAmount) || undefined,
                        })}
                    />
                </FormField>
                <FormField label="Valor máximo">
                    <input
                        inputMode="decimal"
                        value={maxAmount}
                        onChange={(event) => setMaxAmount(event.target.value)}
                        onBlur={() => applyFilters({
                            max_amount: moneyPayload(maxAmount) || undefined,
                        })}
                    />
                </FormField>
                <FormField label="Ordenação">
                    <select
                        value={fixed.filters.sort}
                        onChange={(event) => applyFilters({ sort: event.target.value })}
                    >
                        <option value="name">Nome</option>
                        <option value="amount">Valor</option>
                        <option value="due">Vencimento</option>
                    </select>
                </FormField>
            </div>
            {listsEmpty ? (
                <Card className="card--fit">
                    <EmptyState
                        icon={CalendarClock}
                        message={fixed.has_records
                            ? 'Nenhum item com esses filtros.'
                            : 'Cadastre um gasto fixo ou um parcelado para acompanhar o que já está comprometido.'}
                    />
                    {!fixed.has_records ? (
                        <div className="plan-empty-actions">
                            <Button onClick={() => setEditor({ kind: 'fixed', item: null })}>Novo fixo</Button>
                            <Button variant="secondary" onClick={() => setEditor({ kind: 'installment', item: null })}>
                                Novo parcelado
                            </Button>
                        </div>
                    ) : null}
                </Card>
            ) : (
                <>
                    <RecordGroup
                        title="Gastos fixos"
                        items={fixed.expenses}
                        kind="fixed"
                        onEdit={(item) => setEditor({ kind: 'fixed', item })}
                        onArchive={(item) => setArchive({ kind: 'fixed', item })}
                        onRestore={(item) => restore('fixed', item)}
                    />
                    <RecordGroup
                        title="Parcelados"
                        items={fixed.installments}
                        kind="installment"
                        onEdit={(item) => setEditor({ kind: 'installment', item })}
                        onArchive={(item) => setArchive({ kind: 'installment', item })}
                        onRestore={(item) => restore('installment', item)}
                    />
                    {outsideCount > 0 ? (
                        <details className="plan-archived">
                            <summary>Fora deste mês ({outsideCount})</summary>
                            <RecordGroup
                                title="Gastos fixos"
                                items={fixed.outside_expenses}
                                kind="fixed"
                                onEdit={(item) => setEditor({ kind: 'fixed', item })}
                                onArchive={(item) => setArchive({ kind: 'fixed', item })}
                                onRestore={(item) => restore('fixed', item)}
                            />
                            <RecordGroup
                                title="Parcelados"
                                items={fixed.outside_installments}
                                kind="installment"
                                onEdit={(item) => setEditor({ kind: 'installment', item })}
                                onArchive={(item) => setArchive({ kind: 'installment', item })}
                                onRestore={(item) => restore('installment', item)}
                            />
                        </details>
                    ) : null}
                    {fixed.archived_expenses.length + fixed.archived_installments.length > 0 ? (
                        <details className="plan-archived">
                            <summary>Arquivados</summary>
                            <RecordGroup
                                title="Fixos arquivados"
                                items={fixed.archived_expenses}
                                kind="fixed"
                                onEdit={(item) => setEditor({ kind: 'fixed', item })}
                                onArchive={(item) => setArchive({ kind: 'fixed', item })}
                                onRestore={(item) => restore('fixed', item)}
                            />
                            <RecordGroup
                                title="Parcelados arquivados"
                                items={fixed.archived_installments}
                                kind="installment"
                                onEdit={(item) => setEditor({ kind: 'installment', item })}
                                onArchive={(item) => setArchive({ kind: 'installment', item })}
                                onRestore={(item) => restore('installment', item)}
                            />
                        </details>
                    ) : null}
                </>
            )}
            {editor?.kind === 'fixed' ? (
                <FixedExpenseModal
                    item={editor.item}
                    options={page.options}
                    onClose={() => setEditor(null)}
                />
            ) : null}
            {editor?.kind === 'installment' ? (
                <InstallmentModal
                    item={editor.item}
                    options={page.options}
                    onClose={() => setEditor(null)}
                />
            ) : null}
            <ConfirmDialog
                open={Boolean(archive)}
                title={archive?.kind === 'installment' ? 'Arquivar parcelado?' : 'Arquivar gasto fixo?'}
                message={`${archive?.item?.name ?? ''}. Os pendentes futuros serão removidos; o que já foi pago continua.`}
                confirmLabel="Arquivar"
                busyLabel="Arquivando…"
                busy={archiving}
                onConfirm={confirmArchive}
                onCancel={() => setArchive(null)}
            />
        </div>
    );
}
