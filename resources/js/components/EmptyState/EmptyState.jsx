import { Link } from '@inertiajs/react';
import './EmptyState.css';

export default function EmptyState({ message, shortcutLabel, shortcutHref }) {
    return (
        <div className="empty-state">
            <p>{message}</p>
            {shortcutLabel && shortcutHref ? (
                <Link href={shortcutHref} className="empty-state__shortcut">
                    {shortcutLabel}
                </Link>
            ) : null}
        </div>
    );
}
