import { annotations, Calendar } from 'vanilla-calendar-pro';

function parse(value) {
    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, month - 1, day);
}

function firstWeekday(locale) {
    try {
        const info = new Intl.Locale(locale);
        const day = (info.getWeekInfo?.() ?? info.weekInfo)?.firstDay;

        if (day) {
            return day % 7;
        }
    } catch {
        // Unknown locale or no weekInfo support: Monday below.
    }

    return 1;
}

function escape(text) {
    const node = document.createElement('span');
    node.textContent = text;

    return node.innerHTML;
}

export function mount(root) {
    const host = root.querySelector('[data-date-range-calendar]');
    const result = root.querySelector('[data-date-range-result]');
    const summary = root.querySelector('[data-date-range-summary]');
    const clear = root.querySelector('[data-date-range-clear]');
    const fields = root.querySelector('[data-date-range-fields]');
    const start = root.querySelector('[data-date-range-start]');
    const end = root.querySelector('[data-date-range-end]');
    const required = start.required;
    const locale = root.dataset.locale || document.documentElement.lang || 'en';
    const format = new Intl.DateTimeFormat(locale, { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' });
    const marked = JSON.parse(root.dataset.marked || '{}');

    if (end.value === '' || end.value < start.value) {
        end.value = start.value;
    }

    const render = () => {
        if (start.value === '') {
            summary.textContent = summary.dataset.empty;
        } else if (start.value === end.value) {
            summary.textContent = format.format(parse(start.value));
        } else {
            summary.textContent = format.formatRange(parse(start.value), parse(end.value));
        }

        summary.classList.remove('text-error');

        if (clear) {
            clear.hidden = start.value === '';
        }
    };

    const write = (first, last) => {
        start.value = first;
        end.value = last;
        start.dispatchEvent(new Event('change', { bubbles: true }));
        end.dispatchEvent(new Event('change', { bubbles: true }));
        render();
    };

    const options = {
        locale,
        firstWeekday: firstWeekday(locale),
        selectionDatesMode: 'multiple-ranged',
        enableEdgeDatesOnly: true,
        selectedWeekends: [],
        onClickDate(self) {
            const dates = [...self.context.selectedDates].sort();
            write(dates[0] ?? '', dates.at(-1) ?? '');
        },
    };

    if (Object.keys(marked).length > 0) {
        options.extensions = [annotations];
        options.popups = Object.fromEntries(Object.entries(marked).map(([date, label]) => [
            date,
            { modifier: 'k-date-range-marked', html: escape(label) },
        ]));
    }

    if (root.dataset.min) {
        options.dateMin = root.dataset.min;
    }

    if (root.dataset.max) {
        options.dateMax = root.dataset.max;
    }

    if (start.value !== '') {
        const shown = parse(start.value);
        options.selectedDates = [start.value === end.value ? start.value : `${start.value}:${end.value}`];
        options.selectedMonth = shown.getMonth();
        options.selectedYear = shown.getFullYear();
    }

    const calendar = new Calendar(host, options);
    calendar.init();

    // Hidden required fields can't show the browser's own message, so the summary does instead.
    const onSubmit = (event) => {
        if (start.value === '') {
            event.preventDefault();
            event.stopImmediatePropagation();
            summary.classList.add('text-error');
            host.scrollIntoView({ block: 'nearest' });
        }
    };

    const onClear = () => {
        calendar.set({ selectedDates: [] }, { dates: true });
        write('', '');
    };

    const form = root.closest('form');

    if (required) {
        start.required = false;
        end.required = false;
        form?.addEventListener('submit', onSubmit, true);
    }

    clear?.addEventListener('click', onClear);
    fields.hidden = true;
    host.hidden = false;
    result.hidden = false;
    render();

    return () => {
        form?.removeEventListener('submit', onSubmit, true);
        clear?.removeEventListener('click', onClear);
        calendar.destroy();
        start.required = required;
        end.required = required;
        fields.hidden = false;
        host.hidden = true;
        result.hidden = true;
    };
}
