<div wire:init="updatePageTitle">
    <livewire:teacher.request-change-watcher />

    <div class="mx-auto mb-6 flex max-w-7xl flex-col gap-3 px-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
        <div>
            <div class="flex items-center gap-1.5 text-sm font-medium text-gray-500">
                <span>{{ $currentClassroom?->name ?? __('No room selected') }}</span>
                @if ($currentClassroom)
                    <x-classroom-opening-status :classroom="$currentClassroom" live show-closed-until />
                @endif
            </div>
        </div>

        <a href="{{ route('teacher.history') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50">
            {{ __('View history') }}
        </a>
    </div>

    <div wire:key="teacher-dashboard-requests-view" class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[minmax(360px,0.95fr)_minmax(0,1.05fr)] lg:items-start lg:px-8">
        <div class="space-y-5">
            <livewire:teacher.my-requests :key="'teacher-my-requests'" />
            <livewire:teacher.other-teacher-requests :key="'teacher-other-requests'" />
        </div>

        <div class="space-y-5">
            <livewire:teacher.waiting-queue :key="'teacher-waiting-queue'" />
        </div>
    </div>
</div>
