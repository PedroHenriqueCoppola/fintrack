export function categoryOption(item, extra = {}) {
    return {
        value: String(item.id),
        label: item.name,
        icon: item.icon,
        color: item.color,
        mark: 'category',
        archived: Boolean(item.archived),
        ...extra,
    };
}

export function bankOption(item) {
    return {
        value: String(item.id),
        label: item.name,
        color: item.color,
        mark: 'dot',
        archived: Boolean(item.archived),
    };
}

export function cardOption(item, banks = []) {
    const bank = banks.find((entry) => String(entry.id) === String(item.bank_id));

    return {
        value: String(item.id),
        label: item.name,
        color: bank?.color || item.color,
        mark: 'dot',
        archived: Boolean(item.archived),
        hint: item.closing_day ? `Fecha dia ${item.closing_day}` : undefined,
    };
}

export function namedOption(item) {
    return {
        value: String(item.id),
        label: item.name,
        archived: Boolean(item.archived),
    };
}

export function choiceOptions(items) {
    return items.map((item) => ({
        value: String(item.value),
        label: item.label,
    }));
}
