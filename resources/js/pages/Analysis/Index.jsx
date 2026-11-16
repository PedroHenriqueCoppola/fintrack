import { Head } from '@inertiajs/react';
import { Construction } from 'lucide-react';
import Card from '../../components/Card/Card';
import EmptyState from '../../components/EmptyState/EmptyState';
import PageHeader, { DisabledTransactionButton } from '../../components/PageHeader/PageHeader';
import AppLayout from '../../layouts/AppLayout/AppLayout';

export default function Index() {
    return (
        <>
            <Head title="Análise" />
            <div className="page">
                <PageHeader
                    title="Análise"
                    subtitle="Todas as suas transações, agrupadas por dia."
                    actions={<DisabledTransactionButton />}
                />
                <Card className="card--fit">
                    <EmptyState icon={Construction} message="Em construção" />
                </Card>
            </div>
        </>
    );
}

Index.layout = (page) => <AppLayout>{page}</AppLayout>;
