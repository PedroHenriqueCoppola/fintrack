import { useState } from 'react';
import { Head } from '@inertiajs/react';
import Button from '../../components/Button/Button';
import Card from '../../components/Card/Card';
import EmptyState from '../../components/EmptyState/EmptyState';
import FormField from '../../components/FormField/FormField';
import Modal from '../../components/Modal/Modal';
import Money from '../../components/Money/Money';
import PageHeader from '../../components/PageHeader/PageHeader';
import Tabs from '../../components/Tabs/Tabs';
import AppLayout from '../../layouts/AppLayout/AppLayout';
import { formatDate } from '../../lib/format';
import CatalogIcon from '../Manage/icons';
import '../Manage/Index.css';
import './Index.css';

export default function Index() {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Head title="Guia de estilos" />
            <div className="page styleguide">
                <PageHeader
                    title="Guia de estilos"
                    subtitle="Conferência dos componentes da interface."
                    actions={<Button variant="secondary">Ação</Button>}
                />

                <section className="styleguide__section">
                    <h2>Botões</h2>
                    <div className="styleguide__row">
                        <Button>Principal</Button>
                        <Button variant="secondary">Secundário</Button>
                        <Button variant="destructive">Destrutivo</Button>
                        <Button variant="quiet">Discreto</Button>
                    </div>
                </section>

                <Card title="Cartão">
                    Um bloco para agrupar saldo, listas e resumos.
                </Card>
                <Card title="Destaque" className="card--highlight">
                    O saldo principal de uma tela.
                </Card>
                <Card title="Interno" className="card--inset">
                    Um bloco dentro de outro card.
                </Card>
                <Card className="card--interactive">
                    Card clicável: no hover sobe e a borda fica mais forte.
                </Card>

                <section className="styleguide__section">
                    <h2>Card de cadastro</h2>
                    <div className="manage-list">
                        <Card className="manage-card card--interactive">
                            <div className="manage-card__top">
                                <span className="manage-marker" style={{ '--item-color': 'hsl(262 42% 58%)' }}>
                                    <CatalogIcon name="ShoppingCart" />
                                </span>
                                <div className="manage-card__copy">
                                    <div className="manage-card__title">
                                        <h2>Mercado</h2>
                                    </div>
                                    <p className="manage-card__detail">Gasto</p>
                                </div>
                            </div>
                            <div className="manage-card__actions">
                                <Button variant="secondary">Editar</Button>
                                <Button variant="secondary">Arquivar</Button>
                            </div>
                        </Card>
                    </div>
                </section>

                <section className="styleguide__section">
                    <h2>Modal</h2>
                    <div>
                        <Button onClick={() => setOpen(true)}>Abrir modal</Button>
                    </div>
                    <Modal
                        open={open}
                        title="Exemplo de modal"
                        onClose={() => setOpen(false)}
                        footer={(
                            <>
                                <Button variant="secondary" onClick={() => setOpen(false)}>Cancelar</Button>
                                <Button variant="primary" onClick={() => setOpen(false)}>Salvar</Button>
                            </>
                        )}
                    >
                        <p>Este modal fecha com Esc, com o clique fora e com o botão Fechar.</p>
                    </Modal>
                </section>

                <section className="styleguide__section">
                    <h2>Abas</h2>
                    <Tabs
                        label="Exemplo"
                        tabs={[
                            { id: 'one', label: 'Primeira', panel: <p>Painel da primeira aba.</p> },
                            { id: 'two', label: 'Segunda', panel: <p>Painel da segunda aba.</p> },
                        ]}
                    />
                </section>

                <section className="styleguide__section">
                    <h2>Campos</h2>
                    <FormField label="Descrição" hint="Como o lançamento aparece na lista.">
                        <input type="text" defaultValue="Mercado" />
                    </FormField>
                    <FormField label="Valor" error="Informe um valor maior que zero.">
                        <input type="text" defaultValue="0" />
                    </FormField>
                </section>

                <section className="styleguide__section">
                    <h2>Estado vazio</h2>
                    <EmptyState
                        message="Nenhum lançamento neste período."
                        shortcutLabel="Ir para o Dashboard"
                        shortcutHref="/"
                    />
                </section>

                <section className="styleguide__section">
                    <h2>Valores</h2>
                    <p>
                        Positivo: <Money value={1234.56} />
                    </p>
                    <p>
                        Negativo: <Money value={-470} />
                    </p>
                    <p>
                        Zero: <Money value={0} />
                    </p>
                    <p>
                        Data: {formatDate('2026-11-12')}
                    </p>
                </section>
            </div>
        </>
    );
}

Index.layout = (page) => <AppLayout>{page}</AppLayout>;
