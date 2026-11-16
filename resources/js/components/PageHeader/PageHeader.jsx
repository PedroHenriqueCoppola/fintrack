import { Plus } from 'lucide-react';
import Button from '../Button/Button';
import './PageHeader.css';

export function DisabledTransactionButton() {
    return (
        <div className="page-header__soon">
            <Button disabled aria-describedby="nova-transacao-aviso">
                <Plus aria-hidden="true" strokeWidth={1.75} />
                Nova transação
            </Button>
            <p id="nova-transacao-aviso" className="page-header__hint">Em breve</p>
        </div>
    );
}

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
