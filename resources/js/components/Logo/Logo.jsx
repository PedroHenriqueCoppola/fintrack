import './Logo.css';

export default function Logo({ className = '' }) {
    const classes = ['logo', className].filter(Boolean).join(' ');

    return (
        <img
            className={classes}
            src="/brand/fintrack-mark.svg"
            alt=""
            width="32"
            height="32"
        />
    );
}
