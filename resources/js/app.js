import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

const liveDurationSelector = '[data-live-duration][data-started-at]';

function formatLiveDuration(startedAt) {
    const startedAtMs = Date.parse(startedAt);

    if (Number.isNaN(startedAtMs)) {
        return null;
    }

    const elapsedMinutes = Math.max(0, Math.floor((Date.now() - startedAtMs) / 60000));

    return `${elapsedMinutes} min`;
}

function updateLiveDurations() {
    document.querySelectorAll(liveDurationSelector).forEach((element) => {
        const duration = formatLiveDuration(element.dataset.startedAt);

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
    window.__lineupLiveDurationTimer = window.setInterval(updateLiveDurations, 1000);
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
