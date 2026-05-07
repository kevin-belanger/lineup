<section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
    <div class="flex flex-col gap-4 border-b border-gray-100 pb-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">{{ __('Historique') }}</h3>
            <p class="mt-1 text-sm text-gray-600">{{ __('Demandes du local courant selon les filtres sélectionnés.') }}</p>
        </div>

        <span class="inline-flex w-fit rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700">
            {{ trans_choice('{0} Aucune demande affichée|{1} 1 demande affichée|[2,*] :count demandes affichées', $requests->total(), ['count' => $requests->total()]) }}
        </span>
    </div>

    <div class="grid gap-3 border-b border-gray-100 py-4 md:grid-cols-2 xl:grid-cols-[0.85fr_1.25fr_0.9fr_1.35fr]">
        <div>
            <x-input-label for="history-period" :value="__('Période')" />
            <select id="history-period" wire:model.live="period" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="today">{{ __('Aujourd’hui') }}</option>
                <option value="all">{{ __('Toutes les demandes') }}</option>
                <option value="custom">{{ __('Date ou plage de dates') }}</option>
            </select>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <x-input-label for="history-start-date" :value="__('Date de début')" />
                <input id="history-start-date" type="date" wire:model.live="startDate" @disabled($period !== 'custom') class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-500">
            </div>

            <div>
                <x-input-label for="history-end-date" :value="__('Date de fin')" />
                <input id="history-end-date" type="date" wire:model.live="endDate" @disabled($period !== 'custom') class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-500">
            </div>
        </div>

        <div>
            <x-input-label for="history-teacher" :value="__('Prises en charge par')" />
            <select id="history-teacher" wire:model.live="teacherFilter" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="all">{{ __('Tous les enseignants') }}</option>
                <option value="mine">{{ __('Mes demandes') }}</option>
                @foreach ($teacherOptions as $teacher)
                    <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <x-input-label for="history-search" :value="__('Recherche')" />
            <x-text-input id="history-search" type="search" wire:model.live.debounce.300ms="search" class="mt-1 block w-full text-sm" placeholder="{{ __('Nom, matière, commentaire, statut...') }}" />
        </div>
    </div>

    <div class="divide-y divide-gray-100">
        @forelse ($requests as $supportRequest)
            @php
                $statusClass = match ($supportRequest->status) {
                    \App\Models\SupportRequest::STATUS_COMPLETED => 'bg-green-50 text-green-800 ring-green-100',
                    \App\Models\SupportRequest::STATUS_CANCELLED => 'bg-red-50 text-red-700 ring-red-100',
                    \App\Models\SupportRequest::STATUS_READY => 'bg-amber-50 text-amber-800 ring-amber-100',
                    \App\Models\SupportRequest::STATUS_PAUSED => 'bg-sky-50 text-sky-800 ring-sky-100',
                    default => 'bg-gray-50 text-gray-700 ring-gray-200',
                };
                $createdAt = $supportRequest->created_at->timezone($timezone);
                $assignedAt = $supportRequest->assigned_at?->timezone($timezone);
                $completedAt = $supportRequest->completed_at?->timezone($timezone);
                $waitDuration = $supportRequest->assigned_at
                    ? $supportRequest->created_at->diffForHumans($supportRequest->assigned_at, true)
                    : $supportRequest->created_at->diffForHumans(null, true);
                $serviceDuration = $supportRequest->assigned_at && $supportRequest->completed_at
                    ? $supportRequest->assigned_at->diffForHumans($supportRequest->completed_at, true)
                    : null;
            @endphp

            <article wire:key="teacher-history-request-{{ $supportRequest->id }}" class="py-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="font-semibold text-gray-900">
                                @if ($supportRequest->is_priority)
                                    {{ __('Demande prioritaire') }}
                                @else
                                    {{ $supportRequest->student?->name ?? 'N/A' }}
                                @endif
                            </div>

                            <span class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 {{ $statusClass }}">
                                {{ $statusLabels[$supportRequest->status] ?? $supportRequest->status }}
                            </span>

                            @if ($supportRequest->is_priority)
                                <span class="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700 ring-1 ring-rose-100">{{ __('Prioritaire') }}</span>
                            @endif
                        </div>

                        <div class="flex flex-wrap gap-x-3 gap-y-1 text-sm text-gray-600">
                            @if ($supportRequest->is_priority)
                                <span>{{ __('Envoyée par') }} {{ $supportRequest->priorityRequester?->name ?? 'N/A' }}</span>
                            @else
                                <span class="inline-flex items-center gap-1">
                                    <span>{{ $supportRequest->subject?->name ?? 'N/A' }} - {{ __('Tuile') }} {{ $supportRequest->moodle_tile_number }}</span>
                                    <x-subject-request-link :support-request="$supportRequest" />
                                </span>
                                <span>{{ __('Table') }} {{ $supportRequest->table_number }}</span>
                                <span>{{ $typeLabels[$supportRequest->type] ?? $supportRequest->type }}</span>
                            @endif
                        </div>

                        @if ($supportRequest->comment)
                            <p class="text-sm text-gray-700">{{ $supportRequest->comment }}</p>
                        @endif

                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                            <span>{{ __('Créée') }} {{ $createdAt->format('Y-m-d H:i') }}</span>
                            @if ($assignedAt)
                                <span>{{ __('Prise en charge') }} {{ $assignedAt->format('Y-m-d H:i') }}</span>
                            @endif
                            @if ($completedAt)
                                <span>{{ __('Complétée') }} {{ $completedAt->format('Y-m-d H:i') }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-col gap-1 text-sm text-gray-600 lg:w-56 lg:text-right">
                        <div>
                            <span class="font-medium text-gray-800">{{ __('Enseignant') }}</span>
                            <span>{{ $supportRequest->assignedTeacher?->name ?? 'N/A' }}</span>
                        </div>
                        <div>{{ __('Attente') }} {{ $waitDuration }}</div>
                        @if ($serviceDuration)
                            <div>{{ __('Durée') }} {{ $serviceDuration }}</div>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="py-8 text-center text-sm text-gray-500">
                {{ __('Aucune demande ne correspond aux filtres.') }}
            </div>
        @endforelse
    </div>

    @if ($requests->hasPages())
        <div class="border-t border-gray-100 pt-4">
            {{ $requests->links() }}
        </div>
    @endif
</section>
