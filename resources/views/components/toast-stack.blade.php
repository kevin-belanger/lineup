@php
    $flashToasts = [];
    $statusMessages = [
        'password-updated' => __('Password updated.'),
        'profile-updated' => __('Profile updated.'),
        'verification-link-sent' => __('A new verification link has been sent.'),
    ];
    $statusTypes = [
        'This account is deactivated.' => 'error',
    ];

    if (session('toast')) {
        $toast = session('toast');
        $flashToasts[] = is_array($toast)
            ? $toast
            : ['type' => 'info', 'message' => (string) $toast];
    }

    if (session('status')) {
        $status = session('status');
        $flashToasts[] = [
            'type' => $statusTypes[$status] ?? 'success',
            'message' => $statusMessages[$status] ?? (string) $status,
        ];
    }

    foreach (['success', 'info', 'warning', 'error'] as $type) {
        if (session($type)) {
            $flashToasts[] = ['type' => $type, 'message' => session($type)];
        }
    }

    $flashToasts = collect($flashToasts)
        ->filter(fn (array $toast): bool => filled($toast['message'] ?? null))
        ->unique(fn (array $toast): string => ($toast['type'] ?? 'info').'|'.($toast['message'] ?? ''))
        ->values();
@endphp

<div
    x-data="{
        toasts: [],
        progressInterval: null,
        add(event) {
            const detail = event.detail ?? {};
            const type = detail.type ?? 'info';
            const timeout = Number(detail.timeout ?? (type === 'success' ? 4000 : 10000));
            const toast = {
                id: Date.now() + Math.random(),
                type,
                message: detail.message ?? '',
                action: detail.action ?? null,
                timeout,
                remaining: timeout,
                startedAt: Date.now(),
                timeoutId: null,
                progress: 100,
                paused: false,
            };

            this.toasts.push(toast);
            this.schedule(toast);
            this.ensureProgressInterval();
        },
        schedule(toast) {
            this.clearToastTimeout(toast);
            toast.startedAt = Date.now();
            toast.timeoutId = setTimeout(() => this.remove(toast.id), toast.remaining);
        },
        clearToastTimeout(toast) {
            if (toast.timeoutId) {
                clearTimeout(toast.timeoutId);
                toast.timeoutId = null;
            }
        },
        pause(toast) {
            if (toast.paused) {
                return;
            }

            toast.remaining = Math.max(0, toast.remaining - (Date.now() - toast.startedAt));
            toast.paused = true;
            this.clearToastTimeout(toast);
            this.updateProgress();
        },
        resume(toast) {
            if (! toast.paused) {
                return;
            }

            toast.paused = false;

            if (toast.remaining <= 0) {
                this.remove(toast.id);
                return;
            }

            this.schedule(toast);
        },
        remove(id) {
            const toast = this.toasts.find((item) => item.id === id);

            if (toast) {
                this.clearToastTimeout(toast);
            }

            this.toasts = this.toasts.filter((item) => item.id !== id);

            if (this.toasts.length === 0 && this.progressInterval) {
                clearInterval(this.progressInterval);
                this.progressInterval = null;
            }
        },
        runAction(toast) {
            const action = toast.action;

            if (action?.event && window.Livewire) {
                window.Livewire.dispatch(action.event, action.payload ?? {});
            }

            this.remove(toast.id);
        },
        updateProgress() {
            this.toasts.forEach((toast) => {
                const remaining = toast.paused
                    ? toast.remaining
                    : Math.max(0, toast.remaining - (Date.now() - toast.startedAt));

                toast.progress = toast.timeout > 0
                    ? Math.max(0, Math.min(100, (remaining / toast.timeout) * 100))
                    : 0;
            });
        },
        ensureProgressInterval() {
            if (this.progressInterval) {
                this.updateProgress();
                return;
            }

            this.updateProgress();
            this.progressInterval = setInterval(() => this.updateProgress(), 100);
        },
        destroy() {
            if (this.progressInterval) {
                clearInterval(this.progressInterval);
            }

            this.toasts.forEach((toast) => this.clearToastTimeout(toast));
        },
        classes(type) {
            return {
                success: 'border-green-200 bg-green-50 text-green-800',
                info: 'border-sky-200 bg-sky-50 text-sky-800',
                warning: 'border-amber-200 bg-amber-50 text-amber-900',
                error: 'border-red-200 bg-red-50 text-red-800',
            }[type] ?? 'border-sky-200 bg-sky-50 text-sky-800';
        },
        progressClasses(type) {
            return {
                success: 'bg-green-500',
                info: 'bg-sky-500',
                warning: 'bg-amber-500',
                error: 'bg-red-500',
            }[type] ?? 'bg-sky-500';
        },
    }"
    x-init="@foreach ($flashToasts as $flashToast) add({ detail: @js($flashToast) }); @endforeach"
    x-on:toast.window="add($event)"
    class="fixed bottom-4 right-4 z-50 w-[min(28rem,calc(100vw-2rem))] space-y-3 sm:bottom-6 sm:right-6"
    aria-live="polite"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-on:mouseenter="pause(toast)"
            x-on:mouseleave="resume(toast)"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-x-4 opacity-0"
            x-transition:enter-end="translate-x-0 opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0 opacity-100"
            x-transition:leave-end="translate-x-4 opacity-0"
            class="relative overflow-hidden rounded-lg border px-5 py-4 text-sm shadow-lg"
            :class="classes(toast.type)"
        >
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="leading-5" x-text="toast.message"></p>

                    <template x-if="toast.action">
                        <button
                            type="button"
                            x-on:click="runAction(toast)"
                            class="mt-2 inline-flex font-semibold underline-offset-2 hover:underline focus:outline-none focus:underline"
                            x-text="toast.action.label"
                        ></button>
                    </template>
                </div>
                <button
                    type="button"
                    x-on:click="remove(toast.id)"
                    class="font-semibold opacity-70 transition hover:opacity-100"
                    aria-label="{{ __('Close') }}"
                >
                    &times;
                </button>
            </div>

            <div class="absolute inset-x-0 bottom-0 h-1 bg-black/10">
                <div
                    class="h-full transition-[width] duration-100 ease-linear"
                    :class="progressClasses(toast.type)"
                    :style="`width: ${toast.progress}%`"
                ></div>
            </div>
        </div>
    </template>
</div>
