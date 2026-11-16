import { useId, useRef, useState } from 'react';
import './Tabs.css';

export default function Tabs({ label, tabs, value, onChange, aside = null }) {
    const [uncontrolled, setUncontrolled] = useState(tabs[0]?.id ?? '');
    const selected = value ?? uncontrolled;
    const baseId = useId();
    const tabRefs = useRef([]);
    const current = tabs.find((tab) => tab.id === selected) ?? tabs[0];

    function select(id, index) {
        if (value === undefined) {
            setUncontrolled(id);
        }

        onChange?.(id);

        if (index !== undefined) {
            tabRefs.current[index]?.focus();
        }
    }

    function moveTo(index) {
        const next = tabs[index];

        if (!next) {
            return;
        }

        select(next.id, index);
    }

    function onKeyDown(event) {
        const index = tabs.findIndex((tab) => tab.id === current?.id);

        if (index < 0) {
            return;
        }

        if (event.key === 'ArrowRight') {
            event.preventDefault();
            moveTo((index + 1) % tabs.length);
        } else if (event.key === 'ArrowLeft') {
            event.preventDefault();
            moveTo((index - 1 + tabs.length) % tabs.length);
        } else if (event.key === 'Home') {
            event.preventDefault();
            moveTo(0);
        } else if (event.key === 'End') {
            event.preventDefault();
            moveTo(tabs.length - 1);
        }
    }

    if (!current) {
        return null;
    }

    const tabId = `${baseId}-tab-${current.id}`;
    const panelId = `${baseId}-panel-${current.id}`;

    return (
        <div className="tabs">
            <div className="tabs__bar">
                <div className="tabs__list" role="tablist" aria-label={label} onKeyDown={onKeyDown}>
                    {tabs.map((tab, index) => {
                        const active = tab.id === current.id;

                        return (
                            <button
                                key={tab.id}
                                ref={(element) => {
                                    tabRefs.current[index] = element;
                                }}
                                type="button"
                                className="tabs__tab"
                                role="tab"
                                id={active ? tabId : `${baseId}-tab-${tab.id}`}
                                aria-selected={active}
                                aria-controls={active ? panelId : undefined}
                                tabIndex={active ? 0 : -1}
                                onClick={() => select(tab.id)}
                            >
                                {tab.label}
                            </button>
                        );
                    })}
                </div>
                {aside ? <div className="tabs__aside">{aside}</div> : null}
            </div>
            <div
                className="tabs__panel"
                role="tabpanel"
                id={panelId}
                aria-labelledby={tabId}
            >
                {current.panel}
            </div>
        </div>
    );
}
