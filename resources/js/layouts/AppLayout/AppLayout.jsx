import { useEffect, useRef, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import {
    CalendarRange,
    ChartColumn,
    CreditCard,
    FileBarChart,
    Landmark,
    LayoutDashboard,
    Menu,
    Moon,
    PanelLeftClose,
    PanelLeftOpen,
    Settings,
    Sun,
    Target,
} from 'lucide-react';
import CursorGlow from '../../components/CursorGlow/CursorGlow';
import Logo from '../../components/Logo/Logo';
import { useFocusTrap } from '../../lib/dialog';
import { toggleTheme, useTheme } from '../../lib/theme';
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

function useMaxWidth(pixels) {
    const query = `(max-width: ${pixels}px)`;
    const [matches, setMatches] = useState(() => window.matchMedia(query).matches);

    useEffect(() => {
        const media = window.matchMedia(query);
        const onChange = () => setMatches(media.matches);

        onChange();
        media.addEventListener('change', onChange);

        return () => media.removeEventListener('change', onChange);
    }, [query]);

    return matches;
}

export default function AppLayout({ children }) {
    const page = usePage();
    const path = page.url.split('?')[0];
    const user = page.props.auth?.user ?? null;
    const profileRef = useRef(null);
    const profileButtonRef = useRef(null);
    const menuItems = useRef([]);
    const focusMenu = useRef(false);
    const [collapsed, setCollapsed] = useState(readCollapsed);
    const [menuOpen, setMenuOpen] = useState(false);
    const [overlayOpen, setOverlayOpen] = useState(false);
    const theme = useTheme();
    const phone = useMaxWidth(767);
    const compact = useMaxWidth(1099);
    const tablet = compact && !phone;
    const iconOnly = !overlayOpen && (tablet || (!compact && collapsed));
    const sidebarRef = useFocusTrap(overlayOpen, () => setOverlayOpen(false));

    useEffect(() => {
        setOverlayOpen(false);
    }, [path]);

    useEffect(() => {
        if (!compact) {
            setOverlayOpen(false);
        }
    }, [compact]);

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
                profileButtonRef.current?.focus();
            }

            if (event.key === 'Tab') {
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

    useEffect(() => {
        if (menuOpen && focusMenu.current) {
            focusMenu.current = false;
            menuItems.current[0]?.focus();
        }
    }, [menuOpen]);

    function onToggleSidebar() {
        if (compact) {
            setOverlayOpen((open) => !open);
            return;
        }

        const next = !collapsed;
        setCollapsed(next);
        storeCollapsed(next);
    }

    function onToggleTheme() {
        toggleTheme(theme);
    }

    function onLogout() {
        setMenuOpen(false);
        router.post('/logout');
    }

    function openMenuFromKeyboard() {
        focusMenu.current = true;
        setMenuOpen(true);
    }

    function onProfileKeyDown(event) {
        if (event.key !== 'ArrowDown' && event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        event.preventDefault();

        if (!menuOpen) {
            openMenuFromKeyboard();
            return;
        }

        menuItems.current[0]?.focus();
    }

    function onMenuKeyDown(event) {
        const items = menuItems.current.filter(Boolean);
        const index = items.indexOf(document.activeElement);

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            items[Math.min(items.length - 1, index + 1)]?.focus();
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            items[Math.max(0, index - 1)]?.focus();
        } else if (event.key === 'Home') {
            event.preventDefault();
            items[0]?.focus();
        } else if (event.key === 'End') {
            event.preventDefault();
            items[items.length - 1]?.focus();
        } else if (event.key === 'Escape') {
            event.preventDefault();
            setMenuOpen(false);
            profileButtonRef.current?.focus();
        } else if (event.key === 'Tab') {
            setMenuOpen(false);
        }
    }

    function onSkip(event) {
        event.preventDefault();
        document.getElementById('conteudo')?.focus();
    }

    const shellClass = [
        'app-shell',
        iconOnly ? 'app-shell--collapsed' : '',
        overlayOpen ? 'app-shell--overlay' : '',
        phone ? 'app-shell--phone' : '',
    ].filter(Boolean).join(' ');
    const toggleLabel = overlayOpen
        ? 'Fechar menu'
        : (iconOnly ? 'Expandir menu' : 'Recolher menu');

    return (
        <div className={shellClass}>
            <a className="skip-link" href="#conteudo" onClick={onSkip}>
                Pular para o conteúdo
            </a>
            <CursorGlow />
            {overlayOpen ? (
                <button
                    type="button"
                    className="sidebar-backdrop"
                    aria-label="Fechar menu"
                    tabIndex={-1}
                    onClick={() => setOverlayOpen(false)}
                />
            ) : null}
            <aside className="app-sidebar" ref={sidebarRef}>
                <Link href="/" className="brand" title={iconOnly ? 'Fintrack' : undefined} onClick={() => setOverlayOpen(false)}>
                    <Logo />
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
                                title={iconOnly ? item.label : undefined}
                                onClick={() => setOverlayOpen(false)}
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
                    aria-label={toggleLabel}
                    title={iconOnly ? toggleLabel : undefined}
                    aria-expanded={compact ? overlayOpen : undefined}
                    onClick={onToggleSidebar}
                >
                    {iconOnly
                        ? <PanelLeftOpen aria-hidden="true" strokeWidth={1.75} />
                        : <PanelLeftClose aria-hidden="true" strokeWidth={1.75} />}
                    <span className="sidebar-label">{overlayOpen ? 'Fechar' : 'Recolher'}</span>
                </button>
            </aside>
            <main className="app-main" id="conteudo" tabIndex={-1}>
                <div className="app-content">
                    <div className="app-top">
                        <button
                            type="button"
                            className="icon-button app-menu"
                            aria-label="Menu"
                            aria-expanded={overlayOpen}
                            onClick={() => setOverlayOpen((open) => !open)}
                        >
                            <Menu aria-hidden="true" strokeWidth={1.75} />
                        </button>
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
                                    ref={profileButtonRef}
                                    type="button"
                                    className="profile__button"
                                    aria-label="Abrir perfil"
                                    aria-haspopup="menu"
                                    aria-expanded={menuOpen}
                                    aria-controls="profile-menu"
                                    onClick={() => setMenuOpen((open) => !open)}
                                    onKeyDown={onProfileKeyDown}
                                >
                                    <span className="profile__initials">{initials(user.name)}</span>
                                </button>
                                {menuOpen ? (
                                    <div className="profile__menu" id="profile-menu">
                                        <p className="profile__name" id="profile-menu-name">{user.name}</p>
                                        <div
                                            role="menu"
                                            aria-labelledby="profile-menu-name"
                                            onKeyDown={onMenuKeyDown}
                                        >
                                            <Link
                                                href="/profile"
                                                className="profile__link"
                                                role="menuitem"
                                                tabIndex={-1}
                                                ref={(element) => {
                                                    menuItems.current[0] = element;
                                                }}
                                                onClick={() => setMenuOpen(false)}
                                            >
                                                Meu perfil
                                            </Link>
                                            <button
                                                type="button"
                                                className="profile__logout"
                                                role="menuitem"
                                                tabIndex={-1}
                                                ref={(element) => {
                                                    menuItems.current[1] = element;
                                                }}
                                                onClick={onLogout}
                                            >
                                                Sair
                                            </button>
                                        </div>
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
