@php
    $flashToasts = [];
    $statusMessages = [
        'password-updated' => __('Mot de passe mis a jour.'),
        'profile-updated' => __('Profil mis a jour.'),
        'verification-link-sent' => __('Un nouveau lien de verification a ete envoye.'),
    ];
    $statusTypes = [
        'Ce compte est desactive.' => 'error',
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
        add(event) {
            const detail = event.detail ?? {};
            const type = detail.type ?? 'info';
            const toast = {
                id: Date.now() + Math.random(),
                type,
                message: detail.message ?? '',
                timeout: detail.timeout ?? (type === 'success' ? 4000 : 10000),
            };

            this.toasts.push(toast);

            setTimeout(() => {
                this.toasts = this.toasts.filter((item) => item.id !== toast.id);
            }, toast.timeout);
        },
        classes(type) {
            return {
                success: 'border-green-200 bg-green-50 text-green-800',
                info: 'border-sky-200 bg-sky-50 text-sky-800',
                warning: 'border-amber-200 bg-amber-50 text-amber-900',
                error: 'border-red-200 bg-red-50 text-red-800',
            }[type] ?? 'border-sky-200 bg-sky-50 text-sky-800';
        },
    }"
    x-init="@foreach ($flashToasts as $flashToast) add({ detail: @js($flashToast) }); @endforeach"
    x-on:toast.window="add($event)"
    class="fixed bottom-4 right-4 z-50 w-[min(24rem,calc(100vw-2rem))] space-y-3 sm:bottom-6 sm:right-6"
    aria-live="polite"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-x-4 opacity-0"
            x-transition:enter-end="translate-x-0 opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0 opacity-100"
            x-transition:leave-end="translate-x-4 opacity-0"
            class="rounded-md border px-4 py-3 text-sm shadow-sm"
            :class="classes(toast.type)"
        >
            <div class="flex items-start justify-between gap-3">
                <p x-text="toast.message"></p>
                <button
                    type="button"
                    x-on:click="toasts = toasts.filter((item) => item.id !== toast.id)"
                    class="font-semibold opacity-70 transition hover:opacity-100"
                    aria-label="{{ __('Fermer') }}"
                >
                    &times;
                </button>
            </div>
        </div>
    </template>
</div>
