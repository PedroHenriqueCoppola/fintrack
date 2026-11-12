import { Head } from '@inertiajs/react';
import EmptyState from '../../components/EmptyState/EmptyState';
import PageHeader from '../../components/PageHeader/PageHeader';
import AppLayout from '../../layouts/AppLayout/AppLayout';
import './Index.css';

export default function Index() {
    return (
        <>
            <Head title="Planejamento" />
            <div className="page">
                <PageHeader
                    title="Planejamento"
                    subtitle="Valor do mês, fixos, parcelados e remanejamentos."
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
