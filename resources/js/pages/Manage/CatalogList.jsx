import { useState } from 'react';
import { router } from '@inertiajs/react';
import Button from '../../components/Button/Button';
import Card from '../../components/Card/Card';
import ConfirmDialog from '../../components/ConfirmDialog/ConfirmDialog';
import EmptyState from '../../components/EmptyState/EmptyState';
import Modal from '../../components/Modal/Modal';
import CatalogIcon from './icons';

function fold(value) {
    return String(value ?? '')
        .trim()
        .toLocaleLowerCase('pt-BR')
        .normalize('NFD')
        .replace(/\p{M}/gu, '');
}

function markerIcon(item, fallbackIcon) {
    if (item.icon) {
        return item.icon;
    }

    if (typeof fallbackIcon === 'function') {
        return fallbackIcon(item);
    }

    return fallbackIcon || null;
}

function ItemMarker({ item, fallbackIcon }) {
    const icon = markerIcon(item, fallbackIcon);

    if (!icon && !item.color) {
        return null;
    }

    return (
        <span
            className={item.color ? 'manage-marker' : 'manage-marker manage-marker--plain'}
            style={item.color ? { '--item-color': item.color } : undefined}
            aria-hidden="true"
        >
            {icon ? <CatalogIcon name={icon} /> : null}
        </span>
    );
}

function firstError(errors) {
    return errors.delete || errors.bank || errors.name || errors.bank_id || 'Não foi possível concluir.';
}

export default function CatalogList({
    items,
    query,
    showArchived,
    emptyAll,
    emptyActive,
    onEdit,
    archivePath,
    restorePath,
    deletePath,
    detail,
    fallbackIcon = null,
    groups = null,
}) {
    const [pending, setPending] = useState(null);
    const [deleting, setDeleting] = useState(null);
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

    function renderCard(item) {
        return (
            <Card
                key={item.id}
                className="manage-card card--interactive"
            >
                <div className="manage-card__top">
                    <ItemMarker item={item} fallbackIcon={fallbackIcon} />
                    <div className="manage-card__copy">
                        <div className="manage-card__title">
                            <h3>{item.name}</h3>
                            {item.archived ? <span className="manage-badge">Arquivado</span> : null}
                        </div>
                        <p className="manage-card__detail">{detail(item)}</p>
                        {item.usage_summary ? (
                            <p className="manage-card__usage">{`Em uso: ${item.usage_summary}`}</p>
                        ) : null}
                    </div>
                </div>
                <div className="manage-card__actions">
                    <Button
                        variant="secondary"
                        aria-label={`Editar ${item.name}`}
                        onClick={() => onEdit(item)}
                    >
                        Editar
                    </Button>
                    {item.archived ? (
                        <Button
                            variant="secondary"
                            aria-label={`Desarquivar ${item.name}`}
                            disabled={busy}
                            onClick={() => run(restorePath(item), () => {})}
                        >
                            Desarquivar
                        </Button>
                    ) : (
                        <Button
                            variant="secondary"
                            aria-label={`Arquivar ${item.name}`}
                            onClick={() => {
                                setActionError('');
                                setPending(item);
                            }}
                        >
                            Arquivar
                        </Button>
                    )}
                    {item.can_delete ? (
                        <Button
                            variant="quiet"
                            aria-label={`Excluir ${item.name}`}
                            disabled={busy}
                            onClick={() => {
                                setActionError('');
                                setDeleting(item);
                            }}
                        >
                            Excluir
                        </Button>
                    ) : null}
                </div>
            </Card>
        );
    }

    function renderGroups() {
        const buckets = groups.map(() => []);

        visible.forEach((item) => {
            const index = groups.findIndex((group) => group.match(item));
            buckets[index === -1 ? 0 : index].push(item);
        });

        return buckets.map((groupItems, index) => {
            if (groupItems.length === 0) {
                return null;
            }

            return (
                <section key={groups[index].title} className="manage-section">
                    <h2 className="manage-section__title">{groups[index].title}</h2>
                    <div className="manage-list">
                        {groupItems.map(renderCard)}
                    </div>
                </section>
            );
        });
    }

    return (
        <>
            {actionError && !pending ? (
                <p className="manage-alert" role="alert">{actionError}</p>
            ) : null}

            {visible.length === 0 ? (
                <EmptyState message={emptyMessage} />
            ) : (
                groups ? renderGroups() : (
                    <div className="manage-list">
                        {visible.map(renderCard)}
                    </div>
                )
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
                        <Button
                            variant="secondary"
                            disabled={busy}
                            onClick={() => {
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
                <p className="manage-confirm">
                    Arquivar <strong>{pending?.name}</strong>? O item sai da lista. Nada é apagado, e dá para desarquivar depois.
                </p>
                {actionError ? <p className="manage-alert" role="alert">{actionError}</p> : null}
            </Modal>
            <ConfirmDialog
                open={deleting !== null}
                title={deleting ? `Excluir ${deleting.name}?` : 'Excluir?'}
                confirmLabel="Excluir"
                busy={busy}
                onConfirm={() => {
                    if (!deleting) {
                        return;
                    }

                    router.delete(deletePath(deleting), {
                        preserveScroll: true,
                        preserveState: true,
                        onStart: () => setBusy(true),
                        onFinish: () => setBusy(false),
                        onSuccess: () => setDeleting(null),
                        onError: (errors) => {
                            setDeleting(null);
                            setActionError(firstError(errors));
                        },
                    });
                }}
                onCancel={() => {
                    if (!busy) {
                        setDeleting(null);
                    }
                }}
            >
                Essa ação não pode ser desfeita.
            </ConfirmDialog>
        </>
    );
}
