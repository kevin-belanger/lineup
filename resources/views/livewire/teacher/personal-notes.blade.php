<div class="space-y-6">
    @php
        $courseUrlSettings = app(\App\Services\ApplicationSettings::class);
        $courseUrlTarget = $courseUrlSettings->courseUrlTarget();
        $courseUrlRel = $courseUrlSettings->courseUrlRel();
    @endphp

    <div class="flex justify-end">
        <x-primary-button
            type="button"
            x-data
            x-on:click="$dispatch('open-modal', 'create-personal-note')"
        >
            {{ __('Add note') }}
        </x-primary-button>
    </div>

    <x-modal name="create-personal-note" maxWidth="lg" focusable>
        <form
            wire:submit="create"
            x-data
            x-on:open-modal.window="$event.detail === 'create-personal-note' && setTimeout(() => $refs.noteBody?.focus(), 150)"
            class="p-6"
        >
            <h3 class="text-lg font-semibold text-gray-900">{{ __('New personal note') }}</h3>

            <div class="mt-5">
                <x-input-label for="personal-note-body" :value="__('Note')" />
                <textarea
                    id="personal-note-body"
                    x-ref="noteBody"
                    wire:model="body"
                    rows="5"
                    maxlength="2000"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                ></textarea>
                <x-input-error :messages="$errors->get('body')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">
                    {{ __('Cancel') }}
                </x-secondary-button>
                <x-primary-button wire:loading.attr="disabled" wire:target="create">
                    {{ __('Save note') }}
                </x-primary-button>
            </div>
        </form>
    </x-modal>

    <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4">
            <h3 class="text-base font-semibold text-gray-900">{{ __('Personal notes') }}</h3>
        </div>

        <div class="space-y-3 p-5">
            @forelse ($notes as $note)
                @php
                    $supportRequest = $note->supportRequest;
                    $subjectUrl = $supportRequest?->subjectUrl();
                    $cardClass = $supportRequest?->is_priority
                        ? 'border-rose-200 bg-rose-50 shadow-sm ring-1 ring-rose-100'
                        : 'border-indigo-200 bg-white shadow-sm';
                    $badgeClass = $supportRequest?->is_priority
                        ? 'bg-rose-100 text-rose-800'
                        : 'bg-indigo-100 text-indigo-800';
                @endphp

                <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 flex-1 space-y-4">
                            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 shadow-sm">
                                <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-amber-900">{{ __('Personal note') }}</div>
                                <p class="whitespace-pre-line text-sm leading-6 text-gray-900">{{ $note->body }}</p>

                                @if ($supportRequest)
                                    <div class="mt-4">
                                        <div class="mb-1 text-xs font-medium text-gray-500">{{ __('Request linked to this note') }}</div>

                                        <div class="rounded-md border {{ $cardClass }} p-3">
                                            @if ($supportRequest->is_priority)
                                                <div class="min-w-0 space-y-2">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <span class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $badgeClass }}">{{ __('Priority') }}</span>
                                                        <span class="font-semibold text-gray-950">{{ __('Sent by') }} {{ $supportRequest->priorityRequesterDisplayName() }}</span>
                                                    </div>
                                                    @if ($supportRequest->classroom)
                                                        <div class="text-sm text-gray-700">{{ $supportRequest->classroom->name }}</div>
                                                    @endif
                                                </div>
                                            @else
                                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
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
                                                                class="block cursor-pointer rounded-md px-2 py-1.5 text-sm font-medium leading-snug text-gray-700 transition hover:bg-indigo-50 hover:text-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                                                aria-label="{{ __('Open the subject link') }}"
                                                                title="{{ __('Open the subject link') }}"
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
                                                                <span class="whitespace-normal break-words">
                                                                    {{ $supportRequest->subject?->name ?? 'N/A' }} - {{ __('Tile') }} {{ $supportRequest->moodle_tile_number }}
                                                                </span>
                                                            </div>
                                                        @endif

                                                        <div class="flex flex-wrap items-center gap-2 px-2 text-sm text-gray-700">
                                                            @if ($supportRequest->classroom)
                                                                <span class="rounded-full bg-gray-50 px-2.5 py-0.5 font-medium text-gray-700 ring-1 ring-gray-200">{{ $supportRequest->classroom->name }}</span>
                                                            @endif
                                                            @if ($supportRequest->typeLabel() !== '')
                                                                <span class="rounded-full bg-white px-2.5 py-0.5 font-medium text-indigo-700 ring-1 ring-indigo-100">{{ $supportRequest->typeLabel() }}</span>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    @if ($supportRequest->table_number)
                                                        <div class="inline-flex items-center justify-end gap-1.5 self-start rounded-md bg-gray-50 px-2.5 py-1 text-sm font-semibold text-gray-800 ring-1 ring-gray-200" title="{{ __('Table') }} {{ $supportRequest->table_number }}">
                                                            <svg class="h-4 w-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25h6m-7.5 3h9m-12-6.75h15A1.5 1.5 0 0 0 21 12V5.25a1.5 1.5 0 0 0-1.5-1.5h-15A1.5 1.5 0 0 0 3 5.25V12a1.5 1.5 0 0 0 1.5 1.5Z" />
                                                            </svg>
                                                            <span>{{ $supportRequest->table_number }}</span>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="text-xs text-gray-500">{{ $note->created_at->format('Y-m-d H:i') }}</div>
                        </div>

                        <button
                            type="button"
                            wire:click="archive({{ $note->id }})"
                            wire:loading.attr="disabled"
                            wire:target="archive({{ $note->id }})"
                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-gray-400 transition hover:bg-gray-50 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50"
                            aria-label="{{ __('Archive') }}"
                            title="{{ __('Archive') }}"
                        >
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m16.5 0H3.75m16.5 0-1.125-3.375A2.25 2.25 0 0 0 16.99 2.625H7.01a2.25 2.25 0 0 0-2.135 1.5L3.75 7.5m8.25 3v6m0 0 2.25-2.25M12 16.5l-2.25-2.25" />
                            </svg>
                        </button>
                    </div>
                </article>
            @empty
                <div class="p-8 text-center text-sm text-gray-500">
                    {{ __('No personal notes.') }}
                </div>
            @endforelse
        </div>
    </section>

    <details class="group rounded-lg border border-gray-200 bg-white/70 shadow-sm">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 text-sm text-gray-500 transition hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
            <span class="font-medium">{{ __('Archived notes') }}</span>
            <svg class="h-4 w-4 transition group-open:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </summary>

        <div class="border-t border-gray-100 px-5 py-4">
            <div class="space-y-3">
                @forelse ($archivedNotes as $note)
                    @php
                        $supportRequest = $note->supportRequest;
                    @endphp

                    <article class="rounded-md border border-gray-200 bg-gray-50 p-3">
                        <div class="flex gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="whitespace-pre-line text-sm leading-6 text-gray-700">{{ $note->body }}</div>

                                @if ($supportRequest)
                                    <div class="mt-3">
                                        <div class="mb-1 text-xs font-medium text-gray-500">{{ __('Request linked to this note') }}</div>
                                        @include('livewire.teacher.partials.personal-note-request-summary', ['supportRequest' => $supportRequest])
                                    </div>
                                @endif

                                <div class="mt-3 text-xs text-gray-500">
                                    {{ __('Archived') }} {{ $note->archived_at?->format('Y-m-d H:i') }}
                                </div>
                            </div>

                            <button
                                type="button"
                                wire:click="confirmDeleteArchived({{ $note->id }})"
                                wire:loading.attr="disabled"
                                wire:target="confirmDeleteArchived({{ $note->id }})"
                                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-gray-400 transition hover:bg-white hover:text-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2"
                                aria-label="{{ __('Delete permanently') }}"
                                title="{{ __('Delete permanently') }}"
                            >
                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673A2.25 2.25 0 0 1 15.916 21.75H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                            </button>
                        </div>
                    </article>
                @empty
                    <div class="py-3 text-sm text-gray-500">{{ __('No archived notes.') }}</div>
                @endforelse
            </div>

            @if ($archivedNotes->isNotEmpty())
                <div class="mt-4 flex justify-end border-t border-gray-100 pt-4">
                    <x-danger-button type="button" x-data x-on:click="$dispatch('open-modal', 'delete-all-archived-personal-notes')">
                        {{ __('Delete all archived notes') }}
                    </x-danger-button>
                </div>
            @endif
        </div>
    </details>

    <x-modal name="delete-archived-personal-note" maxWidth="md" focusable>
        <x-confirmation-panel
            :title="__('Delete archived note')"
            :message="__('This archived note will be permanently deleted. This action cannot be undone.')"
        >
            <x-slot name="actions">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">
                    {{ __('Back') }}
                </x-secondary-button>

                <x-danger-button type="button" wire:click="deleteArchived" wire:loading.attr="disabled" wire:target="deleteArchived">
                    {{ __('Delete') }}
                </x-danger-button>
            </x-slot>
        </x-confirmation-panel>
    </x-modal>

    <x-modal name="delete-all-archived-personal-notes" maxWidth="md" focusable>
        <x-confirmation-panel
            :title="__('Delete all archived notes')"
            :message="__('All archived personal notes will be permanently deleted. This action cannot be undone.')"
        >
            <x-slot name="actions">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">
                    {{ __('Back') }}
                </x-secondary-button>

                <x-danger-button type="button" wire:click="deleteAllArchived" wire:loading.attr="disabled" wire:target="deleteAllArchived">
                    {{ __('Delete all') }}
                </x-danger-button>
            </x-slot>
        </x-confirmation-panel>
    </x-modal>
</div>
