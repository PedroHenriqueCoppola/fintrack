import { useId } from 'react';
import CatalogIcon from './icons';

export default function ChoiceGroup({ legend, name, value, options, onChange, error }) {
    const errorId = useId();

    return (
        <fieldset className={error ? 'choice-group choice-group--error' : 'choice-group'}>
            <legend>{legend}</legend>
            <div className="choice-group__options" aria-describedby={error ? errorId : undefined}>
                {options.map((option) => {
                    const selected = String(value ?? '') === String(option.value);

                    return (
                        <label key={option.value || 'none'} className={selected ? 'choice choice--selected' : 'choice'}>
                            <input
                                type="radio"
                                name={name}
                                value={option.value}
                                checked={selected}
                                onChange={() => onChange(option.value)}
                            />
                            {option.swatch ? (
                                <span className="choice__swatch" style={{ background: option.swatch }} aria-hidden="true" />
                            ) : null}
                            {option.icon ? <CatalogIcon name={option.icon} /> : null}
                            <span>{option.label}</span>
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
