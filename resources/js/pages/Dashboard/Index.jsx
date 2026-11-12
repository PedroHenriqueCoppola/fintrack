import { Head } from '@inertiajs/react';
import EmptyState from '../../components/EmptyState/EmptyState';
import PageHeader from '../../components/PageHeader/PageHeader';
import AppLayout from '../../layouts/AppLayout/AppLayout';
import './Index.css';

export default function Index() {
    return (
        <>
            <Head title="Dashboard" />
            <div className="page">
                <PageHeader
                    title="Dashboard"
                    subtitle="Saldo, receitas, despesas e o que precisa de atenção."
                />
                <EmptyState
                    message="Esta página está em construção."
                    shortcutLabel="Ir para Análise"
                    shortcutHref="/analysis"
                />
            </div>
        </>
    );
}

Index.layout = (page) => <AppLayout>{page}</AppLayout>;
