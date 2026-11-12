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
            glow.classList.add('cursor-glow--visible');
            glow.style.transform = `translate(${event.clientX}px, ${event.clientY}px) translate(-50%, -50%)`;
        }

        window.addEventListener('pointermove', onPointerMove);

        return () => window.removeEventListener('pointermove', onPointerMove);
    }, []);

    return <div ref={glowRef} className="cursor-glow" aria-hidden="true" />;
}
