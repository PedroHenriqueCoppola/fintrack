import { Head } from '@inertiajs/react';
import EmptyState from '../../components/EmptyState/EmptyState';
import PageHeader from '../../components/PageHeader/PageHeader';
import AppLayout from '../../layouts/AppLayout/AppLayout';

export default function Index() {
    return (
        <>
            <Head title="Patrimônio" />
            <div className="page">
                <PageHeader
                    title="Patrimônio"
                    subtitle="Saldo de cada banco no fechamento do mês."
                />
                <EmptyState
                    message="Esta página está em construção."
                    shortcutLabel="Ir para o Dashboard"
                    shortcutHref="/"
                />
            </div>
        </>
    );
}

Index.layout = (page) => <AppLayout>{page}</AppLayout>;
