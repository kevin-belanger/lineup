<section class="rounded-lg border border-indigo-200 bg-indigo-50/50 p-4 shadow-sm">
    @php
        $courseUrlSettings = app(\App\Services\ApplicationSettings::class);
        $courseUrlTarget = $courseUrlSettings->courseUrlTarget();
        $courseUrlRel = $courseUrlSettings->courseUrlRel();
    @endphp

    <div class="mb-4 flex items-start justify-between gap-3">
        <div>
            <div class="flex items-center gap-1.5">
                <h3 class="text-lg font-semibold text-gray-950">{{ __('Your active requests') }}</h3>
                <details class="js-my-requests-settings-menu relative">
                    <summary
                        class="inline-flex h-7 w-7 cursor-pointer list-none items-center justify-center rounded-md text-gray-400 transition hover:bg-white hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        aria-label="{{ __('Active request options') }}"
                        aria-haspopup="true"
                        title="{{ __('Active request options') }}"
                    >
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a7.723 7.723 0 0 1 0 .255c-.007.378.138.75.431.992l1.003.827c.424.35.534.955.26 1.431l-1.296 2.247a1.125 1.125 0 0 1-1.37.49l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.542-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.075-.124l-1.217.456a1.125 1.125 0 0 1-1.37-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 0 1 0-.255c.007-.378-.138-.75-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.431l1.297-2.247a1.125 1.125 0 0 1 1.37-.49l1.217.456c.355.133.75.072 1.075-.124.073-.044.146-.086.22-.127.332-.184.582-.496.644-.87l.213-1.281Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </summary>

                    <div class="absolute left-0 top-full z-20 mt-2 w-72 rounded-md border border-gray-200 bg-white p-3 text-sm shadow-lg sm:left-auto sm:right-0" role="menu">
                        <label class="flex items-start gap-2 text-gray-700">
                            <input
                                type="checkbox"
                                wire:model.live="placeNewRequestsOnTop"
                                class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                            >
                            <span>{{ __('Place new requests at the top') }}</span>
                        </label>
                    </div>
                </details>
            </div>
            <p class="text-sm text-gray-600">{{ __('Requests you are currently taking.') }}</p>
        </div>
        <span class="rounded-full bg-indigo-100 px-3 py-1 text-sm font-semibold text-indigo-800">{{ $requests->count() }}</span>
    </div>

    <div
        wire:key="my-requests-list-{{ $refreshKey }}"
        wire:sort="moveRequestToPosition"
        wire:sort:config="{ animation: 150 }"
        data-active-requests-list
        class="space-y-3"
    >
        @forelse ($requests as $supportRequest)
            @php
                $cardClass = match ($supportRequest->status) {
                    \App\Models\SupportRequest::STATUS_READY => 'border-amber-300 bg-amber-50 shadow-md ring-1 ring-amber-200',
                    \App\Models\SupportRequest::STATUS_PAUSED => $supportRequest->is_priority ? 'border-rose-200 bg-rose-50 shadow-sm ring-1 ring-rose-100' : 'border-indigo-200 bg-white shadow-sm',
                    default => $supportRequest->is_priority ? 'border-rose-200 bg-rose-50 shadow-sm ring-1 ring-rose-100' : 'border-indigo-200 bg-white shadow-sm',
                };

                $badgeClass = match ($supportRequest->status) {
                    \App\Models\SupportRequest::STATUS_READY => 'bg-amber-200 text-amber-900',
                    \App\Models\SupportRequest::STATUS_PAUSED => 'bg-amber-100 text-amber-800',
                    default => $supportRequest->is_priority ? 'bg-rose-100 text-rose-800' : 'bg-indigo-100 text-indigo-800',
                };

                $priorityBadgeClass = 'bg-rose-100 text-rose-800';

                $completeButtonClass = 'bg-emerald-600 text-white hover:bg-emerald-500 focus:ring-emerald-500';

                $completeMenuClass = 'border-emerald-500 bg-emerald-600 text-white hover:bg-emerald-500 focus:ring-emerald-500';

                $pausedCardClass = $supportRequest->status === \App\Models\SupportRequest::STATUS_PAUSED ? 'opacity-60' : '';

                $durationStartedAt = $supportRequest->assigned_at ?? $supportRequest->created_at;
                $durationMinutes = intdiv((int) $durationStartedAt->diffInSeconds(now(), true), 60);
            @endphp

            @if ($supportRequest->is_priority)
                <article
                    wire:key="my-request-priority-{{ $supportRequest->id }}"
                    wire:transition="teacher-my-priority-request-{{ $supportRequest->id }}"
                    wire:sort:item="{{ $supportRequest->id }}"
                    data-active-request-card
                    data-request-id="{{ $supportRequest->id }}"
                    class="rounded-lg border {{ $cardClass }} p-4 {{ $pausedCardClass }}"
                >
                    <div class="space-y-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex min-w-0 flex-1 gap-2">
                                <button
                                    type="button"
                                    wire:sort:handle
                                    class="mt-0.5 inline-flex h-8 w-8 shrink-0 cursor-grab items-center justify-center rounded-md text-gray-400 transition hover:bg-white hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 active:cursor-grabbing"
                                    aria-label="{{ __('Reorder request') }}"
                                    title="{{ __('Reorder request') }}"
                                >
                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h.008v.008H8.25V6.75Zm0 5.25h.008v.008H8.25V12Zm0 5.25h.008v.008H8.25v-.008Zm7.5-10.5h.008v.008h-.008V6.75Zm0 5.25h.008v.008h-.008V12Zm0 5.25h.008v.008h-.008v-.008Z" />
                                    </svg>
                                </button>

                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $priorityBadgeClass }}">{{ __('Priority') }}</span>
                                        <span class="font-semibold text-gray-950">{{ __('Sent by') }} {{ $supportRequest->priorityRequesterDisplayName() }}</span>
                                    </div>
                                    <div class="mt-2 text-sm text-gray-700">{{ $supportRequest->comment }}</div>
                                </div>
                            </div>

                            <button type="button" wire:click="complete({{ $supportRequest->id }})" wire:loading.attr="disabled" wire:target="complete({{ $supportRequest->id }})" class="inline-flex min-h-12 w-32 shrink-0 items-center justify-center self-end rounded-md border border-transparent px-3 py-2 text-center text-xs font-semibold uppercase leading-tight tracking-widest transition focus:z-10 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 {{ $completeButtonClass }}">
                                {{ __('Complete') }}
                            </button>
                        </div>

                        <div class="flex flex-wrap justify-end gap-2 text-sm">
                            <div class="flex flex-wrap gap-2">
                                @if ($supportRequest->status === \App\Models\SupportRequest::STATUS_PAUSED)
                                    <span class="rounded-full bg-amber-100 px-3 py-1 font-medium text-amber-800">{{ __('Paused') }}</span>
                                @endif
                                <span
                                    class="rounded-full bg-white px-3 py-1 font-medium text-rose-700 ring-1 ring-rose-100"
                                    data-live-duration
                                    data-live-duration-prefix="{{ __('Since') }}"
                                    data-started-at="{{ $durationStartedAt->toIso8601String() }}"
                                >{{ __('Since') }} {{ $durationMinutes }} min</span>
                            </div>
                        </div>
                    </div>
                </article>
                <x-modal name="personal-note-{{ $supportRequest->id }}" maxWidth="lg" focusable>
                    <form
                        wire:submit="savePersonalNote({{ $supportRequest->id }})"
                        x-data
                        x-on:open-modal.window="$event.detail === 'personal-note-{{ $supportRequest->id }}' && setTimeout(() => $refs.noteBody?.focus(), 150)"
                        class="p-6"
                    >
                        <h3 class="text-lg font-semibold text-gray-900">{{ __('Personal note') }}</h3>
                        <p class="mt-1 text-sm text-gray-600">{{ __('This note is private and visible only to you.') }}</p>

                        <div class="mt-5">
                            @include('livewire.teacher.partials.personal-note-request-summary', ['supportRequest' => $supportRequest])
                        </div>

                        <div class="mt-5">
                            <x-input-label for="personal-note-{{ $supportRequest->id }}-body" :value="__('Note')" />
                            <textarea
                                id="personal-note-{{ $supportRequest->id }}-body"
                                x-ref="noteBody"
                                wire:model="noteBodies.{{ $supportRequest->id }}"
                                rows="5"
                                maxlength="2000"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            ></textarea>
                            <x-input-error :messages="$errors->get('noteBodies.'.$supportRequest->id)" class="mt-2" />
                        </div>

                        <div class="mt-6 flex justify-end gap-3">
                            <x-secondary-button type="button" x-on:click="$dispatch('close')">
                                {{ __('Cancel') }}
                            </x-secondary-button>
                            <x-primary-button wire:loading.attr="disabled" wire:target="savePersonalNote({{ $supportRequest->id }})">
                                {{ __('Save note') }}
                            </x-primary-button>
                        </div>
                    </form>
                </x-modal>
                @continue
            @endif

            @php
                $cardClass = match ($supportRequest->status) {
                    \App\Models\SupportRequest::STATUS_READY => 'border-amber-300 bg-amber-50 shadow-md ring-1 ring-amber-200',
                    \App\Models\SupportRequest::STATUS_PAUSED => 'border-indigo-200 bg-white shadow-sm',
                    default => 'border-indigo-200 bg-white shadow-sm',
                };

                $badgeClass = match ($supportRequest->status) {
                    \App\Models\SupportRequest::STATUS_READY => 'bg-amber-200 text-amber-900',
                    \App\Models\SupportRequest::STATUS_PAUSED => 'bg-amber-100 text-amber-800',
                    default => 'bg-indigo-100 text-indigo-800',
                };

                $completeButtonClass = 'bg-emerald-600 text-white hover:bg-emerald-500 focus:ring-emerald-500';

                $completeMenuClass = 'border-emerald-500 bg-emerald-600 text-white hover:bg-emerald-500 focus:ring-emerald-500';

                $pausedCardClass = $supportRequest->status === \App\Models\SupportRequest::STATUS_PAUSED ? 'opacity-60' : '';

                $durationStartedAt = $supportRequest->assigned_at ?? $supportRequest->created_at;
                $durationMinutes = intdiv((int) $durationStartedAt->diffInSeconds(now(), true), 60);
            @endphp

            @php
                $subjectUrl = $supportRequest->subjectUrl();
            @endphp

            <article
                wire:key="my-request-regular-{{ $supportRequest->id }}"
                wire:transition="teacher-my-regular-request-{{ $supportRequest->id }}"
                wire:sort:item="{{ $supportRequest->id }}"
                data-active-request-card
                data-request-id="{{ $supportRequest->id }}"
                class="rounded-lg border {{ $cardClass }} p-4 {{ $pausedCardClass }}"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex min-w-0 flex-1 gap-2">
                        <button
                            type="button"
                            wire:sort:handle
                            class="mt-1 inline-flex h-8 w-8 shrink-0 cursor-grab items-center justify-center rounded-md text-gray-400 transition hover:bg-indigo-50 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 active:cursor-grabbing"
                            aria-label="{{ __('Reorder request') }}"
                            title="{{ __('Reorder request') }}"
                        >
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h.008v.008H8.25V6.75Zm0 5.25h.008v.008H8.25V12Zm0 5.25h.008v.008H8.25v-.008Zm7.5-10.5h.008v.008h-.008V6.75Zm0 5.25h.008v.008h-.008V12Zm0 5.25h.008v.008h-.008v-.008Z" />
                            </svg>
                        </button>

                        <div class="min-w-0 flex-1 space-y-2">
                            <div class="px-2">
                                <div class="min-w-0 text-base font-semibold text-gray-950">
                                    {{ $supportRequest->studentDisplayName() }}
                                </div>
                            </div>

                            @if ($subjectUrl)
                                <a
                                    href="{{ $subjectUrl }}"
                                    target="{{ $courseUrlTarget }}"
                                    @if ($courseUrlRel) rel="{{ $courseUrlRel }}" @endif
                                    class="block rounded-md px-2 py-1.5 text-sm font-medium leading-snug text-gray-700 transition hover:bg-indigo-50 hover:text-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    aria-label="{{ __('Open the subject link') }}"
                                >
                                    <span class="whitespace-normal break-words">
                                        {{ $supportRequest->subject?->name ?? 'N/A' }} - {{ __('Tile') }} {{ $supportRequest->moodle_tile_number }}
                                        <svg class="ml-1 inline-block h-3.5 w-3.5 shrink-0 align-[-2px] text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 6.364 6.364l-1.768 1.768a4.5 4.5 0 0 1-6.364 0m1.768-8.132-1.768-1.768a4.5 4.5 0 0 0-6.364 0L3.29 8.688a4.5 4.5 0 0 0 6.364 6.364l1.768-1.768" />
                                        </svg>
                                    </span>
                                </a>
                            @else
                                <div class="whitespace-normal break-words px-2 py-1.5 text-sm font-medium leading-snug text-gray-700">
                                    {{ $supportRequest->subject?->name ?? 'N/A' }} - {{ __('Tile') }} {{ $supportRequest->moodle_tile_number }}
                                </div>
                            @endif
                        </div>
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
                                class="inline-flex min-h-12 w-32 items-center justify-center rounded-l-md border border-transparent px-3 py-2 text-center text-xs font-semibold uppercase leading-tight tracking-widest transition focus:z-10 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 {{ $completeButtonClass }}"
                            >
                                {{ __('Complete') }}
                            </button>

                            <details class="js-my-requests-action-menu relative flex">
                                <summary
                                    class="flex h-full cursor-pointer list-none items-center justify-center rounded-r-md border-l px-2 transition focus:z-10 focus:outline-none focus:ring-2 focus:ring-offset-2 {{ $completeMenuClass }}"
                                    aria-label="{{ __('Open actions menu') }}"
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
                                            {{ __('Pause') }}
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
                                        {{ __('Return to queue') }}
                                    </button>

                                    <button
                                        type="button"
                                        x-data
                                        x-on:click="$dispatch('open-modal', 'personal-note-{{ $supportRequest->id }}'); $el.closest('details')?.removeAttribute('open')"
                                        class="block w-full px-4 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-50 focus:bg-gray-50 focus:outline-none"
                                        role="menuitem"
                                    >
                                        {{ __('Create personal note') }}
                                    </button>
                                </div>
                            </details>
                        </div>
                    </div>
                </div>

                <div class="mt-3 border-t border-gray-100 px-2 pt-2 text-sm leading-snug text-gray-700">
                    <div class="float-right mb-1 ml-3 flex flex-wrap justify-end gap-1.5 text-xs">
                        @if ($supportRequest->status !== \App\Models\SupportRequest::STATUS_ASSIGNED)
                            <span class="rounded-full px-2.5 py-0.5 font-medium {{ $badgeClass }}">
                                {{ $supportRequest->status === \App\Models\SupportRequest::STATUS_READY ? __('Ready to review') : ($statusLabels[$supportRequest->status] ?? $supportRequest->status) }}
                            </span>
                        @endif
                        @if ($supportRequest->typeLabel() !== '')
                            <span class="rounded-full bg-white px-2.5 py-0.5 font-medium text-indigo-700 ring-1 ring-indigo-100">{{ $supportRequest->typeLabel() }}</span>
                        @endif
                        <span
                            class="rounded-full bg-white px-2.5 py-0.5 font-medium text-gray-700 ring-1 ring-gray-200"
                            data-live-duration
                            data-live-duration-prefix="{{ __('Since') }}"
                            data-started-at="{{ $durationStartedAt->toIso8601String() }}"
                        >{{ __('Since') }} {{ $durationMinutes }} min</span>
                    </div>

                    @if ($supportRequest->comment)
                        <p>{{ $supportRequest->comment }}</p>
                    @endif

                    <div class="clear-both"></div>
                </div>
            </article>
            <x-modal name="personal-note-{{ $supportRequest->id }}" maxWidth="lg" focusable>
                <form
                    wire:submit="savePersonalNote({{ $supportRequest->id }})"
                    x-data
                    x-on:open-modal.window="$event.detail === 'personal-note-{{ $supportRequest->id }}' && setTimeout(() => $refs.noteBody?.focus(), 150)"
                    class="p-6"
                >
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Personal note') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ __('This note is private and visible only to you.') }}</p>

                    <div class="mt-5">
                        @include('livewire.teacher.partials.personal-note-request-summary', ['supportRequest' => $supportRequest])
                    </div>

                    <div class="mt-5">
                        <x-input-label for="personal-note-{{ $supportRequest->id }}-body" :value="__('Note')" />
                        <textarea
                            id="personal-note-{{ $supportRequest->id }}-body"
                            x-ref="noteBody"
                            wire:model="noteBodies.{{ $supportRequest->id }}"
                            rows="5"
                            maxlength="2000"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        ></textarea>
                        <x-input-error :messages="$errors->get('noteBodies.'.$supportRequest->id)" class="mt-2" />
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button type="button" x-on:click="$dispatch('close')">
                            {{ __('Cancel') }}
                        </x-secondary-button>
                        <x-primary-button wire:loading.attr="disabled" wire:target="savePersonalNote({{ $supportRequest->id }})">
                            {{ __('Save note') }}
                        </x-primary-button>
                    </div>
                </form>
            </x-modal>
        @empty
            <div class="rounded-lg border border-dashed border-indigo-200 bg-white/70 p-6 text-center text-sm text-gray-600">
                {{ __('No active requests.') }}
            </div>
        @endforelse
    </div>
