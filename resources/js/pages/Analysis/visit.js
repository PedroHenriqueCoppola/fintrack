import { router } from '@inertiajs/react';

export function visitAnalysis(next, { replace = false, loadMore = false, onStart, onFinish } = {}) {
    const params = {};
    const values = {
        period: next.period && next.period !== 'current_month' ? next.period : undefined,
        from: next.period === 'custom' ? next.from : undefined,
        to: next.period === 'custom' ? next.to : undefined,
        type: next.type || undefined,
        status: next.status || undefined,
        category_id: next.category_id || undefined,
        bank_id: next.bank_id || undefined,
        card_id: next.card_id || undefined,
        payment_method_id: next.payment_method_id || undefined,
        min_amount: next.min_amount || undefined,
        max_amount: next.max_amount || undefined,
        q: next.q || undefined,
        sort: next.sort && next.sort !== 'date_desc' ? next.sort : undefined,
        page: loadMore ? next.page : undefined,
    };

    Object.entries(values).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            params[key] = value;
        }
    });

    router.get('/analysis', params, {
        preserveState: true,
        preserveScroll: true,
        replace,
        only: loadMore
            ? ['transactions']
            : ['transactions', 'summary', 'period', 'filters'],
        reset: loadMore ? [] : ['transactions'],
        onStart,
        onFinish,
    });
}
