@props(['classroom'])

@php
    $openingHours = app(\App\Services\ClassroomOpeningHours::class);
    $classroom->loadMissing('openingHours');
    $isOpen = $openingHours->isOpen($classroom);
    $hasSchedule = $openingHours->hasSchedule($classroom);
    $modalName = 'classroom-opening-hours-'.$classroom->id;
@endphp

<span class="inline-flex items-center" x-data>
    <button
        type="button"
        x-on:click="$dispatch('open-modal', '{{ $modalName }}')"
        class="inline-flex h-5 w-5 items-center justify-center rounded-full transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
        aria-label="{{ $isOpen ? __('Room open') : __('Room closed') }}"
        title="{{ $isOpen ? __('Room open') : __('Room closed') }}"
    >
        <span class="h-2.5 w-2.5 rounded-full {{ $isOpen ? 'bg-emerald-500 ring-2 ring-emerald-100' : 'bg-rose-500 ring-2 ring-rose-100' }}"></span>
    </button>

    <template x-teleport="body">
        <x-modal :name="$modalName" maxWidth="md" focusable>
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900">{{ __('Opening hours') }}</h3>
                <p class="mt-1 text-sm text-gray-600">{{ $classroom->name }}</p>

                <div class="mt-5 space-y-3">
                    @if (! $hasSchedule)
                        <p class="rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                            {{ __('This room is always open.') }}
                        </p>
                    @else
                        @foreach ($classroom->openingHours as $openingHour)
                            <div class="rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm">
                                <div class="font-medium text-gray-900">{{ $openingHours->dayLabels($openingHour->days ?? []) }}</div>
                                <div class="mt-1 text-gray-600">
                                    {{ substr((string) $openingHour->opens_at, 0, 5) }} - {{ substr((string) $openingHour->closes_at, 0, 5) }}
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>

                <div class="mt-6 flex justify-end">
                    <x-secondary-button type="button" x-on:click="$dispatch('close')">
                        {{ __('Close') }}
                    </x-secondary-button>
                </div>
            </div>
        </x-modal>
    </template>
</span>
