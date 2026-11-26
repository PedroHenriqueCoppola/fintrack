import { Link, usePage } from '@inertiajs/react';
import Card from '../../components/Card/Card';

export default function AuthFrame({ mode, title, subtitle, alert = null, children }) {
    const { registrationEnabled = true } = usePage().props;

    return (
        <div className="auth-page">
            <Card className="auth-card">
                <nav className="auth-segment" aria-label="Acesso">
                    {mode === 'login' ? (
                        <span className="auth-segment__item auth-segment__item--active" aria-current="page">Entrar</span>
                    ) : (
                        <Link className="auth-segment__item" href="/login">Entrar</Link>
                    )}
                    {registrationEnabled ? (
                        mode === 'register' ? (
                            <span className="auth-segment__item auth-segment__item--active" aria-current="page">Criar conta</span>
                        ) : (
                            <Link className="auth-segment__item" href="/register">Criar conta</Link>
                        )
                    ) : null}
                </nav>
                <h1>{title}</h1>
                <p className="auth-sub">{subtitle}</p>
                {alert}
                {children}
            </Card>
            <p className="auth-note">Seus dados ficam só na sua conta.</p>
        </div>
    );
}
