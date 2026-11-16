import './PageHeader.css';

export default function PageHeader({ title, subtitle, actions = null }) {
    return (
        <header className="page-header">
            <div className="page-header__copy">
                <h1>{title}</h1>
                {subtitle ? <p>{subtitle}</p> : null}
            </div>
            {actions ? <div className="page-header__actions">{actions}</div> : null}
        </header>
    );
}
