import { Head } from '@inertiajs/react';
import { Construction } from 'lucide-react';
import Card from '../../components/Card/Card';
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
                    subtitle="O que você tem guardado, banco a banco."
                />
                <Card className="card--fit">
                    <EmptyState icon={Construction} message="Em construção" />
                </Card>
            </div>
        </>
    );
}

Index.layout = (page) => <AppLayout>{page}</AppLayout>;
