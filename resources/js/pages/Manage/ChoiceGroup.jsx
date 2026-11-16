import { useId } from 'react';
import CatalogIcon from './icons';

export default function ChoiceGroup({
    legend,
    name,
    value,
    options,
    onChange,
    error,
    appearance = 'default',
}) {
    const errorId = useId();
    const classes = [
        'choice-group',
        appearance !== 'default' ? `choice-group--${appearance}` : '',
        error ? 'choice-group--error' : '',
    ].filter(Boolean).join(' ');

    return (
        <fieldset className={classes}>
            <legend>{legend}</legend>
            <div className="choice-group__options" aria-describedby={error ? errorId : undefined}>
                {options.map((option) => {
                    const selected = String(value ?? '') === String(option.value);

                    return (
                        <label
                            key={option.value || 'none'}
                            className={selected ? 'choice choice--selected' : 'choice'}
                            title={appearance === 'swatch' ? option.label : undefined}
                        >
                            <input
                                type="radio"
                                name={name}
                                value={option.value}
                                checked={selected}
                                onChange={() => onChange(option.value)}
                            />
                            {appearance === 'swatch' ? (
                                <span
                                    className={option.swatch ? 'choice__swatch' : 'choice__swatch choice__swatch--empty'}
                                    style={option.swatch ? { background: option.swatch } : undefined}
                                    aria-hidden="true"
                                />
                            ) : (
                                <>
                                    {option.icon ? <CatalogIcon name={option.icon} /> : null}
                                    <span>{option.label}</span>
                                </>
                            )}
                            {appearance === 'swatch' ? (
                                <span className="visually-hidden">{option.label}</span>
                            ) : null}
                        </label>
                    );
                })}
            </div>
            {error ? (
                <p className="choice-group__error" id={errorId} role="alert">
                    {error}
                </p>
            ) : null}
        </fieldset>
    );
}
