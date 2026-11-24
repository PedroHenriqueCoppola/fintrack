import { Link } from '@inertiajs/react';
import UsageBar from '../../components/UsageBar/UsageBar';
import { formatNeutralMoney, formatPlainMoney } from '../../lib/format';
import CatalogIcon from '../Manage/icons';

function stateChip(goal) {
    if (goal.state === 'completed') {
        return { label: 'Concluída', ok: true };
    }

    if (goal.state === 'archived') {
        return { label: 'Arquivada', ok: false };
    }

    return { label: 'Ativa', ok: false };
}

export function GoalChip({ goal, showState = false }) {
    const chips = [];

    if (showState || goal.state === 'completed' || goal.state === 'archived') {
        chips.push(stateChip(goal));
    }

    if (goal.overdue) {
        chips.push({ label: 'Prazo vencido', ok: false });
    }

    if (chips.length === 0) {
        return null;
    }

    return (
        <span className="goal-card__chips">
            {chips.map((chip) => (
                <span key={chip.label} className={chip.ok ? 'goal-chip goal-chip--ok' : 'goal-chip goal-chip--neutral'}>
                    {chip.label}
                </span>
            ))}
        </span>
    );
}

export function goalResult(goal) {
    if (!goal.reached) {
        return `Falta ${formatPlainMoney(goal.remaining)}`;
    }

    if (goal.surplus && goal.surplus !== '0.00') {
        return `Meta atingida · ${formatPlainMoney(goal.surplus)} acima do alvo`;
    }

    return 'Meta atingida';
}

export default function GoalCard({ goal, showState = false }) {
    const category = goal.category;

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
                <GoalChip goal={goal} showState={showState} />
            </span>
            <UsageBar
                variant="meter"
                percent={goal.percent}
                    complete={goal.reached}
                label={`Progresso de ${goal.name}`}
            />
            <span className="goal-card__figures">
                <span>{`${formatPlainMoney(goal.progress)} de ${formatPlainMoney(goal.target_amount)}`}</span>
                <span>{`${goal.percent}%`}</span>
            </span>
            <span className="goal-card__line">{goalResult(goal)}</span>
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
