import { Link } from '@inertiajs/react';
import UsageBar from '../../components/UsageBar/UsageBar';
import { formatNeutralMoney, formatPlainMoney } from '../../lib/format';
import CatalogIcon from '../Manage/icons';

export function GoalChip({ goal }) {
    if (goal.state === 'completed') {
        return <span className="goal-chip goal-chip--ok">Concluída</span>;
    }

    if (goal.state === 'archived') {
        return <span className="goal-chip goal-chip--neutral">Arquivada</span>;
    }

    if (goal.overdue) {
        return <span className="goal-chip goal-chip--neutral">Prazo vencido</span>;
    }

    return null;
}

export default function GoalCard({ goal }) {
    const category = goal.category;
    const overflow = goal.percent > 100;

    return (
        <Link href={goal.href} className="card card--interactive goal-card">
            <span className="goal-card__top">
                <span
                    className={category?.color ? 'goal-tile' : 'goal-tile goal-tile--plain'}
                    style={category?.color ? { '--item-color': category.color } : undefined}
                    aria-hidden="true"
                >
                    <CatalogIcon name={category?.icon} />
                </span>
                <span className="goal-card__copy">
                    <span className="goal-card__name">{goal.name}</span>
                    <span className="goal-card__category">{category?.name}</span>
                </span>
                <GoalChip goal={goal} />
            </span>
            <UsageBar
                variant="meter"
                percent={goal.percent}
                over={overflow}
                label={`Progresso de ${goal.name}`}
            />
            {overflow ? <span className="goal-card__over">Passou do valor</span> : null}
            <span className="goal-card__figures">
                <span>{`${formatPlainMoney(goal.progress)} de ${formatPlainMoney(goal.target_amount)}`}</span>
                <span>{`${goal.percent}%`}</span>
            </span>
            <span className="goal-card__line">{`Falta ${formatPlainMoney(goal.remaining)}`}</span>
            <span className="goal-card__muted">
                {`Guardado no período: ${formatNeutralMoney(goal.period_saved)}`}
            </span>
            {goal.deadline_phrase ? (
                <span className="goal-card__muted">{`Prazo: ${goal.deadline_phrase}`}</span>
            ) : null}
            {goal.monthly_amount ? (
                <span className="goal-card__muted">{`Guarde ${formatPlainMoney(goal.monthly_amount)} por mês`}</span>
            ) : null}
        </Link>
    );
}
