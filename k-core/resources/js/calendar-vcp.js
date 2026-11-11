import { annotations, Calendar } from 'vanilla-calendar-pro';

// The month on screen when a field was swapped out, so an htmx re-render of the same field keeps it.
const shownMonths = new Map();
const REMEMBER_MS = 2000;

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

function baseOptions(root, locale) {
    const marked = JSON.parse(root.dataset.marked || '{}');
    const options = { locale, firstWeekday: firstWeekday(locale), selectedWeekends: [] };

    if (Object.keys(marked).length > 0) {
        options.extensions = [annotations];
        options.popups = Object.fromEntries(Object.entries(marked).map(([date, label]) => [
            date,
            { modifier: 'k-calendar-marked', html: escape(label) },
        ]));
    }

    if (root.dataset.min) {
        options.dateMin = root.dataset.min;
    }

    if (root.dataset.max) {
        options.dateMax = root.dataset.max;
    }

    return options;
}

function range(root, format, notify) {
    const result = root.querySelector('[data-calendar-result]');
    const summary = root.querySelector('[data-calendar-summary]');
    const clear = root.querySelector('[data-calendar-clear]');
    const fields = root.querySelector('[data-calendar-fields]');
    const start = root.querySelector('[data-calendar-start]');
    const end = root.querySelector('[data-calendar-end]');

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

        if (clear) {
            clear.hidden = start.value === '';
        }
    };

    const write = (first, last) => {
        start.value = first;
        end.value = last;
        render();
        notify();
    };

    return {
        summary,
        options: {
            selectionDatesMode: 'multiple-ranged',
            enableEdgeDatesOnly: true,
            selectedDates: start.value === '' ? [] : [start.value === end.value ? start.value : `${start.value}:${end.value}`],
            onClickDate(self) {
                const dates = [...self.context.selectedDates].sort();
                write(dates[0] ?? '', dates.at(-1) ?? '');
            },
        },
        first: () => start.value,
        isEmpty: () => start.value === '',
        start(calendar) {
            const onClear = () => {
                calendar.set({ selectedDates: [] }, { dates: true });
                write('', '');
            };

            clear?.addEventListener('click', onClear);
            start.required = false;
            end.required = false;
            fields.hidden = true;
            result.hidden = false;
            render();

            return () => {
                clear?.removeEventListener('click', onClear);
                start.required = root.hasAttribute('data-required');
                end.required = root.hasAttribute('data-required');
                fields.hidden = false;
                result.hidden = true;
            };
        },
    };
}

function multiple(root, format, notify) {
    const summary = root.querySelector('[data-calendar-summary]');
    const rows = root.querySelector('[data-calendar-rows]');
    const { name, dateLabel, timeLabel } = rows.dataset;
    const withTime = rows.hasAttribute('data-time');
    const times = new Map();

    rows.querySelectorAll('[data-calendar-row]').forEach((row) => {
        const date = row.querySelector('[data-calendar-date]').value;

        if (date !== '') {
            times.set(date, row.querySelector('[data-calendar-time]')?.value ?? '');
        }
    });

    const keepTimes = () => rows.querySelectorAll('[data-calendar-time]').forEach((input) => {
        times.set(input.closest('[data-calendar-row]').dataset.date, input.value);
    });

    const render = () => {
        rows.replaceChildren(...[...times.keys()].sort().map((date, i) => {
            const row = document.createElement('div');
            row.className = 'flex flex-wrap items-center gap-2';
            row.dataset.calendarRow = '';
            row.dataset.date = date;

            const label = document.createElement('span');
            label.className = 'min-w-0 flex-1 text-sm';
            label.textContent = format.format(parse(date));

            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = withTime ? `${name}[${i}][date]` : `${name}[]`;
            hidden.value = date;
            hidden.dataset.calendarDate = '';
            row.append(label, hidden);

            if (withTime) {
                const time = document.createElement('input');
                time.type = 'time';
                time.name = `${name}[${i}][time]`;
                time.value = times.get(date) ?? '';
                time.className = 'input w-32';
                time.setAttribute('aria-label', `${timeLabel}, ${label.textContent}`);
                time.dataset.calendarTime = '';
                row.append(time);
            } else {
                label.setAttribute('aria-label', `${dateLabel}, ${label.textContent}`);
            }

            return row;
        }));

        summary.hidden = times.size > 0;
        summary.textContent = summary.dataset.empty;
    };

    return {
        summary,
        options: {
            selectionDatesMode: 'multiple',
            enableDateToggle: true,
            selectedDates: [...times.keys()],
            onClickDate(self) {
                keepTimes();
                const selected = new Set(self.context.selectedDates);
                [...times.keys()].filter((date) => !selected.has(date)).forEach((date) => times.delete(date));
                selected.forEach((date) => times.has(date) || times.set(date, ''));
                render();
                notify();
            },
        },
        first: () => [...times.keys()].sort()[0] ?? '',
        isEmpty: () => times.size === 0,
        start() {
            render();

            return () => {};
        },
    };
}

export function mount(root) {
    const host = root.querySelector('[data-calendar-host]');
    const locale = root.dataset.locale || document.documentElement.lang || 'en';
    const format = new Intl.DateTimeFormat(locale, { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' });
    const notify = () => root.dispatchEvent(new Event('change', { bubbles: true }));
    const mode = (root.dataset.mode === 'multiple' ? multiple : range)(root, format, notify);
    const key = `${location.pathname}|${root.querySelector('input[name]')?.name}`;
    const options = { ...baseOptions(root, locale), ...mode.options };
    const remembered = shownMonths.get(key);
    const shown = remembered && Date.now() - remembered.at < REMEMBER_MS
        ? remembered.month
        : (mode.first() !== '' ? parse(mode.first()) : null);

    if (shown) {
        options.selectedMonth = shown.getMonth();
        options.selectedYear = shown.getFullYear();
    }

    shownMonths.delete(key);

    const calendar = new Calendar(host, options);
    calendar.init();
    host.hidden = false;
    const stop = mode.start(calendar);

    // Hidden or replaced required fields can't show the browser's own message, so the summary does.
    const onSubmit = (event) => {
        if (mode.isEmpty()) {
            event.preventDefault();
            event.stopImmediatePropagation();
            mode.summary.hidden = false;
            mode.summary.classList.add('text-error');
            host.scrollIntoView({ block: 'nearest' });
        }
    };

    const form = root.closest('form');

    if (root.hasAttribute('data-required')) {
        form?.addEventListener('submit', onSubmit, true);
    }

    const onChange = () => mode.summary.classList.remove('text-error');
    root.addEventListener('change', onChange);

    return () => {
        shownMonths.set(key, { at: Date.now(), month: new Date(calendar.context.selectedYear, calendar.context.selectedMonth, 1) });
        root.removeEventListener('change', onChange);
        form?.removeEventListener('submit', onSubmit, true);
        calendar.destroy();
        stop();
        host.hidden = true;
    };
}
