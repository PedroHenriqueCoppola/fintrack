import { Head } from '@inertiajs/react';
import Button from '../../components/Button/Button';
import Logo from '../../components/Logo/Logo';
import './Index.css';

const COPY = {
    403: {
        title: 'Acesso negado',
        text: 'Esta página não está liberada para a sua conta.',
    },
    404: {
        title: 'Página não encontrada',
        text: 'O fantasminha procurou em todo canto e não achou essa página.',
    },
    419: {
        title: 'Sessão expirada',
        text: 'A página ficou parada por muito tempo. Recarregue e tente de novo.',
    },
    500: {
        title: 'Algo deu errado',
        text: 'Um fantasminha bagunçou algo por aqui. Tente de novo em instantes.',
    },
    503: {
        title: 'Em manutenção',
        text: 'Estamos arrumando a casa. Volte daqui a pouco.',
    },
};

export default function Index({ code = 500, authenticated = false }) {
    const status = COPY[code] ? Number(code) : 500;
    const copy = COPY[status];
    const signedIn = Boolean(authenticated);
    const home = signedIn ? '/' : '/login';

    function goBack() {
        window.history.back();
    }

    function reload() {
        window.location.reload();
    }

    return (
        <>
            <Head title={copy.title} />
            <div className="error-page">
                <a href={home} className="error-page__brand">
                    <Logo />
                    <span>Fintrack</span>
                </a>
                <div className="error-page__center">
                    <div className="error-page__stage">
                        <p className="error-page__code" aria-hidden="true">{status}</p>
                        <img className="error-page__ghost" src="/illustrations/ghost.svg" alt="" />
                    </div>
                    <h1>{copy.title}</h1>
                    <p className="error-page__text">{copy.text}</p>
                    <div className="error-page__actions">
                        <a className="button button--primary" href={home}>
                            {signedIn ? 'Voltar ao Dashboard' : 'Ir para o login'}
                        </a>
                        {status === 419 ? (
                            <Button variant="secondary" onClick={reload}>Recarregar</Button>
                        ) : (
                            <Button variant="secondary" onClick={goBack}>Voltar</Button>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
