import { useForm, usePage } from '@inertiajs/react';
import { Head } from '@inertiajs/react';
import Button from '../../components/Button/Button';
import Card from '../../components/Card/Card';
import FormField from '../../components/FormField/FormField';
import PageHeader from '../../components/PageHeader/PageHeader';
import TextInput from '../../components/TextInput/TextInput';
import AppLayout from '../../layouts/AppLayout/AppLayout';
import { useFieldSetter } from '../../lib/fields';
import './Index.css';

export default function Index() {
    const { auth, status } = usePage().props;
    const user = auth?.user ?? {};
    const profile = useForm({
        name: user.name ?? '',
        email: user.email ?? '',
    });
    const password = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });
    const setProfile = useFieldSetter(profile);
    const setPassword = useFieldSetter(password);

    function saveProfile(event) {
        event.preventDefault();
        profile.put('/profile', { preserveScroll: true });
    }

    function savePassword(event) {
        event.preventDefault();
        password.put('/profile/password', {
            preserveScroll: true,
            onSuccess: () => password.reset(),
        });
    }

    return (
        <>
            <Head title="Meu perfil" />
            <div className="page profile-page">
                <PageHeader title="Meu perfil" subtitle="Nome, e-mail e senha da sua conta." />
                {status ? <p className="profile-status" role="status">{status}</p> : null}
                <div className="profile-grid">
                    <Card title="Dados">
                        <form className="profile-form" noValidate onSubmit={saveProfile}>
                            <FormField label="Nome" error={profile.errors.name}>
                                <TextInput
                                    maxLength={255}
                                    value={profile.data.name}
                                    onChange={(value) => setProfile('name', value)}
                                />
                            </FormField>
                            <FormField label="E-mail" error={profile.errors.email}>
                                <input
                                    type="email"
                                    autoComplete="email"
                                    value={profile.data.email}
                                    onChange={(event) => setProfile('email', event.target.value)}
                                />
                            </FormField>
                            <Button type="submit" disabled={profile.processing}>
                                {profile.processing ? 'Salvando…' : 'Salvar dados'}
                            </Button>
                        </form>
                    </Card>
                    <Card title="Senha">
                        <form className="profile-form" noValidate onSubmit={savePassword}>
                            <FormField label="Senha atual" error={password.errors.current_password}>
                                <input
                                    type="password"
                                    autoComplete="current-password"
                                    value={password.data.current_password}
                                    onChange={(event) => setPassword('current_password', event.target.value)}
                                />
                            </FormField>
                            <FormField label="Nova senha" error={password.errors.password}>
                                <input
                                    type="password"
                                    autoComplete="new-password"
                                    value={password.data.password}
                                    onChange={(event) => setPassword('password', event.target.value)}
                                />
                            </FormField>
                            <FormField label="Confirmar nova senha" error={password.errors.password_confirmation}>
                                <input
                                    type="password"
                                    autoComplete="new-password"
                                    value={password.data.password_confirmation}
                                    onChange={(event) => setPassword('password_confirmation', event.target.value)}
                                />
                            </FormField>
                            <Button type="submit" disabled={password.processing}>
                                {password.processing ? 'Salvando…' : 'Trocar senha'}
                            </Button>
                        </form>
                    </Card>
                </div>
            </div>
        </>
    );
}

Index.layout = (page) => <AppLayout>{page}</AppLayout>;
