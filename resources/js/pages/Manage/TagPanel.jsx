import { useState } from 'react';
import { router } from '@inertiajs/react';
import Button from '../../components/Button/Button';
import Card from '../../components/Card/Card';
import ConfirmDialog from '../../components/ConfirmDialog/ConfirmDialog';
import EmptyState from '../../components/EmptyState/EmptyState';
import FormField from '../../components/FormField/FormField';
import Modal from '../../components/Modal/Modal';
import TextInput from '../../components/TextInput/TextInput';

function fold(value) {
    return String(value ?? '')
        .trim()
        .toLocaleLowerCase('pt-BR')
        .normalize('NFD')
        .replace(/\p{M}/gu, '');
}

function uses(count) {
    return count === 1 ? '1 transação' : `${count} transações`;
}

export default function TagPanel({ tags, query }) {
    const [editing, setEditing] = useState(null);
    const [name, setName] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [removing, setRemoving] = useState(null);
    const folded = fold(query);
    const visible = tags.filter((tag) => !folded || fold(tag.name).includes(folded));

    function save() {
        if (!editing) {
            return;
        }

        setBusy(true);
        setError('');
        router.put(`/manage/tags/${editing.id}`, { name }, {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
            onError: (errors) => setError(errors.name || 'Não foi possível renomear.'),
            onFinish: () => setBusy(false),
        });
    }

    return (
        <>
            {visible.length === 0 ? (
                <EmptyState message={folded ? 'Nenhum resultado para essa busca.' : 'Nenhuma tag criada. Elas aparecem quando você marca uma transação.'} />
            ) : (
                <div className="manage-list">
                    {visible.map((tag) => (
                        <Card key={tag.id} className="manage-card card--interactive">
                            <div className="manage-card__top">
                                <div className="manage-card__copy">
                                    <div className="manage-card__title">
                                        <h3>{tag.name}</h3>
                                    </div>
                                    <p className="manage-card__detail">{uses(tag.usage_count)}</p>
                                </div>
                            </div>
                            <div className="manage-card__actions">
                                <Button
                                    variant="secondary"
                                    onClick={() => {
                                        setError('');
                                        setName(tag.name);
                                        setEditing(tag);
                                    }}
                                >
                                    Renomear
                                </Button>
                                <Button variant="quiet" onClick={() => setRemoving(tag)}>Excluir</Button>
                            </div>
                        </Card>
                    ))}
                </div>
            )}
            <Modal
                open={editing !== null}
                title="Renomear tag"
                onClose={() => {
                    if (!busy) {
                        setEditing(null);
                    }
                }}
                footer={(
                    <>
                        <Button variant="secondary" disabled={busy} onClick={() => setEditing(null)}>Cancelar</Button>
                        <Button disabled={busy || name.trim() === ''} onClick={save}>{busy ? 'Salvando…' : 'Salvar'}</Button>
                    </>
                )}
            >
                <FormField label="Nome" error={error}>
                    <TextInput maxLength={30} value={name} onChange={setName} autoFocus />
                </FormField>
                <p className="manage-card__detail">Se já existir uma tag com esse nome, as duas viram uma só.</p>
            </Modal>
            <ConfirmDialog
                open={removing !== null}
                title={removing ? `Excluir a tag ${removing.name}?` : 'Excluir tag?'}
                confirmLabel="Excluir"
                busy={busy}
                onConfirm={() => {
                    if (!removing) {
                        return;
                    }

                    setBusy(true);
                    router.delete(`/manage/tags/${removing.id}`, {
                        preserveScroll: true,
                        onSuccess: () => setRemoving(null),
                        onFinish: () => setBusy(false),
                    });
                }}
                onCancel={() => {
                    if (!busy) {
                        setRemoving(null);
                    }
                }}
            >
                {removing ? `A tag ${removing.name} sai de ${uses(removing.usage_count)}.` : ''}
            </ConfirmDialog>
        </>
    );
}
