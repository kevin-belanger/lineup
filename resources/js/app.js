import './bootstrap';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

const liveDurationSelector = '[data-live-duration][data-started-at]';
const classroomOpeningStatusSelector = '[data-classroom-opening-status]';
const classroomOpeningStatusRefreshMs = 5000;
const weekdayByName = {
    Mon: 1,
    Tue: 2,
    Wed: 3,
    Thu: 4,
    Fri: 5,
    Sat: 6,
    Sun: 7,
};
const dateTimeFormatters = new Map();
const dateKeyFormatters = new Map();
const timeFormatters = new Map();
const dateTimeLabelFormatters = new Map();
const statisticsCharts = new WeakMap();

function formatterForTimezone(timezone) {
    if (!dateTimeFormatters.has(timezone)) {
        dateTimeFormatters.set(timezone, new Intl.DateTimeFormat('en-US', {
            timeZone: timezone,
            weekday: 'short',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        }));
    }

    return dateTimeFormatters.get(timezone);
}

function localMinuteParts(date, timezone) {
    const parts = Object.fromEntries(formatterForTimezone(timezone)
        .formatToParts(date)
        .map((part) => [part.type, part.value]));

    return {
        day: weekdayByName[parts.weekday] ?? null,
        minute: (Number(parts.hour) * 60) + Number(parts.minute),
    };
}

function localDateKey(date, timezone) {
    if (!dateKeyFormatters.has(timezone)) {
        dateKeyFormatters.set(timezone, new Intl.DateTimeFormat('en-CA', {
            timeZone: timezone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        }));
    }

    const parts = Object.fromEntries(dateKeyFormatters.get(timezone)
        .formatToParts(date)
        .map((part) => [part.type, part.value]));

    return `${parts.year}-${parts.month}-${parts.day}`;
}

function openingScheduleForElement(element) {
    if (element._lineupOpeningSchedule !== undefined) {
        return element._lineupOpeningSchedule;
    }

    try {
        const schedule = JSON.parse(element.dataset.openingHours || '{}');
        element._lineupOpeningSchedule = Array.isArray(schedule.periods) && schedule.periods.length > 0
            ? schedule
            : null;
    } catch {
        element._lineupOpeningSchedule = null;
    }

    return element._lineupOpeningSchedule;
}

function isOpenMinute(date, schedule) {
    const local = localMinuteParts(date, schedule.timezone || 'UTC');

    if (local.day === null || Number.isNaN(local.minute)) {
        return false;
    }

    return schedule.periods.some((period) => {
        const opensAt = String(period.opens_at || '').split(':').map(Number);
        const closesAt = String(period.closes_at || '').split(':').map(Number);

        if (opensAt.length < 2 || closesAt.length < 2 || opensAt.some(Number.isNaN) || closesAt.some(Number.isNaN)) {
            return false;
        }

        const opensAtMinute = (opensAt[0] * 60) + opensAt[1];
        const closesAtMinute = (closesAt[0] * 60) + closesAt[1];
        const days = Array.isArray(period.days) ? period.days.map(Number) : [];

        return days.includes(local.day)
            && local.minute >= opensAtMinute
            && local.minute < closesAtMinute;
    });
}

function nextOpeningAt(date, schedule) {
    const startMs = date.getTime();

    for (let offsetMinutes = 1; offsetMinutes <= 10080; offsetMinutes++) {
        const candidate = new Date(startMs + (offsetMinutes * 60000));

        if (isOpenMinute(candidate, schedule)) {
            return candidate;
        }
    }

    return null;
}

function closedUntilLabel(date, schedule) {
    const opensAt = nextOpeningAt(date, schedule);

    if (opensAt === null) {
        return null;
    }

    const timezone = schedule.timezone || 'UTC';
    const locale = document.documentElement.lang || undefined;

    if (!timeFormatters.has(`${locale}|${timezone}`)) {
        timeFormatters.set(`${locale}|${timezone}`, new Intl.DateTimeFormat(locale, {
            timeZone: timezone,
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        }));
    }

    if (localDateKey(date, timezone) === localDateKey(opensAt, timezone)) {
        return timeFormatters.get(`${locale}|${timezone}`).format(opensAt);
    }

    if (!dateTimeLabelFormatters.has(`${locale}|${timezone}`)) {
        dateTimeLabelFormatters.set(`${locale}|${timezone}`, new Intl.DateTimeFormat(locale, {
            timeZone: timezone,
            weekday: 'long',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        }));
    }

    return dateTimeLabelFormatters.get(`${locale}|${timezone}`).format(opensAt);
}

function openElapsedMinutes(startedAtMs, nowMs, schedule) {
    let minutes = 0;
    let cursorMs = startedAtMs - (startedAtMs % 60000);

    while (cursorMs + 60000 <= nowMs) {
        if (cursorMs >= startedAtMs && isOpenMinute(new Date(cursorMs), schedule)) {
            minutes++;
        }

        cursorMs += 60000;
    }

    return minutes;
}

