import { useState } from 'react';
import { Head } from '@inertiajs/react';
import Money from '../../components/Money/Money';
import PageHeader from '../../components/PageHeader/PageHeader';
import Tabs from '../../components/Tabs/Tabs';
import AppLayout from '../../layouts/AppLayout/AppLayout';
import CatalogList from './CatalogList';
import { BankForm, CardForm, CategoryForm, PaymentMethodForm } from './forms';
import './Index.css';

function labelFor(options, value) {
    return options.find((option) => option.value === value)?.label ?? '';
}

function Detail({ label, children }) {
    return (
        <p className="manage-detail">
            <span>{label}</span>
            {children}
        </p>
    );
}

function BanksTab({ banks, options }) {
    const [dialog, setDialog] = useState(null);

    return (
        <>
            <CatalogList
                items={banks}
                newLabel="Novo banco"
                emptyAll="Nenhum banco cadastrado."
                emptyActive="Nenhum banco ativo."
                onCreate={() => setDialog({ item: null })}
                onEdit={(item) => setDialog({ item })}
                archivePath={(item) => `/manage/banks/${item.id}/archive`}
                restorePath={(item) => `/manage/banks/${item.id}/restore`}
                renderDetails={(item) => (
                    <Detail label="Cor">{item.color ? labelFor(options.colors, item.color) : 'Sem cor'}</Detail>
                )}
            />
            {dialog ? (
                <BankForm
                    key={dialog.item?.id ?? 'new'}
                    item={dialog.item}
                    colors={options.colors}
                    onClose={() => setDialog(null)}
                />
            ) : null}
        </>
    );
}

function CategoriesTab({ categories, options }) {
    const [dialog, setDialog] = useState(null);

    return (
        <>
            <CatalogList
                items={categories}
                newLabel="Nova categoria"
                emptyAll="Nenhuma categoria cadastrada."
                emptyActive="Nenhuma categoria ativa."
                onCreate={() => setDialog({ item: null })}
                onEdit={(item) => setDialog({ item })}
                archivePath={(item) => `/manage/categories/${item.id}/archive`}
                restorePath={(item) => `/manage/categories/${item.id}/restore`}
                renderDetails={(item) => (
                    <>
                        <Detail label="Tipo">{labelFor(options.categoryTypes, item.applies_to)}</Detail>
                        {item.nature ? <Detail label="Natureza">{labelFor(options.natures, item.nature)}</Detail> : null}
                        {item.applies_to === 'expense' ? (
                            <Detail label="Saldo inicial"><Money value={item.initial_balance} /></Detail>
                        ) : null}
                    </>
                )}
            />
            {dialog ? (
                <CategoryForm
                    key={dialog.item?.id ?? 'new'}
                    item={dialog.item}
                    colors={options.colors}
                    icons={options.icons}
                    categoryTypes={options.categoryTypes}
                    natures={options.natures}
                    onClose={() => setDialog(null)}
                />
            ) : null}
        </>
    );
}

function CardsTab({ cards, banks, options }) {
    const [dialog, setDialog] = useState(null);

    return (
        <>
            <CatalogList
                items={cards}
                newLabel="Novo cartão"
                emptyAll="Nenhum cartão cadastrado."
                emptyActive="Nenhum cartão ativo."
                onCreate={() => setDialog({ item: null })}
                onEdit={(item) => setDialog({ item })}
                archivePath={(item) => `/manage/cards/${item.id}/archive`}
                restorePath={(item) => `/manage/cards/${item.id}/restore`}
                renderDetails={(item) => (
                    <>
                        <Detail label="Banco">{item.bank_name}</Detail>
                        <Detail label="Fechamento">Dia {item.closing_day}</Detail>
                        <Detail label="Vencimento">{item.due_day ? `Dia ${item.due_day}` : 'Sem vencimento'}</Detail>
                    </>
                )}
            />
            {dialog ? (
                <CardForm
                    key={dialog.item?.id ?? 'new'}
                    item={dialog.item}
                    banks={banks}
                    colors={options.colors}
                    onClose={() => setDialog(null)}
                />
            ) : null}
        </>
    );
}

function PaymentMethodsTab({ paymentMethods }) {
    const [dialog, setDialog] = useState(null);

    return (
        <>
            <CatalogList
                items={paymentMethods}
                newLabel="Nova forma de pagamento"
                emptyAll="Nenhuma forma de pagamento cadastrada."
                emptyActive="Nenhuma forma de pagamento ativa."
                onCreate={() => setDialog({ item: null })}
                onEdit={(item) => setDialog({ item })}
                archivePath={(item) => `/manage/payment-methods/${item.id}/archive`}
                restorePath={(item) => `/manage/payment-methods/${item.id}/restore`}
                renderDetails={(item) => (
                    <Detail label="Cartão">{item.requires_card ? 'Exige cartão' : 'Não exige cartão'}</Detail>
                )}
            />
            {dialog ? (
                <PaymentMethodForm
                    key={dialog.item?.id ?? 'new'}
                    item={dialog.item}
                    onClose={() => setDialog(null)}
                />
            ) : null}
        </>
    );
}

export default function Index({ banks, categories, cards, paymentMethods, options }) {
    return (
        <>
            <Head title="Gerenciar" />
            <div className="page">
                <PageHeader
                    title="Gerenciar"
                    subtitle="Bancos, categorias, cartões e formas de pagamento."
                />
                <Tabs
                    label="Cadastros"
                    tabs={[
                        { id: 'banks', label: 'Bancos', panel: <BanksTab banks={banks} options={options} /> },
                        { id: 'categories', label: 'Categorias', panel: <CategoriesTab categories={categories} options={options} /> },
                        { id: 'cards', label: 'Cartões', panel: <CardsTab cards={cards} banks={banks} options={options} /> },
                        { id: 'payment-methods', label: 'Formas de pagamento', panel: <PaymentMethodsTab paymentMethods={paymentMethods} /> },
                    ]}
                />
            </div>
        </>
    );
}

Index.layout = (page) => <AppLayout>{page}</AppLayout>;
