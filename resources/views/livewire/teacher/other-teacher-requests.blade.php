<section class="rounded-lg border border-gray-200 bg-white/70 shadow-sm">
    <details class="group">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-700">{{ __('Autres enseignants') }}</h3>
                <p class="text-xs text-gray-500">{{ __('Demandes du meme local en lecture seule.') }}</p>
            </div>

            <div class="flex items-center gap-3">
                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">{{ $requests->count() }}</span>
                <span class="text-sm text-gray-500 group-open:hidden">{{ __('Ouvrir') }}</span>
                <span class="hidden text-sm text-gray-500 group-open:inline">{{ __('Fermer') }}</span>
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

                    <article wire:key="other-teacher-request-{{ $supportRequest->id }}" class="rounded-md border border-gray-100 bg-gray-50 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-semibold text-gray-800">
                                    {{ $supportRequest->student?->name ?? 'N/A' }}
                                </div>
                                <div class="mt-1 text-sm text-gray-600">
                                    {{ $supportRequest->subject?->name ?? 'N/A' }} -
                                    {{ __('Table') }} {{ $supportRequest->table_number }}
                                </div>
                                <div class="mt-1 text-xs text-gray-500">
                                    {{ __('Avec') }} {{ $supportRequest->assignedTeacher?->name ?? 'N/A' }}
                                </div>
                            </div>

                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium {{ $badgeClass }}">
                                {{ $supportRequest->status === \App\Models\SupportRequest::STATUS_READY ? __('Prêt à revoir') : ($statusLabels[$supportRequest->status] ?? $supportRequest->status) }}
                            </span>
                        </div>
                    </article>
                @empty
                    <div class="rounded-md border border-dashed border-gray-200 bg-gray-50 p-5 text-center text-sm text-gray-500">
                        {{ __('Aucune demande assignee aux autres enseignants.') }}
                    </div>
                @endforelse
            </div>
        </div>
    </details>
</section>
