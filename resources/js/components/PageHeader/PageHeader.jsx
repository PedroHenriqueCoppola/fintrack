import './PageHeader.css';

export default function PageHeader({ title, subtitle, actions = null, badge = null }) {
    return (
        <header className="page-header">
            <div className="page-header__copy">
                <div className="page-header__title">
                    <h1>{title}</h1>
                    {badge}
                </div>
                {subtitle ? <p>{subtitle}</p> : null}
            </div>
            {actions ? <div className="page-header__actions">{actions}</div> : null}
        </header>
    );
}
