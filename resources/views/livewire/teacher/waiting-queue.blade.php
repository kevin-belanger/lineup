<section class="space-y-4 rounded-lg border border-gray-200 bg-white/60 p-4 shadow-sm">
    @php
        $courseUrlSettings = app(\App\Services\ApplicationSettings::class);
        $courseUrlTarget = $courseUrlSettings->courseUrlTarget();
        $courseUrlRel = $courseUrlSettings->courseUrlRel();
        $openingHours = app(\App\Services\ClassroomOpeningHours::class);
        $liveDurationSchedule = $classroom
            ? $openingHours->liveDurationSchedule($classroom)
            : ['timezone' => $courseUrlSettings->timezone(), 'periods' => []];
    @endphp

    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">{{ __('Waiting requests') }}</h3>
            <p class="text-sm text-gray-600">{{ __('Requests in this room that have not been taken yet.') }}</p>
        </div>
        <span class="rounded-full bg-gray-200 px-3 py-1 text-sm font-medium text-gray-700">{{ $requests->count() }}</span>
    </div>

    <div class="space-y-4">
        @forelse ($requests as $supportRequest)
            @php
                $waitMinutes = $classroom
                    ? $openingHours->openMinutesBetween($classroom, $supportRequest->created_at, now())
                    : intdiv((int) $supportRequest->created_at->diffInSeconds(now(), true), 60);
            @endphp

            @if ($supportRequest->is_priority)
                <article wire:key="waiting-request-{{ $supportRequest->id }}" wire:transition="teacher-waiting-request-{{ $supportRequest->id }}" class="rounded-lg border border-rose-200 bg-rose-50 p-5 shadow-sm ring-1 ring-rose-100">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-rose-800">{{ __('Priority') }}</span>
                                <span class="text-base font-semibold text-gray-900">{{ __('Sent by') }} {{ $supportRequest->priorityRequesterDisplayName() }}</span>
                            </div>

                            <p class="text-sm text-gray-800">{{ $supportRequest->comment }}</p>

                            <div class="flex flex-wrap gap-2 text-sm">
                                <span
                                    class="rounded-full bg-white px-3 py-1 font-medium text-rose-700 ring-1 ring-rose-100"
                                    data-live-duration
                                    data-live-duration-prefix="{{ __('Waiting') }}"
                                    data-started-at="{{ $supportRequest->created_at->toIso8601String() }}"
                                    data-opening-hours='@json($liveDurationSchedule)'
                                >{{ __('Waiting') }} {{ $waitMinutes }} min</span>
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-col gap-2 sm:w-44">
                            <button
                                type="button"
                                wire:click="assign({{ $supportRequest->id }})"
                                wire:loading.attr="disabled"
                                wire:target="assign({{ $supportRequest->id }})"
                                class="inline-flex justify-center rounded-md border border-transparent bg-rose-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 disabled:opacity-50"
                            >
                                {{ __('Take') }}
                            </button>
                        </div>
                    </div>
                </article>
            @else
                @php
                    $subjectUrl = $supportRequest->subjectUrl();
                @endphp

                <article wire:key="waiting-request-{{ $supportRequest->id }}" wire:transition="teacher-waiting-request-{{ $supportRequest->id }}" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 flex-1 space-y-2">
                            <div class="px-2">
                                <div class="min-w-0 text-base font-semibold text-gray-900">
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
                                        <x-support-request-subject-summary :support-request="$supportRequest" />
                                        <svg class="ml-1 inline-block h-3.5 w-3.5 shrink-0 align-[-2px] text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 6.364 6.364l-1.768 1.768a4.5 4.5 0 0 1-6.364 0m1.768-8.132-1.768-1.768a4.5 4.5 0 0 0-6.364 0L3.29 8.688a4.5 4.5 0 0 0 6.364 6.364l1.768-1.768" />
                                        </svg>
                                    </span>
                                </a>
                            @else
                                <div class="whitespace-normal break-words px-2 py-1.5 text-sm font-medium leading-snug text-gray-700">
                                    <x-support-request-subject-summary :support-request="$supportRequest" />
                                </div>
                            @endif

                        </div>

                        <div class="flex shrink-0 flex-col items-end gap-2">
                            @if ($supportRequest->shouldShowTableNumber())
                                <div class="inline-flex items-center justify-end gap-1.5 rounded-md bg-gray-50 px-2.5 py-1 text-sm font-semibold text-gray-800 ring-1 ring-gray-200" title="{{ __('Table') }} {{ $supportRequest->table_number }}">
                                    <svg class="h-4 w-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25h6m-7.5 3h9m-12-6.75h15A1.5 1.5 0 0 0 21 12V5.25a1.5 1.5 0 0 0-1.5-1.5h-15A1.5 1.5 0 0 0 3 5.25V12a1.5 1.5 0 0 0 1.5 1.5Z" />
                                    </svg>
                                    <span>{{ $supportRequest->table_number }}</span>
                                </div>
                            @endif

                            <div class="inline-flex items-stretch rounded-md shadow-sm">
                                <button
                                    type="button"
                                    wire:click="assign({{ $supportRequest->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="assign({{ $supportRequest->id }})"
                                    class="inline-flex uppercase w-32 items-center justify-center rounded-l-md border border-transparent bg-indigo-600 px-3 py-2 text-center text-xs font-semibold leading-tight text-white transition hover:bg-indigo-500 focus:z-10 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50"
                                >
                                    <span class="block leading-tight">
                                        {{ __('Take') }}
                                    </span>
                                </button>

                                <details class="js-waiting-action-menu relative flex">
                                    <summary
                                        class="flex h-full cursor-pointer list-none items-center justify-center rounded-r-md border-l border-indigo-500 bg-indigo-600 px-2 text-white transition hover:bg-indigo-500 focus:z-10 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                        aria-label="{{ __('Open actions menu') }}"
                                        aria-haspopup="true"
                                    >
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </summary>

                                    <div class="absolute right-0 top-full z-20 mt-2 w-max min-w-full max-w-xs rounded-md border border-gray-200 bg-white py-1 shadow-lg" role="menu">
                                        <button
                                            type="button"
                                            wire:click="assignAndComplete({{ $supportRequest->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="assignAndComplete({{ $supportRequest->id }})"
                                            class="block w-full whitespace-nowrap px-4 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:bg-gray-50 disabled:opacity-50"
                                            role="menuitem"
                                        >
                                            {{ __('Take and complete') }}
                                        </button>

                                        <button
                                            type="button"
                                            wire:click="confirmCancel({{ $supportRequest->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="confirmCancel({{ $supportRequest->id }})"
                                            class="block w-full whitespace-nowrap px-4 py-2 text-left text-sm text-red-700 transition hover:bg-red-50 focus:outline-none focus:bg-red-50 disabled:opacity-50"
                                            role="menuitem"
                                        >
                                            {{ __('Cancel this request') }}
                                        </button>
                                    </div>
                                </details>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 border-t border-gray-100 px-2 pt-2 text-sm leading-snug text-gray-700">
                        <div class="float-right mb-1 ml-3 flex flex-wrap justify-end gap-1.5 text-xs">
                            @if ($supportRequest->typeLabel() !== '')
                                <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 font-medium text-indigo-700">{{ $supportRequest->typeLabel() }}</span>
                            @endif
                            <span
                                class="rounded-full bg-amber-50 px-2.5 py-0.5 font-medium text-amber-700"
                                data-live-duration
                                data-live-duration-prefix="{{ __('Waiting') }}"
                                data-started-at="{{ $supportRequest->created_at->toIso8601String() }}"
                                data-opening-hours='@json($liveDurationSchedule)'
                            >{{ __('Waiting') }} {{ $waitMinutes }} min</span>
                        </div>

                        @if ($supportRequest->comment)
                            <p>{{ $supportRequest->comment }}</p>
                        @endif

                        <div class="clear-both"></div>
                    </div>
                </article>
            @endif
        @empty
            <div class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-gray-600">
                {{ __('No waiting requests for this room.') }}
            </div>
        @endforelse
    </div>

    @if ($confirmingCancellationId)
        <div class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6">
            <button type="button" class="absolute inset-0 bg-gray-500 opacity-75" wire:click="dismissCancel" aria-label="{{ __('Back') }}"></button>

            <x-confirmation-panel
                :title="__('Cancel request')"
                :message="__('This request will be cancelled and will no longer be visible in the queue. Do you want to continue?')"
            >
                <x-slot name="actions">
                    <x-secondary-button type="button" wire:click="dismissCancel">
                        {{ __('Back') }}
                    </x-secondary-button>

                    <x-danger-button type="button" wire:click="cancel" wire:loading.attr="disabled" wire:target="cancel">
                        {{ __('Cancel request') }}
                    </x-danger-button>
                </x-slot>
            </x-confirmation-panel>
        </div>
    @endif
</section>


@once
    <script>
        document.addEventListener('click', function (event) {
            document.querySelectorAll('.js-waiting-action-menu[open]').forEach(function (menu) {
                if (!menu.contains(event.target)) {
                    menu.removeAttribute('open');
                }
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('.js-waiting-action-menu[open]').forEach(function (menu) {
                    menu.removeAttribute('open');
                });
            }
        });

        document.addEventListener('toggle', function (event) {
            if (!event.target.matches('.js-waiting-action-menu') || !event.target.open) {
                return;
            }

            document.querySelectorAll('.js-waiting-action-menu[open]').forEach(function (menu) {
                if (menu !== event.target) {
                    menu.removeAttribute('open');
                }
            });
        }, true);
    </script>
@endonce
