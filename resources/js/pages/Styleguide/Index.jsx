import { useState } from 'react';
import { Head } from '@inertiajs/react';
import Button from '../../components/Button/Button';
import Card from '../../components/Card/Card';
import EmptyState from '../../components/EmptyState/EmptyState';
import FormField from '../../components/FormField/FormField';
import Modal from '../../components/Modal/Modal';
import Money from '../../components/Money/Money';
import PageHeader from '../../components/PageHeader/PageHeader';
import AppLayout from '../../layouts/AppLayout/AppLayout';
import { formatDate } from '../../lib/format';
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
                            <Button variant="primary" onClick={() => setOpen(false)}>
                                Entendi
                            </Button>
                        )}
                    >
                        <p>Este modal fecha com Esc, com o clique fora e com o botão Fechar.</p>
                    </Modal>
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
                        Data: {formatDate('2026-11-12')}
                    </p>
                </section>
            </div>
        </>
    );
}

Index.layout = (page) => <AppLayout>{page}</AppLayout>;
