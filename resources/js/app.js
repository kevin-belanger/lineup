import './bootstrap';

const liveDurationSelector = '[data-live-duration][data-started-at]';
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

function startLiveDurations() {
    if (window.__lineupLiveDurationTimer) {
        updateLiveDurations();

        return;
    }

    updateLiveDurations();
    window.__lineupLiveDurationTimer = window.setInterval(updateLiveDurations, 30000);
}

function updateTeacherPageTitle(event) {
    const title = event.detail?.title;

    if (typeof title !== 'string' || title.length === 0) {
        return;
    }

    document.title = title;
}

document.addEventListener('DOMContentLoaded', startLiveDurations);
document.addEventListener('livewire:init', startLiveDurations);
document.addEventListener('teacher-page-title-updated', updateTeacherPageTitle);
