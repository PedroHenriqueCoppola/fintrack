import { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import Button from '../../components/Button/Button';
import PageHeader from '../../components/PageHeader/PageHeader';
import Tabs from '../../components/Tabs/Tabs';
import AppLayout from '../../layouts/AppLayout/AppLayout';
import CatalogList from './CatalogList';
import { BankForm, CardForm, CategoryForm, PaymentMethodForm } from './forms';
import './Index.css';

const TABS = [
    { id: 'categories', label: 'Categorias', create: 'Nova categoria' },
    { id: 'banks', label: 'Bancos', create: 'Novo banco' },
    { id: 'cards', label: 'Cartões', create: 'Novo cartão' },
    { id: 'payment-methods', label: 'Pagamentos', create: 'Nova forma de pagamento' },
];

function labelFor(options, value) {
    return options.find((option) => option.value === value)?.label ?? '';
}

function categoryDetail(item, options) {
    if (item.applies_to === 'income') {
        return labelFor(options.categoryTypes, item.applies_to) || 'Receita';
    }

    return labelFor(options.natures, item.nature) || 'Gasto';
}

function bankDetail(item, options) {
    const color = item.color ? labelFor(options.colors, item.color) : 'Sem cor';

    return item.usage ? `${color} · ${item.usage}` : color;
}

function cardDetail(item) {
    const due = item.due_day ? ` · vence dia ${item.due_day}` : '';

    return `${item.bank_name} · fecha dia ${item.closing_day}${due}`;
}

function ManageTools({ query, onQueryChange, showArchived, onToggleArchived }) {
    return (
        <div className="manage-tools">
            <label className="manage-search">
                <span className="visually-hidden">Buscar por nome</span>
                <Search aria-hidden="true" strokeWidth={1.75} />
                <input
                    type="search"
                    placeholder="Buscar por nome"
                    autoComplete="off"
                    value={query}
                    onChange={(event) => onQueryChange(event.target.value)}
                />
            </label>
            <Button
                variant="secondary"
                aria-pressed={showArchived}
                onClick={onToggleArchived}
            >
                Mostrar arquivados
            </Button>
        </div>
    );
}

export default function Index({ banks, categories, cards, paymentMethods, options }) {
    const [tab, setTab] = useState('categories');
    const [query, setQuery] = useState('');
    const [showArchived, setShowArchived] = useState(false);
    const [dialog, setDialog] = useState(null);
    const current = TABS.find((item) => item.id === tab) ?? TABS[0];

    function changeTab(id) {
        if (id === tab) {
            return;
        }

        setTab(id);
        setQuery('');
        setShowArchived(false);
    }

    return (
        <>
            <Head title="Gerenciar" />
            <div className="page">
                <PageHeader
                    title="Gerenciar"
                    subtitle="Deixe tudo do seu jeito: categorias, bancos, cartões e formas de pagamento."
                    actions={(
                        <Button onClick={() => setDialog({ kind: tab, item: null })}>
                            <Plus aria-hidden="true" strokeWidth={1.75} />
                            {current.create}
                        </Button>
                    )}
                />
                <Tabs
                    label="Cadastros"
                    value={tab}
                    onChange={changeTab}
                    aside={(
                        <ManageTools
                            query={query}
                            onQueryChange={setQuery}
                            showArchived={showArchived}
                            onToggleArchived={() => setShowArchived((value) => !value)}
                        />
                    )}
                    tabs={[
                        {
                            id: 'categories',
                            label: 'Categorias',
                            panel: (
                                <CatalogList
                                    items={categories}
                                    query={query}
                                    showArchived={showArchived}
                                    emptyAll="Nenhuma categoria cadastrada."
                                    emptyActive="Nenhuma categoria ativa."
                                    onEdit={(item) => setDialog({ kind: 'categories', item })}
                                    archivePath={(item) => `/manage/categories/${item.id}/archive`}
                                    restorePath={(item) => `/manage/categories/${item.id}/restore`}
                                    deletePath={(item) => `/manage/categories/${item.id}`}
                                    detail={(item) => categoryDetail(item, options)}
                                    groups={[
                                        {
                                            title: 'Despesas',
                                            match: (item) => item.applies_to !== 'income' && item.nature !== 'reserve',
                                        },
                                        {
                                            title: 'Reservas e receitas',
                                            match: (item) => item.applies_to === 'income' || item.nature === 'reserve',
                                        },
                                    ]}
                                />
                            ),
                        },
                        {
                            id: 'banks',
                            label: 'Bancos',
                            panel: (
                                <CatalogList
                                    items={banks}
                                    query={query}
                                    showArchived={showArchived}
                                    emptyAll="Nenhum banco cadastrado."
                                    emptyActive="Nenhum banco ativo."
                                    onEdit={(item) => setDialog({ kind: 'banks', item })}
                                    archivePath={(item) => `/manage/banks/${item.id}/archive`}
                                    restorePath={(item) => `/manage/banks/${item.id}/restore`}
                                    deletePath={(item) => `/manage/banks/${item.id}`}
                                    detail={(item) => bankDetail(item, options)}
                                    fallbackIcon="Landmark"
                                />
                            ),
                        },
                        {
                            id: 'cards',
                            label: 'Cartões',
                            panel: (
                                <CatalogList
                                    items={cards}
                                    query={query}
                                    showArchived={showArchived}
                                    emptyAll="Nenhum cartão cadastrado."
                                    emptyActive="Nenhum cartão ativo."
                                    onEdit={(item) => setDialog({ kind: 'cards', item })}
                                    archivePath={(item) => `/manage/cards/${item.id}/archive`}
                                    restorePath={(item) => `/manage/cards/${item.id}/restore`}
                                    deletePath={(item) => `/manage/cards/${item.id}`}
                                    detail={cardDetail}
                                    fallbackIcon="CreditCard"
                                />
                            ),
                        },
                        {
                            id: 'payment-methods',
                            label: 'Pagamentos',
                            panel: (
                                <CatalogList
                                    items={paymentMethods}
                                    query={query}
                                    showArchived={showArchived}
                                    emptyAll="Nenhuma forma de pagamento cadastrada."
                                    emptyActive="Nenhuma forma de pagamento ativa."
                                    onEdit={(item) => setDialog({ kind: 'payment-methods', item })}
                                    archivePath={(item) => `/manage/payment-methods/${item.id}/archive`}
                                    restorePath={(item) => `/manage/payment-methods/${item.id}/restore`}
                                    deletePath={(item) => `/manage/payment-methods/${item.id}`}
                                    detail={(item) => (item.requires_card ? 'Exige cartão' : 'Não exige cartão')}
                                    fallbackIcon={(item) => (item.requires_card ? 'CreditCard' : 'Wallet')}
                                />
                            ),
                        },
                    ]}
                />
                {dialog?.kind === 'banks' ? (
                    <BankForm
                        key={dialog.item?.id ?? 'new'}
                        item={dialog.item}
                        colors={options.colors}
                        onClose={() => setDialog(null)}
                    />
                ) : null}
                {dialog?.kind === 'categories' ? (
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
                {dialog?.kind === 'cards' ? (
                    <CardForm
                        key={dialog.item?.id ?? 'new'}
                        item={dialog.item}
                        banks={banks}
                        colors={options.colors}
                        onClose={() => setDialog(null)}
                    />
                ) : null}
                {dialog?.kind === 'payment-methods' ? (
                    <PaymentMethodForm
                        key={dialog.item?.id ?? 'new'}
                        item={dialog.item}
                        onClose={() => setDialog(null)}
                    />
                ) : null}
            </div>
        </>
    );
}

Index.layout = (page) => <AppLayout>{page}</AppLayout>;
