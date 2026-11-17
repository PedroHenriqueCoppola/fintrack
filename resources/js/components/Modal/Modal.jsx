import { useEffect, useId } from 'react';
import { X } from 'lucide-react';
import { useFocusTrap } from '../../lib/dialog';
import Button from '../Button/Button';
import './Modal.css';

export default function Modal({ open, title, footer, onClose, children, className = '', initialFocus }) {
    const dialogRef = useFocusTrap(open, onClose, { initialFocus });
    const titleId = useId();

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = previousOverflow;
        };
    }, [open]);

    if (!open) {
        return null;
    }

    function onBackdropClick(event) {
        if (event.target === event.currentTarget) {
            onClose();
        }
    }

    return (
        <div className="modal-backdrop" onClick={onBackdropClick}>
            <div
                className={['modal', className].filter(Boolean).join(' ')}
                role="dialog"
                aria-modal="true"
                aria-labelledby={titleId}
                ref={dialogRef}
                tabIndex={-1}
            >
                <div className="modal__header">
                    <h2 id={titleId}>{title}</h2>
                    <Button variant="quiet" className="modal__close" aria-label="Fechar" onClick={onClose}>
                        <X aria-hidden="true" strokeWidth={1.75} />
                    </Button>
                </div>
                <div className="modal__body">{children}</div>
                {footer ? <div className="modal__footer">{footer}</div> : null}
            </div>
        </div>
    );
}
