import { useEffect, useId, useRef } from 'react';
import { X } from 'lucide-react';
import Button from '../Button/Button';
import './Modal.css';

const FOCUSABLE = 'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

export default function Modal({ open, title, footer, onClose, children, className = '' }) {
    const dialogRef = useRef(null);
    const onCloseRef = useRef(onClose);
    const titleId = useId();

    onCloseRef.current = onClose;

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const dialog = dialogRef.current;
        const previous = document.activeElement;
        const previousOverflow = document.body.style.overflow;

        const initial = dialog ? [...dialog.querySelectorAll(FOCUSABLE)] : [];
        (initial[0] || dialog)?.focus();
        document.body.style.overflow = 'hidden';

        function focusable() {
            if (!dialog) {
                return [];
            }

            return [...dialog.querySelectorAll(FOCUSABLE)];
        }

        function onKeyDown(event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                onCloseRef.current();
                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            const items = focusable();

            if (items.length === 0) {
                event.preventDefault();
                return;
            }

            const first = items[0];
            const last = items[items.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }

        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('keydown', onKeyDown);
            document.body.style.overflow = previousOverflow;

            if (previous instanceof HTMLElement) {
                previous.focus();
            }
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
