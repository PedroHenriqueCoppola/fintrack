import './UsageBar.css';

export default function UsageBar({ percent, over = false, nearLimit = false, variant = 'usage', label = 'Progresso', complete = false }) {
    const value = typeof percent === 'number' ? percent : 0;
    const capped = value > 100 ? 100 : (value < 0 ? 0 : value);
    const overflow = variant === 'meter' ? false : (over || value > 100);

    if (variant === 'meter') {
        return (
            <span
                className={complete ? 'usage usage--meter usage--complete' : 'usage usage--meter'}
                role="progressbar"
                aria-valuenow={Math.round(value)}
                aria-valuemin={0}
                aria-valuemax={Math.max(100, Math.round(value))}
                aria-label={label}
            >
                <span className="usage__track" aria-hidden="true">
                    <span className="usage__fill" style={{ width: complete ? '100%' : `${capped}%` }} />
                </span>
            </span>
        );
    }

    return (
        <div className={overflow ? 'usage usage--over' : 'usage'}>
            <span className="usage__main">
                <span className="usage__track" aria-hidden="true">
                    <span className="usage__fill" style={{ width: `${capped}%` }} />
                </span>
                <span className="usage__percent">{percent}%</span>
            </span>
            <span className="usage__near">{nearLimit ? 'Perto do limite' : ''}</span>
        </div>
    );
}
