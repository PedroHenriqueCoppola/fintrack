import { Head } from '@inertiajs/react';
import { Construction } from 'lucide-react';
import Card from '../../components/Card/Card';
import EmptyState from '../../components/EmptyState/EmptyState';
import PageHeader from '../../components/PageHeader/PageHeader';
import AppLayout from '../../layouts/AppLayout/AppLayout';

export default function Index() {
    return (
        <>
            <Head title="Planejamento" />
            <div className="page">
                <PageHeader
                    title="Planejamento"
                    subtitle="Defina quanto cada categoria recebe neste mês."
                />
                <Card className="card--fit">
                    <EmptyState icon={Construction} message="Em construção" />
                </Card>
            </div>
        </>
    );
}

Index.layout = (page) => <AppLayout>{page}</AppLayout>;
