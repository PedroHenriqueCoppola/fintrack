import { useEffect, useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import Button from '../../components/Button/Button';
import Card from '../../components/Card/Card';
import FormField from '../../components/FormField/FormField';
import PageHeader from '../../components/PageHeader/PageHeader';
import GuestLayout from '../../layouts/GuestLayout/GuestLayout';
import './auth.css';

function formatLock(total) {
    const minutes = Math.floor(total / 60);
    const seconds = total % 60;

    if (minutes > 0) {
        return `Tente novamente em ${minutes} min ${seconds} s`;
    }

    return `Tente novamente em ${seconds} s`;
}

export default function Login() {
    const { loginLockSeconds = 0 } = usePage().props;
    const [remaining, setRemaining] = useState(() => Number(loginLockSeconds) || 0);
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    useEffect(() => {
        const start = Number(loginLockSeconds) || 0;
        setRemaining(start);

        if (start <= 0) {
            return undefined;
        }

        const startedAt = Date.now();
        const timer = window.setInterval(() => {
            const next = start - Math.floor((Date.now() - startedAt) / 1000);

            if (next <= 0) {
                setRemaining(0);
                form.clearErrors('email');
                window.clearInterval(timer);
                return;
            }

            setRemaining(next);
        }, 1000);

        return () => window.clearInterval(timer);
    }, [loginLockSeconds]);

    function submit(event) {
        event.preventDefault();

        if (remaining > 0) {
            return;
        }

        form.post('/login', {
            onFinish: () => form.reset('password'),
        });
    }

    const emailError = remaining > 0 ? formatLock(remaining) : form.errors.email;

    return (
        <>
            <Head title="Entrar" />
            <div className="auth-page">
                <PageHeader
                    title="Entrar"
                    subtitle="Use o e-mail e a senha da sua conta."
                />
                {form.errors.session ? (
                    <p className="auth-alert" role="alert">{form.errors.session}</p>
                ) : null}
                <Card>
                    <form className="auth-form" noValidate onSubmit={submit}>
                        <FormField label="E-mail" error={emailError}>
                            <input
                                type="email"
                                name="email"
                                autoComplete="email"
                                autoFocus
                                value={form.data.email}
                                onChange={(event) => form.setData('email', event.target.value)}
                            />
                        </FormField>
                        <FormField label="Senha" error={form.errors.password}>
                            <input
                                type="password"
                                name="password"
                                autoComplete="current-password"
                                value={form.data.password}
                                onChange={(event) => form.setData('password', event.target.value)}
                            />
                        </FormField>
                        <label className="auth-remember">
                            <input
                                type="checkbox"
                                name="remember"
                                checked={form.data.remember}
                                onChange={(event) => form.setData('remember', event.target.checked)}
                            />
                            Manter conectado
                        </label>
                        <Button type="submit" disabled={form.processing || remaining > 0}>
                            {form.processing ? 'Entrando…' : 'Entrar'}
                        </Button>
                    </form>
                </Card>
                <p className="auth-switch">
                    <Link href="/register">Criar conta</Link>
                </p>
            </div>
        </>
    );
}

Login.layout = (page) => <GuestLayout>{page}</GuestLayout>;
