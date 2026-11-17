import { Head, useForm } from '@inertiajs/react';
import Button from '../../components/Button/Button';
import FormField from '../../components/FormField/FormField';
import GuestLayout from '../../layouts/GuestLayout/GuestLayout';
import { useFieldSetter } from '../../lib/fields';
import AuthFrame from './AuthFrame';
import './auth.css';

export default function Register() {
    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });
    const setField = useFieldSetter(form);

    function submit(event) {
        event.preventDefault();
        form.post('/register', {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    }

    return (
        <>
            <Head title="Criar conta" />
            <AuthFrame
                mode="register"
                title="Crie sua conta"
                subtitle="Nome, e-mail e senha. Você entra assim que o cadastro termina."
                alert={form.errors.session ? (
                    <p className="auth-alert" role="alert">{form.errors.session}</p>
                ) : null}
            >
                <form className="auth-form" noValidate onSubmit={submit}>
                    <FormField label="Nome" error={form.errors.name}>
                        <input
                            type="text"
                            name="name"
                            autoComplete="name"
                            autoFocus
                            value={form.data.name}
                            onChange={(event) => setField('name', event.target.value)}
                        />
                    </FormField>
                    <FormField label="E-mail" error={form.errors.email}>
                        <input
                            type="email"
                            name="email"
                            autoComplete="email"
                            value={form.data.email}
                            onChange={(event) => setField('email', event.target.value)}
                        />
                    </FormField>
                    <FormField label="Senha" error={form.errors.password}>
                        <input
                            type="password"
                            name="password"
                            autoComplete="new-password"
                            value={form.data.password}
                            onChange={(event) => setField('password', event.target.value)}
                        />
                    </FormField>
                    <FormField label="Confirmar senha" error={form.errors.password_confirmation}>
                        <input
                            type="password"
                            name="password_confirmation"
                            autoComplete="new-password"
                            value={form.data.password_confirmation}
                            onChange={(event) => setField('password_confirmation', event.target.value)}
                        />
                    </FormField>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Criando conta…' : 'Criar conta'}
                    </Button>
                </form>
            </AuthFrame>
        </>
    );
}

Register.layout = (page) => <GuestLayout>{page}</GuestLayout>;
