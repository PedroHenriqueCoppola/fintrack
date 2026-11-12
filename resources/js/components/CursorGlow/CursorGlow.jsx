import { useEffect, useRef } from 'react';
import './CursorGlow.css';

export default function CursorGlow() {
    const glowRef = useRef(null);

    useEffect(() => {
        const glow = glowRef.current;

        if (!glow) {
            return undefined;
        }

        function onPointerMove(event) {
            glow.style.left = `${event.clientX}px`;
            glow.style.top = `${event.clientY}px`;
        }

        window.addEventListener('pointermove', onPointerMove);

        return () => window.removeEventListener('pointermove', onPointerMove);
    }, []);

    return <div ref={glowRef} className="cursor-glow" aria-hidden="true" />;
}
