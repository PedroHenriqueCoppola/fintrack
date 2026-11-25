import { useEffect, useRef, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import {
    CalendarRange,
    ChartColumn,
    CreditCard,
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
import CursorGlow from '../../components/CursorGlow/CursorGlow';
import { toggleTheme } from '../../lib/theme';
import './AppLayout.css';

function initials(name) {
    const parts = String(name ?? '').trim().split(/\s+/).filter(Boolean);
    const letters = parts.slice(0, 2).map((part) => part.charAt(0)).join('');

    return letters.toLocaleUpperCase('pt-BR');
}

const SIDEBAR_KEY = 'sidebar-collapsed';

const NAV_ITEMS = [
    { href: '/', label: 'Dashboard', icon: LayoutDashboard },
    { href: '/analysis', label: 'Análise', icon: ChartColumn },
    { href: '/invoices', label: 'Faturas', icon: CreditCard },
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
    const profileRef = useRef(null);
    const [collapsed, setCollapsed] = useState(readCollapsed);
    const [menuOpen, setMenuOpen] = useState(false);
    const [theme, setThemeState] = useState(() => (
        document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark'
    ));

    useEffect(() => {
        if (!menuOpen) {
            return undefined;
        }

        function onPointerDown(event) {
            if (!profileRef.current?.contains(event.target)) {
                setMenuOpen(false);
            }
        }

        function onKeyDown(event) {
            if (event.key === 'Escape') {
                setMenuOpen(false);
            }
        }

        document.addEventListener('pointerdown', onPointerDown);
        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('pointerdown', onPointerDown);
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [menuOpen]);

    function onToggleSidebar() {
        const next = !collapsed;
        setCollapsed(next);
        storeCollapsed(next);
    }

    function onToggleTheme() {
        setThemeState(toggleTheme(theme));
    }

    function onLogout() {
        setMenuOpen(false);
        router.post('/logout');
    }

    return (
        <div className={collapsed ? 'app-shell app-shell--collapsed' : 'app-shell'}>
            <CursorGlow />
            <aside className="app-sidebar">
                <Link href="/" className="brand" title="Fintrack">
                    <span className="brand__mark" aria-hidden="true">F</span>
                    <span className="sidebar-label">Fintrack</span>
                </Link>
                <nav className="app-nav" aria-label="Principal">
                    {NAV_ITEMS.map((item) => {
                        const Icon = item.icon;
                        const active = item.href === '/'
                            ? path === '/'
                            : path === item.href || path.startsWith(`${item.href}/`);

                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={active ? 'nav-link nav-link--active' : 'nav-link'}
                                aria-current={active ? 'page' : undefined}
                                title={collapsed ? item.label : undefined}
                            >
                                <Icon aria-hidden="true" strokeWidth={1.75} />
                                <span className="sidebar-label">{item.label}</span>
                            </Link>
                        );
                    })}
                </nav>
                <button
                    type="button"
                    className="sidebar-toggle"
                    aria-label={collapsed ? 'Expandir menu' : 'Recolher menu'}
                    title={collapsed ? 'Expandir menu' : undefined}
                    onClick={onToggleSidebar}
                >
                    {collapsed
                        ? <PanelLeftOpen aria-hidden="true" strokeWidth={1.75} />
                        : <PanelLeftClose aria-hidden="true" strokeWidth={1.75} />}
                    <span className="sidebar-label">Recolher</span>
                </button>
            </aside>
            <main className="app-main">
                <div className="app-content">
                    <div className="app-top">
                        <button
                            type="button"
                            className="icon-button"
                            aria-label={theme === 'dark' ? 'Ativar tema claro' : 'Ativar tema escuro'}
                            onClick={onToggleTheme}
                        >
                            {theme === 'dark'
                                ? <Sun aria-hidden="true" strokeWidth={1.75} />
                                : <Moon aria-hidden="true" strokeWidth={1.75} />}
                        </button>
                        {user ? (
                            <div className="profile" ref={profileRef}>
                                <button
                                    type="button"
                                    className="profile__button"
                                    aria-label="Abrir perfil"
                                    aria-expanded={menuOpen}
                                    onClick={() => setMenuOpen((open) => !open)}
                                >
                                    <span className="profile__initials">{initials(user.name)}</span>
                                </button>
                                {menuOpen ? (
                                    <div className="profile__menu">
                                        <p className="profile__name">{user.name}</p>
                                        <Link href="/profile" className="profile__link" onClick={() => setMenuOpen(false)}>
                                            Meu perfil
                                        </Link>
                                        <button type="button" className="profile__logout" onClick={onLogout}>
                                            Sair
                                        </button>
                                    </div>
                                ) : null}
                            </div>
                        ) : null}
                    </div>
                    {children}
                </div>
            </main>
        </div>
    );
}
