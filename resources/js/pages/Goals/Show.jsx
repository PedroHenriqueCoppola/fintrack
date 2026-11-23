import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import Button from '../../components/Button/Button';
import Card from '../../components/Card/Card';
import ConfirmDialog from '../../components/ConfirmDialog/ConfirmDialog';
import EvolutionChart from '../../components/EvolutionChart/EvolutionChart';
import PageHeader from '../../components/PageHeader/PageHeader';
import TransactionModal from '../../components/TransactionModal/TransactionModal';
import UsageBar from '../../components/UsageBar/UsageBar';
import AppLayout from '../../layouts/AppLayout/AppLayout';
import { formatDate, formatNeutralMoney, formatPlainMoney } from '../../lib/format';
import { GoalChip } from './GoalCard';
import GoalModal from './GoalModal';
import PeriodBar from './PeriodBar';
import '../../components/DatePicker/DatePicker.css';
import './Index.css';

function firstError(errors) {
    const value = Object.values(errors ?? {})[0];

    if (!value) {
        return '';
    }

    return Array.isArray(value) ? value[0] : value;
}

function Figure({ label, value }) {
    return (
        <div>
            <p className="goal-stat__label">{label}</p>
            <p className="goal-stat__value goal-stat__value--small">{value}</p>
        </div>
    );
}

