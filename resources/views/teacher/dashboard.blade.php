<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ __('Espace enseignant') }}
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    {{ __('Local courant') }} : <span class="font-medium text-gray-900">{{ $currentClassroom->name }}</span>
                </p>
            </div>

            <a href="{{ route('teacher.classroom.edit') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50">
                {{ __('Changer de local') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <livewire:teacher.request-change-watcher />

        <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[minmax(360px,0.95fr)_minmax(0,1.05fr)] lg:items-start lg:px-8">
            <div>
                <livewire:teacher.my-requests />
            </div>

            <div class="space-y-5">
                <livewire:teacher.waiting-queue />
                <livewire:teacher.other-teacher-requests />
            </div>
        </div>
    </div>
</x-app-layout>
