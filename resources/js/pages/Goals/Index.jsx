import { useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { Plus, Target } from 'lucide-react';
import Button from '../../components/Button/Button';
import Card from '../../components/Card/Card';
import EmptyState from '../../components/EmptyState/EmptyState';
import EvolutionChart from '../../components/EvolutionChart/EvolutionChart';
import PageHeader from '../../components/PageHeader/PageHeader';
import AppLayout from '../../layouts/AppLayout/AppLayout';
import { formatNeutralMoney, formatPlainMoney } from '../../lib/format';
import GoalCard from './GoalCard';
import GoalModal from './GoalModal';
import PeriodBar, { visitGoals } from './PeriodBar';
import '../../components/DatePicker/DatePicker.css';
import './Index.css';

const STATUSES = [
    { key: 'active', label: 'Ativas', empty: 'Nenhuma meta ativa.' },
    { key: 'completed', label: 'Concluídas', empty: 'Nenhuma meta concluída.' },
    { key: 'archived', label: 'Arquivadas', empty: 'Nenhuma meta arquivada.' },
];

function firstError(errors) {
    const value = Object.values(errors ?? {})[0];

    if (!value) {
        return '';
    }

    return Array.isArray(value) ? value[0] : value;
}

function Stat({ label, value }) {
    return (
        <Card>
            <p className="goal-stat__label">{label}</p>
            <p className="goal-stat__value">{value}</p>
        </Card>
    );
}

export default function Index({
    filters,
    period,
    status,
    panel,
    chart,
    goals,
    has_goals: hasGoals,
    reserve_categories: reserveCategories,
    has_active_reserve: hasActiveReserve,
}) {
    const { errors } = usePage().props;
    const banner = firstError(errors);
    const [creating, setCreating] = useState(false);
    const emptyStatus = STATUSES.find((item) => item.key === status) ?? STATUSES[0];

    function chooseStatus(next) {
        visitGoals('/goals', { ...filters, status: next }, { keepStatus: true });
    }

    return (
        <>
            <Head title="Metas" />
            <div className="page goals-page">
                <PageHeader
                    title="Metas"
                    subtitle="Acompanhe o que você quer conquistar."
                    actions={(
                        <Button onClick={() => setCreating(true)} disabled={!hasActiveReserve}>
                            <Plus aria-hidden="true" strokeWidth={1.75} />
                            Nova meta
                        </Button>
                    )}
                />
                {banner ? <p className="goals-alert" role="alert">{banner}</p> : null}
                {!hasActiveReserve ? (
                    <p className="goals-note">
                        Nenhuma categoria de Reserva ativa.
                        {' '}
                        <Link href="/manage">Cadastre uma em Gerenciar.</Link>
                    </p>
                ) : null}
                <PeriodBar path="/goals" filters={filters} period={period} keepStatus />
                {!hasGoals ? (
                    <Card>
                        <EmptyState
                            icon={Target}
                            message="Você ainda não tem metas."
                            shortcutLabel={hasActiveReserve ? 'Criar meta' : null}
                            onShortcut={hasActiveReserve ? () => setCreating(true) : undefined}
                        />
                    </Card>
                ) : (
                    <>
                        <div className="goal-stats">
                            <Stat label="Total guardado" value={formatPlainMoney(panel.saved)} />
                            <Stat label="Metas ativas" value={String(panel.active_count)} />
                            <Stat label="Metas concluídas" value={String(panel.completed_count)} />
                            <Stat label="Guardado no período" value={formatNeutralMoney(panel.period_saved)} />
                        </div>
                        <Card>
                            <div className="goals-head">
                                <h2>Evolução geral do guardado</h2>
                            </div>
                            {chart.points.length === 0 ? (
                                <p className="goals-note">{chart.empty_message}</p>
                            ) : (
                                <EvolutionChart points={chart.points} ariaLabel={chart.aria_label} />
                            )}
                        </Card>
                        <div className="period-seg goal-status" role="group" aria-label="Situação das metas">
                            {STATUSES.map((item) => (
                                <button
                                    key={item.key}
                                    type="button"
                                    aria-pressed={status === item.key}
                                    onClick={() => chooseStatus(item.key)}
                                >
                                    {item.label}
                                </button>
                            ))}
                        </div>
                        {goals.length === 0 ? (
                            <Card>
                                <EmptyState icon={Target} message={emptyStatus.empty} />
                            </Card>
                        ) : (
                            <div className="goal-grid">
                                {goals.map((goal) => (
                                    <GoalCard key={goal.id} goal={goal} />
                                ))}
                            </div>
                        )}
                    </>
                )}
            </div>
            {creating ? (
                <GoalModal
                    categories={reserveCategories}
                    onClose={() => setCreating(false)}
                />
            ) : null}
        </>
    );
}

Index.layout = (page) => <AppLayout>{page}</AppLayout>;
