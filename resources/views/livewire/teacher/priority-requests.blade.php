<div class="grid gap-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
    <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">{{ __('Nouvelle demande prioritaire') }}</h3>
            <p class="mt-1 text-sm text-gray-600">{{ __('Demander rapidement l aide d un autre enseignant dans un local cible.') }}</p>
        </div>

        <form wire:submit="create" wire:key="priority-request-form-{{ $formResetKey }}" class="mt-6 space-y-5">
            <div>
                <x-input-label for="classroomId" :value="__('Local cible')" />
                <select id="classroomId" wire:model="classroomId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('Choisir un local') }}</option>
                    @foreach ($classrooms as $classroom)
                        <option value="{{ $classroom->id }}">{{ $classroom->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('classroomId')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="message" :value="__('Message')" />
                <textarea id="message" wire:model="message" rows="5" maxlength="500" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                <x-input-error :messages="$errors->get('message')" class="mt-2" />
            </div>

            <x-primary-button wire:loading.attr="disabled" wire:target="create">
                {{ __('Envoyer la demande') }}
            </x-primary-button>
        </form>
    </section>

    <section @if ($requests->isNotEmpty()) wire:poll.2s.visible="checkForPriorityRequestChanges" @endif class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <div class="mb-5 flex items-start justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">{{ __('Demandes envoyees') }}</h3>
                <p class="mt-1 text-sm text-gray-600">{{ __('Demandes prioritaires actives creees par toi.') }}</p>
            </div>
            <span class="rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700">{{ $requests->count() }}</span>
        </div>

        <div class="space-y-3">
            @forelse ($requests as $supportRequest)
                <article wire:key="sent-priority-request-{{ $supportRequest->id }}" class="rounded-lg border border-rose-200 bg-rose-50/70 p-4 shadow-sm">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-rose-800">{{ __('Prioritaire') }}</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $supportRequest->classroom?->name ?? 'N/A' }}</span>
                            </div>

                            <p class="text-sm text-gray-800">{{ $supportRequest->comment }}</p>

                            <div class="flex flex-wrap gap-2 text-xs font-medium">
                                <span class="rounded-full bg-white px-2.5 py-1 text-gray-700 ring-1 ring-rose-100">{{ $statusLabels[$supportRequest->status] ?? $supportRequest->status }}</span>
                                <span class="rounded-full bg-white px-2.5 py-1 text-gray-700 ring-1 ring-rose-100">{{ __('Depuis') }} {{ $supportRequest->created_at->diffForHumans(null, true) }}</span>
                                @if ($supportRequest->assignedTeacher)
                                    <span class="rounded-full bg-white px-2.5 py-1 text-gray-700 ring-1 ring-rose-100">{{ __('Pris par') }} {{ $supportRequest->assignedTeacher->name }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-col gap-2 sm:w-40">
                            <button type="button" wire:click="complete({{ $supportRequest->id }})" wire:loading.attr="disabled" wire:target="complete({{ $supportRequest->id }})" class="inline-flex justify-center rounded-md border border-transparent bg-gray-800 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700 disabled:opacity-50">
                                {{ __('Terminer') }}
                            </button>
                            <button type="button" wire:click="cancel({{ $supportRequest->id }})" wire:loading.attr="disabled" wire:target="cancel({{ $supportRequest->id }})" class="inline-flex justify-center rounded-md border border-transparent px-3 py-2 text-xs font-semibold uppercase tracking-widest text-red-700 transition hover:bg-red-50 disabled:opacity-50">
                                {{ __('Annuler') }}
                            </button>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-sm text-gray-600">
                    {{ __('Aucune demande prioritaire active.') }}
                </div>
            @endforelse
        </div>
    </section>
</div>
