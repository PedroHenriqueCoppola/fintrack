import { useEffect, useState } from 'react';

const THEME_KEY = 'theme';
const THEME_EVENT = 'fintrack-theme';

export function getStoredTheme() {
    try {
        const stored = localStorage.getItem(THEME_KEY);

        if (stored === 'light' || stored === 'dark') {
            return stored;
        }
    } catch (error) {
        return 'dark';
    }

    return 'dark';
}

export function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
}

export function setTheme(theme) {
    try {
        localStorage.setItem(THEME_KEY, theme);
    } catch (error) {
        // The choice stays for this visit when storage is blocked.
    }

    applyTheme(theme);
    window.dispatchEvent(new CustomEvent(THEME_EVENT, { detail: theme }));
}

export function useTheme() {
    const [theme, setThemeState] = useState(() => (
        document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark'
    ));

    useEffect(() => {
        function onTheme(event) {
            setThemeState(event.detail === 'light' ? 'light' : 'dark');
        }

        window.addEventListener(THEME_EVENT, onTheme);

        return () => window.removeEventListener(THEME_EVENT, onTheme);
    }, []);

    return theme;
}

export function toggleTheme(current) {
    const next = current === 'light' ? 'dark' : 'light';
    setTheme(next);

    return next;
}
