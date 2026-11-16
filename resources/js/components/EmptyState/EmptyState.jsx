import { Link } from '@inertiajs/react';
import './EmptyState.css';

export default function EmptyState({
    message,
    shortcutLabel,
    shortcutHref,
    onShortcut,
    icon: Icon = null,
}) {
    return (
        <div className="empty-state">
            {Icon ? (
                <span className="empty-state__icon" aria-hidden="true">
                    <Icon strokeWidth={1.75} />
                </span>
            ) : null}
            <p>{message}</p>
            {shortcutLabel && shortcutHref ? (
                <Link href={shortcutHref} className="empty-state__shortcut">
                    {shortcutLabel}
                </Link>
            ) : null}
            {shortcutLabel && onShortcut ? (
                <button type="button" className="empty-state__shortcut" onClick={onShortcut}>
                    {shortcutLabel}
                </button>
            ) : null}
        </div>
    );
}
