export function useFieldSetter(form) {
    return function setField(name, value, ...related) {
        if (typeof name === 'function') {
            form.setData(name);
            form.clearErrors(...[value, ...related].filter((key) => typeof key === 'string'));
            return;
        }

        form.setData(name, value);
        form.clearErrors(name, ...related);
    };
}
