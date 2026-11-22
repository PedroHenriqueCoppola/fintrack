import { useEffect, useState } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { formatPlainMoney } from '../../lib/format';
import './BarsChart.css';

function useReducedMotion() {
    const [reduced, setReduced] = useState(() => (
        typeof window !== 'undefined'
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches
    ));

    useEffect(() => {
        const media = window.matchMedia('(prefers-reduced-motion: reduce)');
        const apply = () => setReduced(media.matches);
        apply();
        media.addEventListener('change', apply);

        return () => media.removeEventListener('change', apply);
    }, []);

    return reduced;
}

function shortMoney(raw) {
    const cents = BigInt(Math.trunc(Math.abs(raw || 0)));
    const sign = raw < 0 ? '−' : '';

    if (cents >= 100000000n) {
        return `${sign}R$ ${scaled(cents, 100000000n)} mi`;
    }

    if (cents >= 100000n) {
        return `${sign}R$ ${scaled(cents, 100000n)} mil`;
    }

    const whole = cents / 100n;
    const fraction = String(cents % 100n).padStart(2, '0');

    return `${sign}R$ ${whole},${fraction}`;
}

function scaled(cents, unit) {
    const whole = cents / unit;
    const tenth = (cents % unit) / (unit / 10n);

    if (tenth === 0n) {
        return whole.toString();
    }

    return `${whole},${tenth}`;
}

function ChartTooltip({ active, payload }) {
    if (!active || !payload?.length) {
        return null;
    }

    const point = payload[0].payload;

    return (
        <div className="bars-tooltip">
            <p>{point.caption}</p>
            <p>{`Receitas ${formatPlainMoney(point.income)}`}</p>
            <p>{`Despesas ${formatPlainMoney(point.expense)}`}</p>
        </div>
    );
}

function AxisTick({ x, y, payload, axis }) {
    const label = axis === 'y' ? shortMoney(payload?.value) : payload?.value;

    return (
        <text
            x={x}
            y={y}
            dy={axis === 'y' ? 4 : 14}
            textAnchor={axis === 'y' ? 'end' : 'middle'}
            fill="var(--color-text-subtle)"
            fontSize="1.1rem"
        >
            {label}
        </text>
    );
}

export default function BarsChart({ points = [], ariaLabel = '', axisMax = null }) {
    const reduced = useReducedMotion();

    if (!points.length) {
        return null;
    }

    return (
        <div className="bars-chart">
            <ul className="bars-chart__legend">
                <li>
                    <span className="bars-chart__key bars-chart__key--income" aria-hidden="true" />
                    Receitas
                </li>
                <li>
                    <span className="bars-chart__key bars-chart__key--expense" aria-hidden="true" />
                    Despesas
                </li>
            </ul>
            <div className="bars-chart__plot" role="img" aria-label={ariaLabel}>
                <div className="bars-chart__canvas" aria-hidden="true">
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart
                            data={points}
                            margin={{ top: 12, right: 8, left: 4, bottom: 0 }}
                            barGap={4}
                            barCategoryGap="22%"
                            accessibilityLayer={false}
                        >
                            <CartesianGrid stroke="var(--color-border)" vertical={false} />
                            <XAxis
                                dataKey="label"
                                axisLine={false}
                                tickLine={false}
                                interval={0}
                                tick={(props) => <AxisTick {...props} axis="x" />}
                            />
                            <YAxis
                                axisLine={false}
                                tickLine={false}
                                width={72}
                                domain={axisMax ? [0, axisMax] : [0, 'auto']}
                                tick={(props) => <AxisTick {...props} axis="y" />}
                            />
                            <Tooltip
                                content={<ChartTooltip />}
                                cursor={{ fill: 'var(--color-accent)' }}
                                isAnimationActive={!reduced}
                            />
                            <Bar
                                dataKey="income_cents"
                                name="Receitas"
                                fill="var(--color-positive)"
                                radius={[4, 4, 0, 0]}
                                maxBarSize={28}
                                isAnimationActive={!reduced}
                            />
                            <Bar
                                dataKey="expense_cents"
                                name="Despesas"
                                fill="var(--color-destructive)"
                                radius={[4, 4, 0, 0]}
                                maxBarSize={28}
                                isAnimationActive={!reduced}
                            />
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            </div>
            <table className="visually-hidden">
                <caption>{ariaLabel}</caption>
                <thead>
                    <tr>
                        <th>Mês</th>
                        <th>Receitas</th>
                        <th>Despesas</th>
                    </tr>
                </thead>
                <tbody>
                    {points.map((point) => (
                        <tr key={point.key}>
                            <th>{point.caption}</th>
                            <td>{formatPlainMoney(point.income)}</td>
                            <td>{formatPlainMoney(point.expense)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
