import './Card.css';

export default function Card({ title, children }) {
    return (
        <section className="card">
            {title ? <h2 className="card__title">{title}</h2> : null}
            <div className="card__body">{children}</div>
        </section>
    );
}
