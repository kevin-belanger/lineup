<section class="rounded-lg border border-indigo-200 bg-indigo-50/50 p-4 shadow-sm">
    <div class="mb-4 flex items-start justify-between gap-3">
        <div>
            <h3 class="text-lg font-semibold text-gray-950">{{ __('Mes demandes en cours') }}</h3>
            <p class="text-sm text-gray-600">{{ __('Prises en charge dans ce local.') }}</p>
        </div>
        <span class="rounded-full bg-indigo-100 px-3 py-1 text-sm font-semibold text-indigo-800">{{ $requests->count() }}</span>
    </div>

    <div class="space-y-3">
        @forelse ($requests as $supportRequest)
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

            <article wire:key="my-request-{{ $supportRequest->id }}" class="rounded-lg border {{ $cardClass }} p-4">
                <div class="space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-semibold text-gray-950">{{ $supportRequest->student?->name ?? 'N/A' }}</div>
                            <div class="mt-1 text-sm text-gray-600">
                                {{ __('Table') }} {{ $supportRequest->table_number }} -
                                {{ $supportRequest->subject?->name ?? 'N/A' }} -
                                {{ __('Tuile') }} {{ $supportRequest->moodle_tile_number }}
                            </div>
                        </div>

                        <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $badgeClass }}">
                            {{ $supportRequest->status === \App\Models\SupportRequest::STATUS_READY ? __('Prêt à revoir') : ($statusLabels[$supportRequest->status] ?? $supportRequest->status) }}
                        </span>
                    </div>

                    <div class="flex flex-wrap gap-2 text-sm">
                        <span class="rounded-full bg-white px-3 py-1 font-medium text-indigo-700 ring-1 ring-indigo-100">{{ $typeLabels[$supportRequest->type] ?? $supportRequest->type }}</span>
                        <span class="rounded-full bg-white px-3 py-1 font-medium text-gray-700 ring-1 ring-gray-200">{{ __('Depuis') }} {{ ($supportRequest->assigned_at ?? $supportRequest->created_at)->diffForHumans(null, true) }}</span>
                    </div>

                    @if ($supportRequest->comment)
                        <p class="text-sm text-gray-700">{{ $supportRequest->comment }}</p>
                    @endif

                    <div class="grid gap-2 sm:grid-cols-3">
                        <button type="button" wire:click="complete({{ $supportRequest->id }})" wire:loading.attr="disabled" wire:target="complete({{ $supportRequest->id }})" class="inline-flex justify-center rounded-md border border-transparent bg-gray-800 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700 disabled:opacity-50">
                            {{ __('Terminer') }}
                        </button>

                        @if ($supportRequest->status !== \App\Models\SupportRequest::STATUS_PAUSED)
                            <button type="button" wire:click="pause({{ $supportRequest->id }})" wire:loading.attr="disabled" wire:target="pause({{ $supportRequest->id }})" class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:opacity-50">
                                {{ __('Mettre en pause') }}
                            </button>
                        @endif

                        <button type="button" wire:click="unassign({{ $supportRequest->id }})" wire:loading.attr="disabled" wire:target="unassign({{ $supportRequest->id }})" class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:opacity-50">
                            {{ __('Remettre dans la file') }}
                        </button>
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-lg border border-dashed border-indigo-200 bg-white/70 p-6 text-center text-sm text-gray-600">
                {{ __('Tu n as aucune demande en cours.') }}
            </div>
        @endforelse
    </div>
</section>
