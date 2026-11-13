import { useState } from 'react';
import { Moon, Sun } from 'lucide-react';
import Button from '../../components/Button/Button';
import CursorGlow from '../../components/CursorGlow/CursorGlow';
import { toggleTheme } from '../../lib/theme';
import './GuestLayout.css';

export default function GuestLayout({ children }) {
    const [theme, setThemeState] = useState(() => (
        document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark'
    ));

    function onToggleTheme() {
        setThemeState(toggleTheme(theme));
    }

    return (
        <div className="guest-shell">
            <CursorGlow />
            <div className="guest-toolbar">
                <Button
                    variant="quiet"
                    className="guest-theme"
                    aria-label={theme === 'dark' ? 'Ativar tema claro' : 'Ativar tema escuro'}
                    onClick={onToggleTheme}
                >
                    {theme === 'dark' ? <Sun aria-hidden="true" /> : <Moon aria-hidden="true" />}
                </Button>
            </div>
            <main className="guest-main">
                <div className="guest-panel">
                    <p className="guest-brand">
                        <span className="guest-brand__dot" aria-hidden="true" />
                        Fintrack
                    </p>
                    {children}
                </div>
            </main>
        </div>
    );
}
