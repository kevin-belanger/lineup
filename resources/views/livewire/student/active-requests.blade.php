<section @if ($requests->isNotEmpty()) wire:poll.3s.visible @endif class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
    <div class="divide-y divide-gray-200">
        @forelse ($requests as $supportRequest)
            <article wire:key="student-active-request-{{ $supportRequest->id }}" class="relative p-6">
                <div class="flex flex-col gap-8 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 space-y-2">
                        <div>
                            <div class="font-medium text-gray-900">
                                <span class="inline-flex items-center gap-1">
                                    <span>{{ $supportRequest->subject?->name ?? 'N/A' }} - {{ $typeLabels[$supportRequest->type] ?? $supportRequest->type }}</span>
                                    <x-subject-request-link :support-request="$supportRequest" />
                                </span>
                            </div>
                            <div class="mt-1 text-sm text-gray-600">
                                {{ __('Moodle tile :tile', ['tile' => $supportRequest->moodle_tile_number]) }}
                                ·
                                {{ __('Table :table', ['table' => $supportRequest->table_number]) }}
                                ·
                                {{ $supportRequest->classroom?->name ?? 'N/A' }}
                            </div>
                        </div>

                        @if ($supportRequest->comment)
                            <div class="text-sm text-gray-500">{{ $supportRequest->comment }}</div>
                        @endif

                        <x-student-request-badges :support-request="$supportRequest" />
                    </div>

                    <div class="flex shrink-0 flex-wrap gap-2 sm:justify-end">
                        @if ($supportRequest->status === 'waiting')
                            <a href="{{ route('student.requests.edit', $supportRequest) }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50">
                                {{ __('Edit') }}
                            </a>

                            <button
                                type="button"
                                wire:click="cancel({{ $supportRequest->id }})"
                                wire:loading.attr="disabled"
                                wire:target="cancel({{ $supportRequest->id }})"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:opacity-50"
                            >
                                {{ __('Cancel') }}
                            </button>
                        @endif

                        @if ($supportRequest->status === 'paused')
                            <form method="POST" action="{{ route('student.requests.ready', $supportRequest) }}">
                                @csrf
                                @method('PATCH')
                                <x-primary-button>
                                    {{ __('Ready to review') }}
                                </x-primary-button>
                            </form>
                        @endif
                    </div>

                    @if ($supportRequest->assigned_teacher_id)
                        <button
                            type="button"
                            wire:click="confirmAssignedCancellation({{ $supportRequest->id }})"
                            wire:loading.attr="disabled"
                            wire:target="confirmAssignedCancellation({{ $supportRequest->id }})"
                            class="absolute bottom-3 right-3 inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-300 transition hover:bg-red-50 hover:text-red-500 focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-2 disabled:opacity-50"
                            aria-label="{{ __('Cancel request') }}"
                        >
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673A2.25 2.25 0 0 1 15.916 21H8.084a2.25 2.25 0 0 1-2.244-1.327L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                        </button>
                    @endif
                </div>
            </article>
        @empty
            <div class="px-6 py-8 text-center text-sm text-gray-500">
                {{ __('No active requests.') }}
            </div>
        @endforelse
    </div>

    @if ($confirmingAssignedCancellationId)
        <div class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6">
            <button type="button" class="absolute inset-0 bg-gray-500 opacity-75" wire:click="dismissAssignedCancellation" aria-label="{{ __('Back') }}"></button>

            <x-confirmation-panel
                :title="__('Cancel request')"
                :message="__('This request has already been taken by a teacher. Do you really want to cancel it?')"
            >
                <x-slot name="actions">
                    <x-secondary-button type="button" wire:click="dismissAssignedCancellation">
                        {{ __('Back') }}
                    </x-secondary-button>

                    <x-danger-button type="button" wire:click="cancelAssignedRequest" wire:loading.attr="disabled" wire:target="cancelAssignedRequest">
                        {{ __('Cancel my request') }}
                    </x-danger-button>
                </x-slot>
            </x-confirmation-panel>
        </div>
    @endif
</section>
