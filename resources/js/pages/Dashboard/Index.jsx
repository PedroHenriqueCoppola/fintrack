import { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { Construction } from 'lucide-react';
import Card from '../../components/Card/Card';
import EmptyState from '../../components/EmptyState/EmptyState';
import PageHeader from '../../components/PageHeader/PageHeader';
import TransactionModal, { NewTransactionButton } from '../../components/TransactionModal/TransactionModal';
import AppLayout from '../../layouts/AppLayout/AppLayout';

function greeting(date) {
    const hour = date.getHours();

    if (hour >= 5 && hour < 12) {
        return 'Bom dia';
    }

    if (hour >= 12 && hour < 18) {
        return 'Boa tarde';
    }

    return 'Boa noite';
}

function firstName(name) {
    const [part] = String(name ?? '').trim().split(/\s+/);

    return part ?? '';
}

export default function Index({ options }) {
    const { auth } = usePage().props;
    const name = firstName(auth?.user?.name);
    const hello = greeting(new Date());
    const title = name ? `${hello}, ${name}` : hello;
    const [creating, setCreating] = useState(false);

    return (
        <>
            <Head title={title} />
            <div className="page">
                <PageHeader
                    title={title}
                    subtitle="Vamos começar? Cadastre algumas coisas e o resumo do mês aparece aqui."
                    actions={<NewTransactionButton onClick={() => setCreating(true)} />}
                />
                <Card className="card--fit">
                    <EmptyState icon={Construction} message="Em construção" />
                </Card>
            </div>
            {creating ? (
                <TransactionModal
                    mode="create"
                    options={options}
                    onClose={() => setCreating(false)}
                />
            ) : null}
        </>
    );
}

Index.layout = (page) => <AppLayout>{page}</AppLayout>;
