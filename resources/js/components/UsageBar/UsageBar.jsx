import './UsageBar.css';

export default function UsageBar({ percent, over = false, nearLimit = false }) {
    const capped = percent > 100 ? 100 : percent;

    return (
        <div className={over ? 'usage usage--over' : 'usage'}>
            <span className="usage__track" aria-hidden="true">
                <span className="usage__fill" style={{ width: `${capped}%` }} />
            </span>
            <span className="usage__percent">{percent}%</span>
            {nearLimit ? <span className="usage__near">Perto do limite</span> : null}
        </div>
    );
}
