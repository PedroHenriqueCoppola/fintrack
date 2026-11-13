import { useState } from 'react';
import { router } from '@inertiajs/react';
import Button from '../../components/Button/Button';
import Card from '../../components/Card/Card';
import EmptyState from '../../components/EmptyState/EmptyState';
import FormField from '../../components/FormField/FormField';
import Modal from '../../components/Modal/Modal';
import CatalogIcon from './icons';

function fold(value) {
    return String(value ?? '')
        .trim()
        .toLocaleLowerCase('pt-BR')
        .normalize('NFD')
        .replace(/\p{M}/gu, '');
}

function firstError(errors) {
    return errors.bank || errors.name || errors.bank_id || errors.pocket_id || 'Não foi possível concluir.';
}

export default function CatalogList({
    items,
    newLabel,
    emptyAll,
    emptyActive,
    onCreate,
    onEdit,
    archivePath,
    restorePath,
    renderDetails,
}) {
    const [query, setQuery] = useState('');
    const [showArchived, setShowArchived] = useState(false);
    const [pending, setPending] = useState(null);
    const [actionError, setActionError] = useState('');
    const [busy, setBusy] = useState(false);
    const foldedQuery = fold(query);
    const matched = items.filter((item) => !foldedQuery || fold(item.name).includes(foldedQuery));
    const active = matched.filter((item) => !item.archived);
    const archived = matched.filter((item) => item.archived);
    const visible = showArchived ? [...active, ...archived] : active;

    let emptyMessage = emptyAll;

    if (foldedQuery) {
        emptyMessage = 'Nenhum resultado para essa busca.';
    } else if (items.length > 0 && !showArchived && active.length === 0) {
        emptyMessage = emptyActive;
    }

    function run(url, onDone) {
        setActionError('');
        router.post(url, {}, {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
            onSuccess: () => {
                setActionError('');
                onDone();
            },
            onError: (errors) => setActionError(firstError(errors)),
        });
    }

    return (
        <>
            <div className="manage-toolbar">
                <FormField label="Buscar por nome">
                    <input
                        type="search"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                    />
                </FormField>
                <div className="manage-toolbar__actions">
                    <Button
                        variant={showArchived ? 'secondary' : 'quiet'}
                        aria-pressed={showArchived}
                        onClick={() => setShowArchived((current) => !current)}
                    >
                        Mostrar arquivados
                    </Button>
                    <Button onClick={onCreate}>{newLabel}</Button>
                </div>
            </div>

            {actionError && !pending ? (
                <p className="manage-alert" role="alert">{actionError}</p>
            ) : null}

            {visible.length === 0 ? (
                <EmptyState message={emptyMessage} />
            ) : (
                <div className="manage-list">
                    {visible.map((item) => (
                        <Card
                            key={item.id}
                            className={item.archived ? 'manage-card manage-card--archived' : 'manage-card'}
                        >
                            <div className="manage-card__title">
                                {'color' in item ? (
                                    <span
                                        className={item.color ? 'manage-swatch' : 'manage-swatch manage-swatch--empty'}
                                        style={item.color ? { background: item.color } : undefined}
                                        aria-hidden="true"
                                    />
                                ) : null}
                                {item.icon ? <CatalogIcon name={item.icon} /> : null}
                                <h2>{item.name}</h2>
                                {item.archived ? <span className="manage-badge">Arquivado</span> : null}
                            </div>
                            <div className="manage-card__details">{renderDetails(item)}</div>
                            {item.usage ? <p className="manage-card__usage">{item.usage}</p> : null}
                            <div className="manage-card__actions">
                                <Button variant="secondary" onClick={() => onEdit(item)}>Editar</Button>
                                {item.archived ? (
                                    <Button variant="quiet" disabled={busy} onClick={() => run(restorePath(item), () => {})}>
                                        Desarquivar
                                    </Button>
                                ) : (
                                    <Button variant="destructive" onClick={() => {
                                        setActionError('');
                                        setPending(item);
                                    }}
                                    >
                                        Arquivar
                                    </Button>
                                )}
                            </div>
                        </Card>
                    ))}
                </div>
            )}

            <Modal
                open={pending !== null}
                title="Arquivar"
                onClose={() => {
                    if (!busy) {
                        setPending(null);
                        setActionError('');
                    }
                }}
                footer={(
                    <>
                        <Button variant="quiet" disabled={busy} onClick={() => {
                            setPending(null);
                            setActionError('');
                        }}
                        >
                            Cancelar
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={busy || !pending}
                            onClick={() => {
                                if (pending) {
                                    run(archivePath(pending), () => setPending(null));
                                }
                            }}
                        >
                            {busy ? 'Arquivando…' : 'Arquivar'}
                        </Button>
                    </>
                )}
            >
                <p>
                    Arquivar <strong>{pending?.name}</strong>? O item sai da lista. Nada é apagado, e dá para desarquivar depois.
                </p>
                {actionError ? <p className="manage-alert" role="alert">{actionError}</p> : null}
            </Modal>
        </>
    );
}
