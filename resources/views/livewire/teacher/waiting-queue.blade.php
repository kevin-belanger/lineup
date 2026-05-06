<section class="space-y-4 rounded-lg border border-gray-200 bg-white/60 p-4 shadow-sm">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">{{ __('En attente') }}</h3>
            <p class="text-sm text-gray-600">{{ __('Demandes du local courant, plus anciennes en premier.') }}</p>
        </div>
        <span class="rounded-full bg-gray-200 px-3 py-1 text-sm font-medium text-gray-700">{{ $requests->count() }}</span>
    </div>

    <div class="space-y-4">
        @forelse ($requests as $supportRequest)
            @if ($supportRequest->is_priority)
                <article wire:key="waiting-request-{{ $supportRequest->id }}" class="rounded-lg border border-rose-200 bg-rose-50 p-5 shadow-sm ring-1 ring-rose-100">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-rose-800">{{ __('Prioritaire') }}</span>
                                <span class="text-base font-semibold text-gray-900">{{ __('Envoyee par') }} {{ $supportRequest->priorityRequester?->name ?? 'N/A' }}</span>
                            </div>

                            <p class="text-sm text-gray-800">{{ $supportRequest->comment }}</p>

                            <div class="flex flex-wrap gap-2 text-sm">
                                <span class="rounded-full bg-white px-3 py-1 font-medium text-rose-700 ring-1 ring-rose-100">{{ __('Attente') }} {{ $supportRequest->created_at->diffForHumans(null, true) }}</span>
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
                                {{ __('Prendre en charge') }}
                            </button>
                        </div>
                    </div>
                </article>
            @else
            <article wire:key="waiting-request-{{ $supportRequest->id }}" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 space-y-3">
                        <div>
                            <div class="text-base font-semibold text-gray-900">{{ $supportRequest->student?->name ?? 'N/A' }}</div>
                            <div class="mt-1 flex flex-wrap gap-2 text-sm text-gray-600">
                                <span>{{ __('Table') }} {{ $supportRequest->table_number }}</span>
                                <span>{{ __('Matiere') }} : {{ $supportRequest->subject?->name ?? 'N/A' }}</span>
                                <span>{{ __('Tuile Moodle') }} {{ $supportRequest->moodle_tile_number }}</span>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 text-sm">
                            <span class="rounded-full bg-indigo-50 px-3 py-1 font-medium text-indigo-700">{{ $typeLabels[$supportRequest->type] ?? $supportRequest->type }}</span>
                            <span class="rounded-full bg-amber-50 px-3 py-1 font-medium text-amber-700">{{ __('Attente') }} {{ $supportRequest->created_at->diffForHumans(null, true) }}</span>
                        </div>

                        @if ($supportRequest->comment)
                            <p class="text-sm text-gray-700">{{ $supportRequest->comment }}</p>
                        @else
                            <p class="text-sm text-gray-500">{{ __('Aucun commentaire.') }}</p>
                        @endif
                    </div>

                    <div class="flex shrink-0 flex-col gap-2 sm:w-44">
                        <button
                            type="button"
                            wire:click="assign({{ $supportRequest->id }})"
                            wire:loading.attr="disabled"
                            wire:target="assign({{ $supportRequest->id }})"
                            class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50"
                        >
                            {{ __('Prendre en charge') }}
                        </button>

                        <button
                            type="button"
                            wire:click="confirmCancel({{ $supportRequest->id }})"
                            wire:loading.attr="disabled"
                            wire:target="confirmCancel({{ $supportRequest->id }})"
                            class="inline-flex justify-center rounded-md border border-transparent px-4 py-2 text-xs font-semibold uppercase tracking-widest text-red-700 transition hover:bg-red-50 disabled:opacity-50"
                        >
                            {{ __('Annuler') }}
                        </button>
                    </div>
                </div>
            </article>
            @endif
        @empty
            <div class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-gray-600">
                {{ __('Aucune demande en attente pour ce local.') }}
            </div>
        @endforelse
    </div>

    @if ($confirmingCancellationId)
        <div class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6">
            <button type="button" class="absolute inset-0 bg-gray-500 opacity-75" wire:click="dismissCancel" aria-label="{{ __('Retour') }}"></button>

            <x-confirmation-panel
                :title="__('Annuler la demande')"
                :message="__('Cette demande sera annulée et ne sera plus visible dans la file d’attente. Voulez-vous continuer ?')"
            >
                <x-slot name="actions">
                    <x-secondary-button type="button" wire:click="dismissCancel">
                        {{ __('Retour') }}
                    </x-secondary-button>

                    <x-danger-button type="button" wire:click="cancel" wire:loading.attr="disabled" wire:target="cancel">
                        {{ __('Annuler la demande') }}
                    </x-danger-button>
                </x-slot>
            </x-confirmation-panel>
        </div>
    @endif
</section>
