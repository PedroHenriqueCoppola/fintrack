import { router } from '@inertiajs/react';

export function visitPlanning(current, overrides = {}) {
    const filters = current.fixed?.filters ?? {};
    const params = {
        month: current.month,
        tab: current.tab,
        category_id: filters.category_id || undefined,
        min_amount: filters.min_amount || undefined,
        max_amount: filters.max_amount || undefined,
        sort: filters.sort && filters.sort !== 'name' ? filters.sort : undefined,
        ...overrides,
    };

    Object.keys(params).forEach((key) => {
        if (params[key] === undefined || params[key] === null || params[key] === '') {
            delete params[key];
        }
    });

    router.get('/planning', params, {
        preserveScroll: true,
        preserveState: false,
    });
}
