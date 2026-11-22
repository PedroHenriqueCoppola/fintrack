import { useEffect, useState } from 'react';
import { Cell, Pie, PieChart, ResponsiveContainer } from 'recharts';
import { formatPlainMoney } from '../../lib/format';
import './DonutChart.css';

const TRACK = [{ cents: 1 }];

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

function sliceColor(color) {
    return color || 'var(--color-text-subtle)';
}

export default function DonutChart({ slices = [], total = '0.00', ariaLabel = '' }) {
    const reduced = useReducedMotion();

    if (!slices.length) {
        return null;
    }

    return (
        <div className="donut-chart">
            <div className="donut-chart__plot" role="img" aria-label={ariaLabel}>
                <div className="donut-chart__canvas" aria-hidden="true">
                    <ResponsiveContainer width="100%" height="100%">
                        <PieChart>
                            <Pie
                                data={TRACK}
                                dataKey="cents"
                                innerRadius={70}
                                outerRadius={84}
                                fill="var(--color-border-input)"
                                stroke="none"
                                isAnimationActive={false}
                            />
                            <Pie
                                data={slices}
                                dataKey="cents"
                                nameKey="name"
                                innerRadius={70}
                                outerRadius={84}
                                stroke="none"
                                paddingAngle={slices.length > 1 ? 1.5 : 0}
                                isAnimationActive={!reduced}
                            >
                                {slices.map((slice) => (
                                    <Cell key={slice.key} fill={sliceColor(slice.color)} />
                                ))}
                            </Pie>
                        </PieChart>
                    </ResponsiveContainer>
                </div>
                <div className="donut-chart__center">
                    <span>Total</span>
                    <strong>{formatPlainMoney(total)}</strong>
                </div>
            </div>
            <p className="visually-hidden">{`Total ${formatPlainMoney(total)}`}</p>
            <ul className="donut-chart__legend">
                {slices.map((slice) => (
                    <li key={slice.key}>
                        <span
                            className="donut-chart__swatch"
                            style={{ background: sliceColor(slice.color) }}
                            aria-hidden="true"
                        />
                        <span className="donut-chart__name">{slice.name}</span>
                        <span className="donut-chart__amount">{formatPlainMoney(slice.amount)}</span>
                        <span className="donut-chart__percent">
                            {slice.percent === null || slice.percent === undefined ? '—' : `${slice.percent}%`}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
