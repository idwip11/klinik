(() => {
    const parseDate = (value) => {
        const input = value.trim();
        let match = input.match(/^(\d{2})[-/](\d{2})[-/](\d{4})$/);
        if (match) {
            return `${match[3]}-${match[2]}-${match[1]}`;
        }
        match = input.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        return match ? input : '';
    };

    const displayDate = (isoDate) => {
        const match = isoDate.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        return match ? `${match[3]}-${match[2]}-${match[1]}` : '';
    };

    document.querySelectorAll('[data-date-paste]').forEach((entry) => {
        const textInput = entry.querySelector('[data-date-input]');
        const datePicker = entry.querySelector('[data-date-picker]');
        if (!textInput || !datePicker) {
            return;
        }

        const syncPicker = () => {
            const isoDate = parseDate(textInput.value);
            datePicker.value = isoDate;
            if (isoDate) {
                textInput.value = displayDate(isoDate);
            }
        };

        syncPicker();
        textInput.addEventListener('input', () => {
            const isoDate = parseDate(textInput.value);
            datePicker.value = isoDate;
        });
        textInput.addEventListener('blur', syncPicker);
        datePicker.addEventListener('change', () => {
            textInput.value = displayDate(datePicker.value);
        });
    });
})();