export default function Show({
    filters,
    period,
    goal,
    chart,
    movements,
    analysis_href: analysisHref,
    list_href: listHref,
    reserve_categories: reserveCategories,
    options,
}) {
    const { errors } = usePage().props;
    const banner = firstError(errors);
    const [editing, setEditing] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const [archiving, setArchiving] = useState(false);
    const [preset, setPreset] = useState(null);
    const archived = goal.state === 'archived';
    const categoryArchived = Boolean(goal.category?.archived);
    const canMove = !archived && !categoryArchived;
    const canWithdraw = canMove && goal.progress !== '0.00' && !String(goal.progress).startsWith('-');
    const withdrawTitle = categoryArchived
        ? 'A categoria está arquivada. Desarquive em Gerenciar para lançar.'
        : (canWithdraw ? 'Resgatar da reserva' : 'Não há valor guardado nesta meta para resgatar.');

    function openTransaction(action) {
        setPreset({
            type: 'reserve',
            reserve_action: action,
            category_id: goal.category_id,
            category: goal.category,
            lockCategory: true,
            goalProgress: goal.progress,
        });
    }

    function restore() {
        router.patch(`/goals/${goal.id}/restore`, {}, { preserveScroll: true });
    }

    function archive() {
        setArchiving(true);
        router.patch(`/goals/${goal.id}/archive`, {}, {
            preserveScroll: true,
            onFinish: () => {
                setArchiving(false);
                setConfirming(false);
            },
        });
    }

    return (
        <>
            <Head title="Metas" />
            <div className="page goals-page goals-show">
                <p className="goals-back">
                    <Link href={listHref}>Voltar para metas</Link>
                </p>
                <PageHeader
                    title={goal.name}
                    subtitle={goal.category?.name}
                    badge={<GoalChip goal={goal} />}
                    actions={(
                        <>
                            {!archived ? (
                                <Button
                                    onClick={() => openTransaction('deposit')}
                                    disabled={!canMove}
                                    title={canMove ? 'Guardar na reserva' : withdrawTitle}
                                >
                                    Guardar
                                </Button>
                            ) : null}
                            {!archived ? (
                                <Button
                                    variant="secondary"
                                    onClick={() => openTransaction('withdraw')}
                                    disabled={!canWithdraw}
                                    title={withdrawTitle}
                                >
                                    Resgatar
                                </Button>
                            ) : null}
                            {archived ? null : (
                                <Button variant="secondary" onClick={() => setEditing(true)}>Editar</Button>
                            )}
                            {archived ? (
                                <Button variant="secondary" onClick={restore}>Restaurar</Button>
                            ) : (
                                <Button variant="quiet" onClick={() => setConfirming(true)}>Arquivar</Button>
                            )}
                        </>
                    )}
                />
                {banner && !preset && !editing ? <p className="goals-alert" role="alert">{banner}</p> : null}
                {goal.notes ? <p className="goals-note">{goal.notes}</p> : null}
                <PeriodBar path={`/goals/${goal.id}`} filters={filters} period={period} />
                <div className="goal-detail">
                    <Card className="card--highlight">
                        <div className="goal-meter">
                            <UsageBar
                                variant="meter"
                                percent={goal.percent}
                                complete={goal.percent >= 100}
                                label={`Progresso de ${goal.name}`}
                            />
                        </div>
                        <div className="goal-progress">
                            <Figure label="Guardado" value={formatPlainMoney(goal.progress)} />
                            <Figure label="Percentual" value={`${goal.percent}%`} />
                            <Figure
                                label={goal.reached ? 'Meta atingida' : 'Falta'}
                                value={goal.reached
                                    ? (goal.surplus !== '0.00' ? `${formatPlainMoney(goal.surplus)} acima do alvo` : '—')
                                    : formatPlainMoney(goal.remaining)}
                            />
                            <Figure
                                label="Prazo"
                                value={goal.deadline_phrase ?? 'Sem prazo'}
                            />
                            <Figure
                                label="Guardar por mês"
                                value={goal.monthly_amount ? formatPlainMoney(goal.monthly_amount) : '—'}
                            />
                            <Figure label="Guardado no período" value={formatNeutralMoney(goal.period_saved)} />
                        </div>
                        {goal.overdue ? <p className="goals-note">Prazo vencido</p> : null}
                    </Card>
                    <Card>
                        <div className="goals-head">
                            <h2>Evolução da meta</h2>
                        </div>
                        {chart.points.length === 0 ? (
                            <p className="goals-note">{chart.empty_message}</p>
                        ) : (
                            <EvolutionChart
                                points={chart.points}
                                ariaLabel={chart.aria_label}
                                referenceValue={chart.reference_cents}
                                referenceLabel={chart.reference_label}
                            />
                        )}
                    </Card>
                </div>
                <Card>
                    <div className="goals-head">
                        <h2>Últimas movimentações</h2>
                        <Link href={analysisHref}>Ver na Análise</Link>
                    </div>
                    {movements.length === 0 ? (
                        <p className="goals-note">Nenhuma movimentação neste período.</p>
                    ) : (
                        <div className="goal-movements">
                            {movements.map((movement) => (
                                <div key={movement.id} className="goal-movement">
                                    <span className="goal-movement__date">{formatDate(movement.date)}</span>
                                    <span className="goal-movement__name">{movement.description}</span>
                                    <span className="goal-chip goal-chip--neutral">
                                        {movement.reserve_action === 'withdraw' ? 'Resgate' : 'Guardar'}
                                    </span>
                                    <span className="goal-movement__status">
                                        {movement.status === 'pending' ? 'Pendente' : 'Paga'}
                                    </span>
                                    <span className="goal-movement__amount">{formatPlainMoney(movement.amount)}</span>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>
            </div>
            {preset ? (
                <TransactionModal
                    mode="create"
                    options={options}
                    preset={preset}
                    onClose={() => setPreset(null)}
                />
            ) : null}
            {editing ? (
                <GoalModal
                    goal={goal}
                    categories={reserveCategories}
                    onClose={() => setEditing(false)}
                />
            ) : null}
            {confirming ? (
                <ConfirmDialog
                    open
                    title="Arquivar meta?"
                    confirmLabel="Arquivar"
                    busy={archiving}
                    busyLabel="Arquivando…"
                    onConfirm={archive}
                    onCancel={() => setConfirming(false)}
                >
                    {`${goal.name} sai das listas e dos totais. Você pode restaurar depois.`}
                </ConfirmDialog>
            ) : null}
        </>
    );
}

Show.layout = (page) => <AppLayout>{page}</AppLayout>;
