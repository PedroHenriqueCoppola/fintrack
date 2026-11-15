import { useId, useRef, useState } from 'react';
import './Tabs.css';

export default function Tabs({ label, tabs }) {
    const [selected, setSelected] = useState(tabs[0]?.id ?? '');
    const baseId = useId();
    const tabRefs = useRef([]);
    const current = tabs.find((tab) => tab.id === selected) ?? tabs[0];

    function moveTo(index) {
        const next = tabs[index];

        if (!next) {
            return;
        }

        setSelected(next.id);
        tabRefs.current[index]?.focus();
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
                            onClick={() => setSelected(tab.id)}
                        >
                            {tab.label}
                        </button>
                    );
                })}
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
