import './Card.css';

export default function Card({ title, className = '', children }) {
    const classes = ['card', className].filter(Boolean).join(' ');

    return (
        <section className={classes}>
            {title ? <h2 className="card__title">{title}</h2> : null}
            <div className="card__body">{children}</div>
        </section>
    );
}
