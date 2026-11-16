import { useEffect, useRef } from 'react';

const FOCUSABLE = 'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

export function useFocusTrap(open, onClose) {
    const containerRef = useRef(null);
    const onCloseRef = useRef(onClose);

    onCloseRef.current = onClose;

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const node = containerRef.current;
        const previous = document.activeElement;

        function focusable() {
            if (!node) {
                return [];
            }

            return [...node.querySelectorAll(FOCUSABLE)];
        }

        const initial = focusable();
        (initial[0] || node)?.focus();

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

            if (previous instanceof HTMLElement) {
                previous.focus();
            }
        };
    }, [open]);

    return containerRef;
}
