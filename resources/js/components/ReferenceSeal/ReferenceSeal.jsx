import './ReferenceSeal.css';

export default function ReferenceSeal({ label }) {
    if (!label) {
        return null;
    }

    return <span className="reference-seal">{`Ref. ${label}`}</span>;
}