</section>

@once
    <script>
        (function () {
            if (window.__lineupActiveRequestHighlightInstalled) {
                return;
            }

            window.__lineupActiveRequestHighlightInstalled = true;
            window.__lineupKnownActiveRequestIds = window.__lineupKnownActiveRequestIds || new Set();
            window.__lineupActiveRequestIdsInitialized = window.__lineupActiveRequestIdsInitialized || false;
            let scanScheduled = false;

            function highlightActiveRequestCard(card) {
                card.querySelectorAll(':scope > .lineup-active-request-highlight-overlay').forEach(function (overlay) {
                    overlay.remove();
                });

                const overlay = document.createElement('span');

                overlay.className = 'lineup-active-request-highlight-overlay';
                overlay.setAttribute('aria-hidden', 'true');
                card.classList.add('lineup-active-request-highlight');
                card.appendChild(overlay);

                overlay.addEventListener('animationend', function () {
                    overlay.remove();
                    card.classList.remove('lineup-active-request-highlight');
                }, { once: true });
            }

            function scanActiveRequestCards() {
                const cards = document.querySelectorAll('[data-active-requests-list] [data-active-request-card][data-request-id]');
                const currentIds = new Set();

                cards.forEach(function (card) {
                    const requestId = card.dataset.requestId;

                    if (!requestId) {
                        return;
                    }

                    currentIds.add(requestId);

                    if (window.__lineupActiveRequestIdsInitialized && !window.__lineupKnownActiveRequestIds.has(requestId)) {
                        highlightActiveRequestCard(card);
                    }
                });

                window.__lineupKnownActiveRequestIds = currentIds;
                window.__lineupActiveRequestIdsInitialized = true;
            }

            function scheduleScan() {
                if (scanScheduled) {
                    return;
                }

                scanScheduled = true;
                window.requestAnimationFrame(function () {
                    scanScheduled = false;
                    scanActiveRequestCards();
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', scanActiveRequestCards, { once: true });
            } else {
                scanActiveRequestCards();
            }

            new MutationObserver(scheduleScan).observe(document.body, {
                childList: true,
                subtree: true,
            });
        })();

        document.addEventListener('click', function (event) {
            document.querySelectorAll('.js-my-requests-action-menu[open], .js-my-requests-settings-menu[open]').forEach(function (menu) {
                if (!menu.contains(event.target)) {
                    menu.removeAttribute('open');
                }
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('.js-my-requests-action-menu[open], .js-my-requests-settings-menu[open]').forEach(function (menu) {
                    menu.removeAttribute('open');
                });
            }
        });

        document.addEventListener('toggle', function (event) {
            if (!event.target.matches('.js-my-requests-action-menu, .js-my-requests-settings-menu') || !event.target.open) {
                return;
            }

            document.querySelectorAll('.js-my-requests-action-menu[open], .js-my-requests-settings-menu[open]').forEach(function (menu) {
                if (menu !== event.target) {
                    menu.removeAttribute('open');
                }
            });
        }, true);
    </script>
@endonce