function formatLiveDuration(element) {
    const startedAt = element.dataset.startedAt;
    const startedAtMs = Date.parse(startedAt);

    if (Number.isNaN(startedAtMs)) {
        return null;
    }

    const nowMs = Date.now();
    const schedule = openingScheduleForElement(element);
    const elapsedMinutes = schedule === null
        ? Math.max(0, Math.floor((nowMs - startedAtMs) / 60000))
        : openElapsedMinutes(startedAtMs, nowMs, schedule);

    return `${elapsedMinutes} min`;
}

function updateLiveDurations() {
    document.querySelectorAll(liveDurationSelector).forEach((element) => {
        const duration = formatLiveDuration(element);

        if (duration === null) {
            return;
        }

        const prefix = element.dataset.liveDurationPrefix;

        element.textContent = prefix ? `${prefix} ${duration}` : duration;
    });
}

function updateClassroomOpeningStatuses() {
    document.querySelectorAll(classroomOpeningStatusSelector).forEach((element) => {
        const schedule = openingScheduleForElement(element);
        const now = new Date();
        const isOpen = schedule === null || isOpenMinute(now, schedule);
        const label = isOpen
            ? (element.dataset.openLabel || 'Room open')
            : (element.dataset.closedLabel || 'Room closed');
        const dot = element.querySelector('[data-classroom-opening-status-dot]');
        const text = element.querySelector('[data-classroom-opening-status-text]')
            || element.parentElement?.querySelector('[data-classroom-opening-status-text]');

        element.setAttribute('aria-label', label);
        element.setAttribute('title', label);

        if (dot) {
            dot.classList.toggle('bg-emerald-500', isOpen);
            dot.classList.toggle('ring-emerald-100', isOpen);
            dot.classList.toggle('bg-rose-500', !isOpen);
            dot.classList.toggle('ring-rose-100', !isOpen);
        }

        if (!text) {
            return;
        }

        const until = schedule === null || isOpen ? null : closedUntilLabel(now, schedule);

        text.textContent = until
            ? (element.dataset.closedUntilTemplate || 'Room closed until :time').replace(':time', until)
            : '';
        element.classList.toggle('hidden', until === null);
    });
}

function startLiveDurations() {
    if (window.__lineupLiveDurationTimer) {
        updateLiveDurations();
        updateClassroomOpeningStatuses();

        return;
    }

    updateLiveDurations();
    updateClassroomOpeningStatuses();
    window.__lineupLiveDurationTimer = window.setInterval(updateLiveDurations, 30000);
    window.__lineupClassroomOpeningStatusTimer = window.setInterval(updateClassroomOpeningStatuses, classroomOpeningStatusRefreshMs);
}

function updatePageTitle(event) {
    const title = event.detail?.title;

    if (typeof title !== 'string' || title.length === 0) {
        return;
    }

    document.title = title;
}

document.addEventListener('DOMContentLoaded', startLiveDurations);
document.addEventListener('livewire:init', startLiveDurations);
document.addEventListener('page-title-updated', updatePageTitle);
document.addEventListener('teacher-page-title-updated', updatePageTitle);

function renderRequestStatisticsCharts(root) {
    const container = root?.matches?.('[data-statistics-charts]')
        ? root
        : root?.querySelector?.('[data-statistics-charts]');

    if (!container) {
        return;
    }

    const chartData = JSON.parse(container.dataset.chart || '{}');
    const labels = chartData.labels || [];
    const text = container.dataset;

    statisticsCharts.get(container)?.forEach((chart) => chart.destroy());

    const charts = [];
    const countCanvas = container.querySelector('[data-statistics-chart="counts"]');
    const durationCanvas = container.querySelector('[data-statistics-chart="durations"]');

    if (countCanvas) {
        charts.push(new Chart(countCanvas, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: text.requestsLabel || 'Requests',
                        data: chartData.requestCounts || [],
                        borderColor: '#4f46e5',
                        backgroundColor: 'rgba(79, 70, 229, 0.12)',
                        tension: 0.25,
                    },
                    {
                        label: text.studentsLabel || 'Distinct students',
                        data: chartData.studentCounts || [],
                        borderColor: '#059669',
                        backgroundColor: 'rgba(5, 150, 105, 0.12)',
                        tension: 0.25,
                    },
                ],
            },
            options: statisticsChartOptions(text.countAxisLabel || 'Count'),
        }));
    }

    if (durationCanvas) {
        charts.push(new Chart(durationCanvas, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: text.waitLabel || 'Average wait',
                        data: chartData.waitAverages || [],
                        borderColor: '#d97706',
                        backgroundColor: 'rgba(217, 119, 6, 0.12)',
                        tension: 0.25,
                    },
                    {
                        label: text.interventionLabel || 'Average intervention',
                        data: chartData.interventionAverages || [],
                        borderColor: '#dc2626',
                        backgroundColor: 'rgba(220, 38, 38, 0.12)',
                        tension: 0.25,
                    },
                ],
            },
            options: statisticsChartOptions(text.minutesAxisLabel || 'Minutes'),
        }));
    }

    statisticsCharts.set(container, charts);
}

function statisticsChartOptions(yAxisTitle) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        plugins: {
            legend: {
                position: 'bottom',
            },
        },
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: yAxisTitle,
                },
            },
        },
    };
}

window.renderRequestStatisticsCharts = renderRequestStatisticsCharts;
