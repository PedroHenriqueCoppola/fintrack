import './Button.css';

export default function Button({
    variant = 'primary',
    type = 'button',
    className = '',
    children,
    ...props
}) {
    const classes = ['button', `button--${variant}`, className].filter(Boolean).join(' ');

    return (
        <button type={type} className={classes} {...props}>
            {children}
        </button>
    );
}
