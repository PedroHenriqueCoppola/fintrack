import Button from '../Button/Button';
import Modal from '../Modal/Modal';
import './ConfirmDialog.css';

export default function ConfirmDialog({
    open,
    title,
    message = null,
    children = null,
    confirmLabel,
    busy = false,
    busyLabel = 'Excluindo…',
    onConfirm,
    onCancel,
}) {
    function requestClose() {
        if (busy) {
            return;
        }

        onCancel();
    }

    return (
        <Modal
            open={open}
            className="modal--narrow"
            title={title}
            onClose={requestClose}
            initialFocus="[data-confirm-cancel]"
            footer={(
                <>
                    <Button
                        variant="secondary"
                        data-confirm-cancel
                        onClick={requestClose}
                        disabled={busy}
                    >
                        Cancelar
                    </Button>
                    <Button variant="destructive" onClick={onConfirm} disabled={busy}>
                        {busy ? busyLabel : confirmLabel}
                    </Button>
                </>
            )}
        >
            <p className="confirm-dialog__message">{children ?? message}</p>
        </Modal>
    );
}
