<section class="rounded-lg border border-gray-200 bg-white/70 shadow-sm">
    <details class="group">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-700">{{ __('Other teachers') }}</h3>
                <p class="text-xs text-gray-500">{{ __('Requests assigned in this room, occasional management.') }}</p>
            </div>

            <div class="flex items-center gap-3">
                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">{{ $requests->count() }}</span>
                <span class="text-sm text-gray-500 group-open:hidden">{{ __('Open') }}</span>
                <span class="hidden text-sm text-gray-500 group-open:inline">{{ __('Close') }}</span>
            </div>
        </summary>

        <div class="border-t border-gray-100 px-4 py-3">
            <div class="space-y-2">
                @forelse ($requests as $supportRequest)
                    @php
                        $badgeClass = match ($supportRequest->status) {
                            \App\Models\SupportRequest::STATUS_READY => 'bg-amber-100 text-amber-800',
                            \App\Models\SupportRequest::STATUS_PAUSED => 'bg-sky-100 text-sky-800',
                            default => 'bg-gray-100 text-gray-700',
                        };
                    @endphp

                    <article wire:key="other-teacher-request-{{ $supportRequest->id }}" wire:transition="teacher-other-request-{{ $supportRequest->id }}" class="relative rounded-md border border-gray-100 bg-gray-50 p-3 pb-9">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-semibold text-gray-800">
                                    {{ $supportRequest->is_priority ? __('Priority') : ($supportRequest->student?->fullName() ?? 'N/A') }}
                                </div>
                                <div class="mt-1 text-sm text-gray-600">
                                    @if ($supportRequest->is_priority)
                                        {{ __('Sent by') }} {{ $supportRequest->priorityRequester?->fullName() ?? 'N/A' }}
                                    @else
                                        <span class="inline-flex items-center gap-1">
                                            <span>{{ $supportRequest->subject?->name ?? 'N/A' }}</span>
                                            <x-subject-request-link :support-request="$supportRequest" />
                                        </span>
                                        -
                                        {{ __('Table') }} {{ $supportRequest->table_number }}
                                    @endif
                                </div>
                                <div class="mt-1 text-xs text-gray-500">
                                    {{ __('With') }} {{ $supportRequest->assignedTeacher?->fullName() ?? 'N/A' }}
                                </div>
                            </div>

                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium {{ $badgeClass }}">
                                {{ $supportRequest->status === \App\Models\SupportRequest::STATUS_READY ? __('Ready to review') : ($statusLabels[$supportRequest->status] ?? $supportRequest->status) }}
                            </span>
                        </div>

                        @if (! $supportRequest->is_priority)
                            <button
                                type="button"
                                wire:click="openManagementModal({{ $supportRequest->id }})"
                                wire:loading.attr="disabled"
                                wire:target="openManagementModal({{ $supportRequest->id }})"
                                class="absolute bottom-2 right-2 inline-flex h-7 w-7 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50"
                                aria-label="{{ __('Manage request') }}"
                                title="{{ __('Manage request') }}"
                            >
                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.66.841.081.036.162.074.241.114.337.17.738.149 1.054-.057l1.084-.705a1.125 1.125 0 0 1 1.45.12l1.833 1.833c.389.389.44.997.12 1.45l-.705 1.084c-.206.316-.227.717-.057 1.054.04.079.078.16.114.241.155.347.467.597.841.66l1.281.213c.542.09.94.56.94 1.11v2.593c0 .55-.398 1.02-.94 1.11l-1.281.213a1.125 1.125 0 0 0-.841.66 6.78 6.78 0 0 1-.114.241c-.17.337-.149.738.057 1.054l.705 1.084c.32.453.269 1.061-.12 1.45l-1.833 1.833a1.125 1.125 0 0 1-1.45.12l-1.084-.705c-.316-.206-.717-.227-1.054-.057a6.78 6.78 0 0 1-.241.114c-.347.155-.597.467-.66.841l-.213 1.281c-.09.542-.56.94-1.11.94h-2.593c-.55 0-1.02-.398-1.11-.94l-.213-1.281a1.125 1.125 0 0 0-.66-.841 6.78 6.78 0 0 1-.241-.114c-.337-.17-.738-.149-1.054.057l-1.084.705a1.125 1.125 0 0 1-1.45-.12l-1.833-1.833a1.125 1.125 0 0 1-.12-1.45l.705-1.084c.206-.316.227-.717.057-1.054a6.78 6.78 0 0 1-.114-.241 1.125 1.125 0 0 0-.841-.66l-1.281-.213a1.125 1.125 0 0 1-.94-1.11v-2.593c0-.55.398-1.02.94-1.11l1.281-.213c.374-.063.686-.313.841-.66.036-.081.074-.162.114-.241.17-.337.149-.738-.057-1.054l-.705-1.084a1.125 1.125 0 0 1 .12-1.45l1.833-1.833a1.125 1.125 0 0 1 1.45-.12l1.084.705c.316.206.717.227 1.054.057.079-.04.16-.078.241-.114.347-.155.597-.467.66-.841l.213-1.281Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                            </button>
                        @endif
                    </article>
                @empty
                    <div class="rounded-md border border-dashed border-gray-200 bg-gray-50 p-5 text-center text-sm text-gray-500">
                        {{ __('No requests assigned to other teachers.') }}
                    </div>
                @endforelse
            </div>
        </div>
    </details>

    @if ($managedRequest)
        <div class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6">
            <button type="button" class="absolute inset-0 bg-gray-500 opacity-75" wire:click="closeManagementModal" aria-label="{{ __('Close') }}"></button>

            <div class="relative w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Manage request') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ __('One-time action on a request assigned to another teacher.') }}</p>
                </div>

                <div class="mt-5 grid gap-3 rounded-md border border-gray-100 bg-gray-50 p-4 text-sm sm:grid-cols-2">
                    <div>
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('Student') }}</div>
                        <div class="mt-1 font-semibold text-gray-900">{{ $managedRequest->student?->fullName() ?? 'N/A' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('Teacher') }}</div>
                        <div class="mt-1 font-semibold text-gray-900">{{ $managedRequest->assignedTeacher?->fullName() ?? 'N/A' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('Subject') }}</div>
                        <div class="mt-1 inline-flex items-center gap-1 text-gray-800">
                            <span>{{ $managedRequest->subject?->name ?? 'N/A' }}</span>
                            <x-subject-request-link :support-request="$managedRequest" />
                        </div>
                    </div>
                    <div>
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('Status') }}</div>
                        <div class="mt-1 text-gray-800">{{ $managedRequest->status === \App\Models\SupportRequest::STATUS_READY ? __('Ready to review') : ($statusLabels[$managedRequest->status] ?? $managedRequest->status) }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('Table') }}</div>
                        <div class="mt-1 text-gray-800">{{ $managedRequest->table_number }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('Moodle tile') }}</div>
                        <div class="mt-1 text-gray-800">{{ $managedRequest->moodle_tile_number }}</div>
                    </div>
                    @if ($managedRequest->typeLabel() !== '')
                        <div class="sm:col-span-2">
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('Type') }}</div>
                            <div class="mt-1 text-gray-800">{{ $managedRequest->typeLabel() }}</div>
                        </div>
                    @endif
                    <div class="sm:col-span-2">
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('Comment') }}</div>
                        <div class="mt-1 text-gray-800">{{ $managedRequest->comment ?: __('No comment.') }}</div>
                    </div>
                </div>

                <div class="mt-6 grid gap-2 sm:grid-cols-3">
                    <button type="button" wire:click="requeue" wire:loading.attr="disabled" wire:target="requeue" class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:opacity-50">
                        {{ __('Return request to waiting') }}
                    </button>

                    <button type="button" wire:click="complete" wire:loading.attr="disabled" wire:target="complete" class="inline-flex justify-center rounded-md border border-transparent bg-gray-800 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700 disabled:opacity-50">
                        {{ __('Mark request as completed') }}
                    </button>

                    <button type="button" wire:click="cancel" wire:loading.attr="disabled" wire:target="cancel" class="inline-flex justify-center rounded-md border border-transparent bg-red-600 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-red-500 disabled:opacity-50">
                        {{ __('Cancel request') }}
                    </button>
                </div>

                <div class="mt-4 flex justify-end">
                    <x-secondary-button type="button" wire:click="closeManagementModal">
                        {{ __('Close') }}
                    </x-secondary-button>
                </div>
            </div>
        </div>
    @endif
</section>
