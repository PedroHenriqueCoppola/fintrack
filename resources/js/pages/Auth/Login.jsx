import { Head, Link, useForm } from '@inertiajs/react';
import Button from '../../components/Button/Button';
import Card from '../../components/Card/Card';
import FormField from '../../components/FormField/FormField';
import PageHeader from '../../components/PageHeader/PageHeader';
import GuestLayout from '../../layouts/GuestLayout/GuestLayout';
import './auth.css';

export default function Login() {
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(event) {
        event.preventDefault();
        form.post('/login', {
            onFinish: () => form.reset('password'),
        });
    }

    return (
        <>
            <Head title="Entrar" />
            <div className="auth-page">
                <PageHeader
                    title="Entrar"
                    subtitle="Use o e-mail e a senha da sua conta."
                />
                <Card>
                    <form className="auth-form" noValidate onSubmit={submit}>
                        <FormField label="E-mail" error={form.errors.email}>
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
                        <Button type="submit" disabled={form.processing}>
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
