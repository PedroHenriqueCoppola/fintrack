import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import {
    CalendarRange,
    ChartColumn,
    FileBarChart,
    Landmark,
    LayoutDashboard,
    Moon,
    PanelLeftClose,
    PanelLeftOpen,
    Settings,
    Sun,
    Target,
} from 'lucide-react';
import Button from '../../components/Button/Button';
import CursorGlow from '../../components/CursorGlow/CursorGlow';
import { toggleTheme } from '../../lib/theme';
import './AppLayout.css';

const SIDEBAR_KEY = 'sidebar-collapsed';

const NAV_ITEMS = [
    { href: '/', label: 'Dashboard', icon: LayoutDashboard },
    { href: '/analysis', label: 'Análise', icon: ChartColumn },
    { href: '/planning', label: 'Planejamento', icon: CalendarRange },
    { href: '/goals', label: 'Metas', icon: Target },
    { href: '/net-worth', label: 'Patrimônio', icon: Landmark },
    { href: '/reports', label: 'Relatórios', icon: FileBarChart },
    { href: '/manage', label: 'Gerenciar', icon: Settings },
];

function readCollapsed() {
    try {
        return localStorage.getItem(SIDEBAR_KEY) === '1';
    } catch (error) {
        return false;
    }
}

function storeCollapsed(collapsed) {
    try {
        localStorage.setItem(SIDEBAR_KEY, collapsed ? '1' : '0');
    } catch (error) {
        // The choice stays for this visit when storage is blocked.
    }
}

export default function AppLayout({ children }) {
    const page = usePage();
    const path = page.url.split('?')[0];
    const user = page.props.auth?.user ?? null;
    const [collapsed, setCollapsed] = useState(readCollapsed);
    const [theme, setThemeState] = useState(() => (
        document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark'
    ));

    function onToggleSidebar() {
        const next = !collapsed;
        setCollapsed(next);
        storeCollapsed(next);
    }

    function onToggleTheme() {
        setThemeState(toggleTheme(theme));
    }

    return (
        <div className={collapsed ? 'app-shell app-shell--collapsed' : 'app-shell'}>
            <CursorGlow />
            <header className="app-header">
                <Link href="/" className="brand">
                    <span className="brand__dot" aria-hidden="true" />
                    Fintrack
                </Link>
                <div className="app-header__actions">
                    {user ? <span className="app-header__user">{user.name}</span> : null}
                    <Button
                        variant="quiet"
                        className="theme-toggle"
                        aria-label={theme === 'dark' ? 'Ativar tema claro' : 'Ativar tema escuro'}
                        onClick={onToggleTheme}
                    >
                        {theme === 'dark' ? <Sun aria-hidden="true" /> : <Moon aria-hidden="true" />}
                    </Button>
                    {user ? (
                        <Button variant="secondary" className="app-header__logout" onClick={() => router.post('/logout')}>
                            Sair
                        </Button>
                    ) : null}
                </div>
            </header>
            <aside className="app-sidebar">
                <nav className="app-nav" aria-label="Principal">
                    {NAV_ITEMS.map((item) => {
                        const Icon = item.icon;
                        const active = item.href === '/' ? path === '/' : path === item.href;

                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={active ? 'nav-link nav-link--active' : 'nav-link'}
                                aria-current={active ? 'page' : undefined}
                            >
                                <Icon aria-hidden="true" />
                                <span className="nav-link__label">{item.label}</span>
                            </Link>
                        );
                    })}
                </nav>
                <button
                    type="button"
                    className="sidebar-toggle"
                    aria-label={collapsed ? 'Expandir menu' : 'Recolher menu'}
                    onClick={onToggleSidebar}
                >
                    {collapsed ? <PanelLeftOpen aria-hidden="true" /> : <PanelLeftClose aria-hidden="true" />}
                    <span className="nav-link__label">{collapsed ? 'Expandir' : 'Recolher'}</span>
                </button>
            </aside>
            <main className="app-main">{children}</main>
        </div>
    );
}
