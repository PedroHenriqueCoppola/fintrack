import { Head } from '@inertiajs/react';
import EmptyState from '../../components/EmptyState/EmptyState';
import PageHeader from '../../components/PageHeader/PageHeader';
import AppLayout from '../../layouts/AppLayout/AppLayout';
import './Index.css';

export default function Index() {
    return (
        <>
            <Head title="Análise" />
            <div className="page">
                <PageHeader
                    title="Análise"
                    subtitle="Transações do período, agrupadas por dia."
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
