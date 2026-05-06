<section class="rounded-lg border border-indigo-200 bg-indigo-50/50 p-4 shadow-sm">
    <div class="mb-4 flex items-start justify-between gap-3">
        <div>
            <h3 class="text-lg font-semibold text-gray-950">{{ __('Demandes en cours') }}</h3>
            <p class="text-sm text-gray-600">{{ __('Demandes prises en charge dans ce local.') }}</p>
        </div>
        <span class="rounded-full bg-indigo-100 px-3 py-1 text-sm font-semibold text-indigo-800">{{ $requests->count() }}</span>
    </div>

    <div class="space-y-3">
        @forelse ($requests as $supportRequest)
            @php
                $cardClass = match ($supportRequest->status) {
                    \App\Models\SupportRequest::STATUS_READY => 'border-amber-300 bg-amber-50 shadow-md ring-1 ring-amber-200',
                    \App\Models\SupportRequest::STATUS_PAUSED => 'border-sky-200 bg-sky-50 shadow-sm',
                    default => $supportRequest->is_priority ? 'border-rose-200 bg-rose-50 shadow-sm ring-1 ring-rose-100' : 'border-indigo-200 bg-white shadow-sm',
                };

                $badgeClass = match ($supportRequest->status) {
                    \App\Models\SupportRequest::STATUS_READY => 'bg-amber-200 text-amber-900',
                    \App\Models\SupportRequest::STATUS_PAUSED => 'bg-sky-100 text-sky-800',
                    default => $supportRequest->is_priority ? 'bg-rose-100 text-rose-800' : 'bg-indigo-100 text-indigo-800',
                };
            @endphp

            @if ($supportRequest->is_priority)
                <article wire:key="my-request-{{ $supportRequest->id }}" class="rounded-lg border {{ $cardClass }} p-4">
                    <div class="space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $badgeClass }}">{{ __('Prioritaire') }}</span>
                                    <span class="font-semibold text-gray-950">{{ __('Envoyee par') }} {{ $supportRequest->priorityRequester?->name ?? 'N/A' }}</span>
                                </div>
                                <div class="mt-2 text-sm text-gray-700">{{ $supportRequest->comment }}</div>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 text-sm">
                            <span class="rounded-full bg-white px-3 py-1 font-medium text-rose-700 ring-1 ring-rose-100">{{ __('Depuis') }} {{ ($supportRequest->assigned_at ?? $supportRequest->created_at)->diffForHumans(null, true) }}</span>
                        </div>

                        <div class="grid gap-2 sm:grid-cols-1">
                            <button type="button" wire:click="complete({{ $supportRequest->id }})" wire:loading.attr="disabled" wire:target="complete({{ $supportRequest->id }})" class="inline-flex justify-center rounded-md border border-transparent bg-gray-800 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700 disabled:opacity-50">
                                {{ __('Terminé') }}
                            </button>
                        </div>
                    </div>
                </article>
                @continue
            @endif

            @php
                $cardClass = match ($supportRequest->status) {
                    \App\Models\SupportRequest::STATUS_READY => 'border-amber-300 bg-amber-50 shadow-md ring-1 ring-amber-200',
                    \App\Models\SupportRequest::STATUS_PAUSED => 'border-sky-200 bg-sky-50 shadow-sm',
                    default => 'border-indigo-200 bg-white shadow-sm',
                };

                $badgeClass = match ($supportRequest->status) {
                    \App\Models\SupportRequest::STATUS_READY => 'bg-amber-200 text-amber-900',
                    \App\Models\SupportRequest::STATUS_PAUSED => 'bg-sky-100 text-sky-800',
                    default => 'bg-indigo-100 text-indigo-800',
                };
            @endphp

            @php
                $subjectUrl = $supportRequest->subjectUrl();
            @endphp

            <article wire:key="my-request-{{ $supportRequest->id }}" class="rounded-lg border {{ $cardClass }} p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 flex-1 space-y-2">
                        <div class="px-2">
                            <div class="min-w-0 text-base font-semibold text-gray-950">
                                {{ $supportRequest->student?->name ?? 'N/A' }}
                            </div>
                        </div>

                        @if ($subjectUrl)
                            <a
                                href="{{ $subjectUrl }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="block truncate rounded-md px-2 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-indigo-50 hover:text-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                aria-label="{{ __('Ouvrir le lien de la matiere') }}"
                            >
                                <span class="inline-flex min-w-0 items-center gap-1.5">
                                    <span class="truncate">{{ $supportRequest->subject?->name ?? 'N/A' }} - {{ __('Tuile') }} {{ $supportRequest->moodle_tile_number }}</span>
                                    <svg class="h-3.5 w-3.5 shrink-0 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 6.364 6.364l-1.768 1.768a4.5 4.5 0 0 1-6.364 0m1.768-8.132-1.768-1.768a4.5 4.5 0 0 0-6.364 0L3.29 8.688a4.5 4.5 0 0 0 6.364 6.364l1.768-1.768" />
                                    </svg>
                                </span>
                            </a>
                        @else
                            <div class="truncate px-2 py-1.5 text-sm font-medium text-gray-700">
                                {{ $supportRequest->subject?->name ?? 'N/A' }} - {{ __('Tuile') }} {{ $supportRequest->moodle_tile_number }}
                            </div>
                        @endif
                    </div>

                    <div class="flex shrink-0 flex-col items-end gap-2">
                        <div class="inline-flex items-center justify-end gap-1.5 rounded-md bg-gray-50 px-2.5 py-1 text-sm font-semibold text-gray-800 ring-1 ring-gray-200" title="{{ __('Table') }} {{ $supportRequest->table_number }}">
                            <svg class="h-4 w-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25h6m-7.5 3h9m-12-6.75h15A1.5 1.5 0 0 0 21 12V5.25a1.5 1.5 0 0 0-1.5-1.5h-15A1.5 1.5 0 0 0 3 5.25V12a1.5 1.5 0 0 0 1.5 1.5Z" />
                            </svg>
                            <span>{{ $supportRequest->table_number }}</span>
                        </div>

                        <div class="inline-flex items-stretch rounded-md shadow-sm">
                            <button
                                type="button"
                                wire:click="complete({{ $supportRequest->id }})"
                                wire:loading.attr="disabled"
                                wire:target="complete({{ $supportRequest->id }})"
                                class="inline-flex w-32 items-center justify-center rounded-l-md border border-transparent bg-gray-800 px-3 py-2 text-center text-xs font-semibold uppercase leading-tight tracking-widest text-white transition hover:bg-gray-700 focus:z-10 focus:outline-none focus:ring-2 focus:ring-gray-700 focus:ring-offset-2 disabled:opacity-50"
                            >
                                {{ __('Terminé') }}
                            </button>

                            <details class="js-my-requests-action-menu relative flex">
                                <summary
                                    class="flex h-full cursor-pointer list-none items-center justify-center rounded-r-md border-l border-gray-700 bg-gray-800 px-2 text-white transition hover:bg-gray-700 focus:z-10 focus:outline-none focus:ring-2 focus:ring-gray-700 focus:ring-offset-2"
                                    aria-label="{{ __('Ouvrir le menu des actions') }}"
                                    aria-haspopup="true"
                                >
                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </summary>

                                <div class="absolute right-0 top-full z-20 mt-2 w-52 rounded-md border border-gray-200 bg-white py-1 shadow-lg" role="menu">
                                    @if ($supportRequest->status !== \App\Models\SupportRequest::STATUS_PAUSED)
                                        <button
                                            type="button"
                                            wire:click="pause({{ $supportRequest->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="pause({{ $supportRequest->id }})"
                                            class="block w-full px-4 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-50 focus:bg-gray-50 focus:outline-none disabled:opacity-50"
                                            role="menuitem"
                                        >
                                            {{ __('Mettre en pause') }}
                                        </button>
                                    @endif

                                    <button
                                        type="button"
                                        wire:click="unassign({{ $supportRequest->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="unassign({{ $supportRequest->id }})"
                                        class="block w-full px-4 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-50 focus:bg-gray-50 focus:outline-none disabled:opacity-50"
                                        role="menuitem"
                                    >
                                        {{ __('Remettre dans la file') }}
                                    </button>
                                </div>
                            </details>
                        </div>
                    </div>
                </div>

                <div class="mt-3 border-t border-gray-100 px-2 pt-2 text-sm leading-snug text-gray-700">
                    <div class="float-right mb-1 ml-3 flex flex-wrap justify-end gap-1.5 text-xs">
                        <span class="rounded-full px-2.5 py-0.5 font-medium {{ $badgeClass }}">
                            {{ $supportRequest->status === \App\Models\SupportRequest::STATUS_READY ? __('Prêt à revoir') : ($statusLabels[$supportRequest->status] ?? $supportRequest->status) }}
                        </span>
                        <span class="rounded-full bg-white px-2.5 py-0.5 font-medium text-indigo-700 ring-1 ring-indigo-100">{{ $typeLabels[$supportRequest->type] ?? $supportRequest->type }}</span>
                        <span class="rounded-full bg-white px-2.5 py-0.5 font-medium text-gray-700 ring-1 ring-gray-200">{{ __('Depuis') }} {{ ($supportRequest->assigned_at ?? $supportRequest->created_at)->diffForHumans(null, true) }}</span>
                    </div>

                    @if ($supportRequest->comment)
                        <p>{{ $supportRequest->comment }}</p>
                    @endif

                    <div class="clear-both"></div>
                </div>
            </article>
        @empty
            <div class="rounded-lg border border-dashed border-indigo-200 bg-white/70 p-6 text-center text-sm text-gray-600">
                {{ __('Aucune demande en cours.') }}
            </div>
        @endforelse
    </div>
</section>

@once
    <script>
        document.addEventListener('click', function (event) {
            document.querySelectorAll('.js-my-requests-action-menu[open]').forEach(function (menu) {
                if (!menu.contains(event.target)) {
                    menu.removeAttribute('open');
                }
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('.js-my-requests-action-menu[open]').forEach(function (menu) {
                    menu.removeAttribute('open');
                });
            }
        });

        document.addEventListener('toggle', function (event) {
            if (!event.target.matches('.js-my-requests-action-menu') || !event.target.open) {
                return;
            }

            document.querySelectorAll('.js-my-requests-action-menu[open]').forEach(function (menu) {
                if (menu !== event.target) {
                    menu.removeAttribute('open');
                }
            });
        }, true);
    </script>
@endonce
