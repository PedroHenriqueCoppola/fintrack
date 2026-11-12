import { cloneElement, useId } from 'react';
import './FormField.css';

export default function FormField({ label, hint, error, children }) {
    const generatedId = useId();
    const fieldId = children.props.id || generatedId;
    const hintId = hint ? `${fieldId}-hint` : undefined;
    const errorId = error ? `${fieldId}-error` : undefined;
    const describedBy = [hintId, errorId].filter(Boolean).join(' ') || undefined;

    const field = cloneElement(children, {
        id: fieldId,
        'aria-invalid': error ? 'true' : undefined,
        'aria-describedby': describedBy,
    });

    return (
        <div className={error ? 'form-field form-field--error' : 'form-field'}>
            <label className="form-field__label" htmlFor={fieldId}>
                {label}
            </label>
            <div className="form-field__control">{field}</div>
            {hint ? (
                <p className="form-field__hint" id={hintId}>
                    {hint}
                </p>
            ) : null}
            {error ? (
                <p className="form-field__error" id={errorId} role="alert">
                    {error}
                </p>
            ) : null}
        </div>
    );
}
