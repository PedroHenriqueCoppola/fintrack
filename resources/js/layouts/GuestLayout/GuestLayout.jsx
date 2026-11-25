import { Moon, Sun } from 'lucide-react';
import CursorGlow from '../../components/CursorGlow/CursorGlow';
import Logo from '../../components/Logo/Logo';
import { toggleTheme, useTheme } from '../../lib/theme';
import './GuestLayout.css';

export default function GuestLayout({ children }) {
    const theme = useTheme();

    function onToggleTheme() {
        toggleTheme(theme);
    }

    return (
        <div className="guest-shell">
            <CursorGlow />
            <button
                type="button"
                className="guest-theme"
                aria-label={theme === 'dark' ? 'Ativar tema claro' : 'Ativar tema escuro'}
                onClick={onToggleTheme}
            >
                {theme === 'dark' ? <Sun aria-hidden="true" strokeWidth={1.75} /> : <Moon aria-hidden="true" strokeWidth={1.75} />}
            </button>
            <main className="guest-main">
                <div className="guest-panel">
                    <p className="guest-brand">
                        <Logo className="logo--guest" />
                        Fintrack
                    </p>
                    {children}
                </div>
            </main>
        </div>
    );
}
