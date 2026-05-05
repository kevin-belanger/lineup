<section @if ($requests->isNotEmpty()) wire:poll.3s.visible @endif class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
    @if ($notice)
        <div class="border-b border-gray-200 bg-green-50 px-6 py-3 text-sm text-green-800">
            {{ $notice }}
        </div>
    @endif

    <div class="divide-y divide-gray-200">
        @forelse ($requests as $supportRequest)
            <article wire:key="student-active-request-{{ $supportRequest->id }}" class="p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 space-y-2">
                        <div>
                            <div class="font-medium text-gray-900">
                                {{ $supportRequest->subject?->name ?? 'N/A' }} - {{ $typeLabels[$supportRequest->type] ?? $supportRequest->type }}
                            </div>
                            <div class="mt-1 text-sm text-gray-600">
                                {{ __('Tuile Moodle :tile', ['tile' => $supportRequest->moodle_tile_number]) }}
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
                                {{ __('Modifier') }}
                            </a>

                            <button
                                type="button"
                                wire:click="cancel({{ $supportRequest->id }})"
                                wire:loading.attr="disabled"
                                wire:target="cancel({{ $supportRequest->id }})"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:opacity-50"
                            >
                                {{ __('Annuler') }}
                            </button>
                        @endif

                        @if ($supportRequest->status === 'paused')
                            <form method="POST" action="{{ route('student.requests.ready', $supportRequest) }}">
                                @csrf
                                @method('PATCH')
                                <x-primary-button>
                                    {{ __('Je suis pret') }}
                                </x-primary-button>
                            </form>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="px-6 py-8 text-center text-sm text-gray-500">
                {{ __('Aucune demande active.') }}
            </div>
        @endforelse
    </div>
</section>
