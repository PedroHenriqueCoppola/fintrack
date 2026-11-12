const THEME_KEY = 'theme';

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
}

export function toggleTheme(current) {
    const next = current === 'light' ? 'dark' : 'light';
    setTheme(next);

    return next;
}
