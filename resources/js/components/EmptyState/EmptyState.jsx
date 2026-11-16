import { Link } from '@inertiajs/react';
import './EmptyState.css';

export default function EmptyState({
    message,
    shortcutLabel,
    shortcutHref,
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
        </div>
    );
}
