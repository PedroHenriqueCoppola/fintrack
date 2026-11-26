import { useEffect, useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Eye, EyeOff } from 'lucide-react';
import Button from '../../components/Button/Button';
import Card from '../../components/Card/Card';
import FormField from '../../components/FormField/FormField';
import TextInput from '../../components/TextInput/TextInput';
import PageHeader from '../../components/PageHeader/PageHeader';
import AppLayout from '../../layouts/AppLayout/AppLayout';
import { useFieldSetter } from '../../lib/fields';
import { setTheme, useTheme } from '../../lib/theme';
import './Index.css';

function initials(name) {
    const parts = String(name ?? '').trim().split(/\s+/).filter(Boolean);
    const letters = parts.slice(0, 2).map((part) => part.charAt(0)).join('');

    return letters.toLocaleUpperCase('pt-BR');
}

function CardNotice({ message }) {
    const [visible, setVisible] = useState(message || '');

    useEffect(() => {
        setVisible(message || '');

        if (!message) {
            return undefined;
        }

        const timer = window.setTimeout(() => setVisible(''), 4000);

        return () => window.clearTimeout(timer);
    }, [message]);

    return (
        <p className={visible ? 'profile-notice' : 'profile-notice profile-notice--empty'} role="status" aria-live="polite">
            {visible}
        </p>
    );
}

function PasswordField({
    id,
    value,
    onChange,
    autoComplete,
    'aria-invalid': ariaInvalid,
    'aria-describedby': ariaDescribedBy,
}) {
    const [visible, setVisible] = useState(false);

    return (
        <div className="password-field">
            <input
                id={id}
                type={visible ? 'text' : 'password'}
                autoComplete={autoComplete}
                value={value}
                aria-invalid={ariaInvalid}
                aria-describedby={ariaDescribedBy}
                onChange={(event) => onChange(event.target.value)}
            />
            <button
                type="button"
                className="password-field__toggle"
                aria-label={visible ? 'Ocultar senha' : 'Mostrar senha'}
                onClick={() => setVisible((current) => !current)}
            >
                {visible
                    ? <EyeOff aria-hidden="true" strokeWidth={1.75} />
                    : <Eye aria-hidden="true" strokeWidth={1.75} />}
            </button>
        </div>
    );
}

export default function Index({ member_since: memberSince = '' }) {
    const { auth, profile_saved: profileSaved, password_saved: passwordSaved } = usePage().props;
    const user = auth?.user ?? {};
    const theme = useTheme();
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
    const unchanged = profile.data.name === (user.name ?? '') && profile.data.email === (user.email ?? '');

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
                <PageHeader title="Meu perfil" />
                <Card>
                    <div className="profile-identity">
                        <span className="profile-identity__avatar" aria-hidden="true">{initials(user.name)}</span>
                        <div>
                            <h2 className="profile-identity__name">{user.name}</h2>
                            <p className="profile-identity__email">{user.email}</p>
                            {memberSince ? <p className="profile-identity__since">{memberSince}</p> : null}
                        </div>
                    </div>
                </Card>
                <Card>
                    <header className="profile-card__head">
                        <h2>Dados</h2>
                        <p>Nome e e-mail da sua conta.</p>
                    </header>
                    <CardNotice message={profileSaved} />
                    <form className="profile-form" noValidate onSubmit={saveProfile}>
                        <div className="profile-pair">
                            <FormField label="Nome" error={profile.errors.name}>
                                <TextInput
                                    maxLength={255}
                                    autoComplete="name"
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
                        </div>
                        <div className="profile-actions">
                            <Button type="submit" disabled={unchanged || profile.processing}>
                                {profile.processing ? 'Salvando…' : 'Salvar alterações'}
                            </Button>
                        </div>
                    </form>
                </Card>
                <Card>
                    <header className="profile-card__head">
                        <h2>Segurança</h2>
                        <p>Senha de acesso.</p>
                    </header>
                    <CardNotice message={passwordSaved} />
                    <form className="profile-form" noValidate onSubmit={savePassword}>
                        <FormField label="Senha atual" error={password.errors.current_password}>
                            <PasswordField
                                autoComplete="current-password"
                                value={password.data.current_password}
                                onChange={(value) => setPassword('current_password', value)}
                            />
                        </FormField>
                        <div className="profile-pair">
                            <FormField label="Nova senha" hint="Mínimo de 8 caracteres." error={password.errors.password}>
                                <PasswordField
                                    autoComplete="new-password"
                                    value={password.data.password}
                                    onChange={(value) => setPassword('password', value)}
                                />
                            </FormField>
                            <FormField label="Confirmar" error={password.errors.password_confirmation}>
                                <PasswordField
                                    autoComplete="new-password"
                                    value={password.data.password_confirmation}
                                    onChange={(value) => setPassword('password_confirmation', value)}
                                />
                            </FormField>
                        </div>
                        <p className="profile-warning">Ao trocar a senha, as outras sessões abertas são encerradas.</p>
                        <div className="profile-actions">
                            <Button type="submit" disabled={password.processing}>
                                {password.processing ? 'Salvando…' : 'Trocar senha'}
                            </Button>
                        </div>
                    </form>
                </Card>
                <Card>
                    <header className="profile-card__head">
                        <h2>Aparência</h2>
                        <p>O mesmo tema do botão no topo.</p>
                    </header>
                    <div className="profile-theme" role="group" aria-label="Aparência">
                        <button type="button" aria-pressed={theme === 'dark'} onClick={() => setTheme('dark')}>
                            Escuro
                        </button>
                        <button type="button" aria-pressed={theme === 'light'} onClick={() => setTheme('light')}>
                            Claro
                        </button>
                    </div>
                </Card>
            </div>
        </>
    );
}

Index.layout = (page) => <AppLayout>{page}</AppLayout>;
