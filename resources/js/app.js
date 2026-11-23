import AirDatepicker from 'air-datepicker';
import 'air-datepicker/air-datepicker.css';
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('ajaxList', () => ({
    loading: false, error: false, controller: null, timer: null, pendingUrl: null,
    schedule() {
        clearTimeout(this.timer);
        this.controller?.abort();
        this.timer = setTimeout(() => this.load(), 300);
    },
    restore() {
        const params = new URL(location.href).searchParams;
        for (const input of this.$refs.filters.elements) {
            if (input.name) input.value = params.get(input.name) ?? '';
        }
        this.load(location.href, false);
    },
    paginate(event) {
        const link = event.target.closest('a');
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const url = new URL(link.href);
        if (url.origin !== location.origin || url.pathname !== location.pathname || !url.searchParams.has('page')) return;
        event.preventDefault();
        this.load(url);
    },
    async load(target = null, updateHistory = true) {
        clearTimeout(this.timer);
        this.controller?.abort();
        const controller = new AbortController();
        this.controller = controller;
        const url = new URL(target ?? this.$refs.filters.action);
        if (!target) {
            url.search = new URLSearchParams(new FormData(this.$refs.filters)).toString();
            for (const [key, value] of [...url.searchParams]) if (!value) url.searchParams.delete(key);
        }
        this.pendingUrl = url.href;
        this.loading = true;
        this.error = false;
        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                signal: controller.signal, cache: 'no-store',
            });
            if (!response.ok || response.redirected) throw new Error('Unable to load results');
            const html = await response.text();
            if (controller.signal.aborted) return;
            this.$refs.results.innerHTML = html;
            if (updateHistory) history.pushState({}, '', url);
        } catch (error) {
            if (error.name !== 'AbortError') this.error = true;
        } finally {
            if (this.controller === controller) this.loading = false;
        }
    },
    destroy() { clearTimeout(this.timer); this.controller?.abort(); },
}));

Alpine.data('invoiceState', (state, url, token) => ({
    state, saving: false, error: '',
    async cycle() {
        if (this.saving) return;
        const states = ['In Progress', 'Ready To Invoice', 'Already Invoiced'];
        const next = states[(states.indexOf(this.state) + 1) % states.length];
        this.saving = true;
        this.error = '';
        try {
            const response = await fetch(url, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ state: next }),
            });
            if (!response.ok) throw new Error('Unable to save');
            this.state = (await response.json()).state;
        } catch {
            this.error = 'Could not save invoice status. Please try again.';
        } finally {
            this.saving = false;
        }
    },
}));

Alpine.start();

const leaveDatepickerLocale = {
    days: ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"],
    daysShort: ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"],
    daysMin: ["Su", "Mo", "Tu", "We", "Th", "Fr", "Sa"],
    months: ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"],
    monthsShort: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
    today: "Today",
    clear: "Clear",
    firstDay: 0
};

function leaveDateFromInputValue(value, fallbackDate = null) {
    const parts = value.match(/^(\d{2})-(\d{2})-(\d{4})$/);

    if (!parts) {
        return fallbackDate;
    }

    return new Date(Number(parts[3]), Number(parts[2]) - 1, Number(parts[1]));
}

function initialiseLeaveDatepicker(selector, options = {}) {
    const input = document.querySelector(selector);

    if (!input) {
        return;
    }

    const { allowPastDates = false, ...datepickerOverrides } = options;
    const fallbackSelectedDate = !('maxDate' in datepickerOverrides) ? new Date() : null;
    const selectedDate = leaveDateFromInputValue(input.value, fallbackSelectedDate);
    const datepickerOptions = {
        autoClose: true,
        dateFormat: 'dd-MM-yyyy',
        locale: leaveDatepickerLocale,
        onSelect() {
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        },
        ...datepickerOverrides,
    };

    if (selectedDate) {
        datepickerOptions.selectedDates = [selectedDate];
    }

    if (!allowPastDates && !('minDate' in datepickerOptions) && !('maxDate' in datepickerOptions)) {
        datepickerOptions.minDate = new Date();
    }

    new AirDatepicker(input, datepickerOptions);
}

const yesterday = new Date();
yesterday.setDate(yesterday.getDate() - 1);

initialiseLeaveDatepicker('#start_date');
initialiseLeaveDatepicker('#end_date');
initialiseLeaveDatepicker('#employment_start_date', { maxDate: yesterday });
initialiseLeaveDatepicker('#non_work_day_date', { allowPastDates: true });

document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('dashboard-calendar');

    if (!calendarEl) return;

    const dashboardVisibleRange = function (currentDate) {
        const start = new Date(currentDate);
        start.setHours(0, 0, 0, 0);
        start.setDate(start.getDate() - ((start.getDay() + 6) % 7));

        const end = new Date(start);
        end.setDate(start.getDate() + 28);

        return { start, end };
    };

    const calendar = new Calendar(calendarEl, {
        plugins: [dayGridPlugin],

        initialView: 'dayGrid',
        visibleRange: dashboardVisibleRange,
        height: 'auto',
        headerToolbar: false,
        firstDay: 1,

        events: '/leave-requests/calendar-events',

        eventDisplay: 'block',

        editable: false,
        selectable: false,
        navLinks: false,
    });

    calendar.render();
});

document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('calendar');

    if (!calendarEl) return;

    const calendar = new Calendar(calendarEl, {
        plugins: [dayGridPlugin],
        height: 'auto',
        firstDay: 1,

        events: '/leave-requests/calendar-events',

        eventDisplay: 'block',

    });

    calendar.render();
});
