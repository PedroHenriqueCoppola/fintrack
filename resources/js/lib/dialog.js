import { useEffect, useRef } from 'react';

const FOCUSABLE = 'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

export function useFocusTrap(open, onClose, options = {}) {
    const containerRef = useRef(null);
    const onCloseRef = useRef(onClose);
    const optionsRef = useRef(options);

    onCloseRef.current = onClose;
    optionsRef.current = options;

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const node = containerRef.current;
        const previous = document.activeElement;
        const capture = Boolean(optionsRef.current.capture);

        function focusable() {
            if (!node) {
                return [];
            }

            return [...node.querySelectorAll(FOCUSABLE)];
        }

        const preferred = optionsRef.current.initialFocus
            ? node?.querySelector(optionsRef.current.initialFocus)
            : null;
        const initial = focusable();
        (preferred || initial[0] || node)?.focus();

        function onKeyDown(event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                onCloseRef.current();
                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            const items = focusable();

            if (items.length === 0) {
                event.preventDefault();
                if (capture) {
                    event.stopPropagation();
                }
                return;
            }

            const first = items[0];
            const last = items[items.length - 1];
            const index = items.indexOf(document.activeElement);

            if (capture) {
                event.preventDefault();
                event.stopPropagation();
                const nextIndex = event.shiftKey
                    ? (index <= 0 ? items.length - 1 : index - 1)
                    : (index === -1 || index >= items.length - 1 ? 0 : index + 1);
                items[nextIndex].focus();
                return;
            }

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }

        document.addEventListener('keydown', onKeyDown, capture);

        return () => {
            document.removeEventListener('keydown', onKeyDown, capture);

            if (optionsRef.current.shouldRestore?.() === false) {
                return;
            }

            if (previous instanceof HTMLElement) {
                previous.focus();
            }
        };
    }, [open]);

    return containerRef;
}
