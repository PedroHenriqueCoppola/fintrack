import { Head } from '@inertiajs/react';
import EmptyState from '../../components/EmptyState/EmptyState';
import PageHeader from '../../components/PageHeader/PageHeader';
import AppLayout from '../../layouts/AppLayout/AppLayout';
import './Index.css';

export default function Index() {
    return (
        <>
            <Head title="Relatórios" />
            <div className="page">
                <PageHeader
                    title="Relatórios"
                    subtitle="Monte um relatório e baixe em PDF ou Excel."
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
