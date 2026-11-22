import { useEffect, useId, useState } from 'react';
import {
    Area,
    AreaChart,
    CartesianGrid,
    ResponsiveContainer,
    ReferenceLine,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { formatPlainMoney } from '../../lib/format';
import { decimalFromCents } from '../../lib/cents';
import './EvolutionChart.css';

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
    if (typeof raw !== 'number' || raw !== raw) {
        return '';
    }

    const negative = raw < 0;
    const cents = BigInt(Math.round(Math.abs(raw)));
    const sign = negative ? '−' : '';

    if (cents >= 100000000n) {
        return `${sign}R$ ${scaled(cents, 100000000n)} mi`;
    }

    if (cents >= 100000n) {
        return `${sign}R$ ${scaled(cents, 100000n)} mil`;
    }

    return `${sign}${formatPlainMoney(decimalFromCents(cents))}`;
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
        <div className="evolution-tooltip">
            <p>{point.caption}</p>
            <p>{formatPlainMoney(point.value)}</p>
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

export default function EvolutionChart({
    points,
    ariaLabel,
    referenceValue = null,
    referenceLabel = null,
}) {
    const reduced = useReducedMotion();
    const gradientId = `evolution-${useId().replace(/:/g, '')}`;
    const lastIndex = points.length - 1;
    const referenceCents = typeof referenceValue === 'number' ? referenceValue : null;

    if (!points.length) {
        return null;
    }

    return (
        <div className="evolution-chart">
            <div className="evolution-chart__plot" role="img" aria-label={ariaLabel}>
            <div className="evolution-chart__canvas" aria-hidden="true">
                <ResponsiveContainer width="100%" height="100%">
                    <AreaChart
                        data={points}
                        margin={{ top: 12, right: 12, left: 4, bottom: 0 }}
                        accessibilityLayer={false}
                    >
                        <defs>
                            <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stopColor="var(--color-primary-soft)" stopOpacity={0.22} />
                                <stop offset="100%" stopColor="var(--color-primary-soft)" stopOpacity={0} />
                            </linearGradient>
                        </defs>
                        <CartesianGrid stroke="var(--color-border)" vertical={false} />
                        <XAxis
                            dataKey="label"
                            axisLine={false}
                            tickLine={false}
                            minTickGap={16}
                            tick={(props) => <AxisTick {...props} axis="x" />}
                        />
                        <YAxis
                            dataKey="cents"
                            axisLine={false}
                            tickLine={false}
                            width={84}
                            domain={([min, max]) => {
                                let low = Math.round(min);
                                let high = Math.round(max);

                                if (referenceCents !== null) {
                                    low = Math.min(low, referenceCents);
                                    high = Math.max(high, referenceCents);
                                }

                                const span = high - low;
                                const pad = span === 0 ? Math.max(Math.round(Math.abs(high) / 10), 1) : Math.round(span / 5);
                                let floor = low - pad;

                                if (low >= 0 && (referenceCents === null || referenceCents >= 0)) {
                                    floor = Math.max(0, floor);
                                }

                                return [floor, high + pad];
                            }}
                            tick={(props) => <AxisTick {...props} axis="y" />}
                        />
                        <Tooltip
                            content={<ChartTooltip />}
                            cursor={{ stroke: 'var(--color-border)' }}
                            isAnimationActive={!reduced}
                        />
                        {referenceCents !== null ? (
                            <ReferenceLine
                                y={referenceCents}
                                stroke="var(--color-text-subtle)"
                                strokeDasharray="4 4"
                                ifOverflow="extendDomain"
                            />
                        ) : null}
                        <Area
                            type="monotone"
                            dataKey="cents"
                            stroke="var(--color-primary-soft)"
                            strokeWidth={2.4}
                            fill={`url(#${gradientId})`}
                            isAnimationActive={!reduced}
                            dot={(props) => {
                                if (props.index !== lastIndex || props.cx == null || props.cy == null) {
                                    return null;
                                }

                                return (
                                    <circle
                                        key={props.index}
                                        cx={props.cx}
                                        cy={props.cy}
                                        r={4.5}
                                        fill="var(--color-primary-soft)"
                                        stroke="var(--color-card)"
                                        strokeWidth={2}
                                    />
                                );
                            }}
                            activeDot={{
                                r: 5,
                                fill: 'var(--color-primary-soft)',
                                stroke: 'var(--color-card)',
                                strokeWidth: 2,
                            }}
                        />
                    </AreaChart>
                </ResponsiveContainer>
            </div>
            </div>
            {referenceLabel ? <p className="evolution-chart__legend">{referenceLabel}</p> : null}
            <ul className="visually-hidden">
                {points.map((point) => (
                    <li key={`${point.label}-${point.caption}`}>{`${point.caption}: ${formatPlainMoney(point.value)}`}</li>
                ))}
                {referenceLabel ? <li>{referenceLabel}</li> : null}
            </ul>
        </div>
    );
}
