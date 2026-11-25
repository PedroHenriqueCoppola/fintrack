import './IntegerInput.css';

export default function IntegerInput({
    value,
    onChange,
    min,
    max,
    onBlur,
    'aria-label': ariaLabel,
    id,
    disabled = false,
    'aria-invalid': ariaInvalid,
    'aria-describedby': ariaDescribedBy,
}) {
    function apply(raw) {
        const digits = String(raw ?? '').replace(/\D/g, '').replace(/^0+(?=\d)/, '');

        if (digits === '') {
            onChange('');
            return;
        }

        const number = Number(digits);

        if (max != null && number > max) {
            const jumped = String(raw).replace(/\D/g, '').length > String(value ?? '').length + 1;

            if (jumped) {
                onChange(String(max));
            }

            return;
        }

        if (min != null && number < min && String(min).length < digits.length) {
            return;
        }

        onChange(digits);
    }

    return (
        <input
            id={id}
            className="integer-input"
            type="text"
            inputMode="numeric"
            autoComplete="off"
            disabled={disabled}
            aria-invalid={ariaInvalid}
            aria-describedby={ariaDescribedBy}
            aria-label={ariaLabel}
            value={value ?? ''}
            onChange={(event) => apply(event.target.value)}
            onBlur={onBlur}
        />
    );
}
