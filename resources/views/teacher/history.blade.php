<x-app-layout>
    <x-slot name="header">
        <x-teacher-breadcrumb :classroom-name="$currentClassroom->name" history />
    </x-slot>

    <div class="py-8">
        <div class="mx-auto mb-6 flex max-w-7xl justify-end px-4 sm:px-6 lg:px-8">
            <a href="{{ route('teacher.dashboard') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50">
                {{ __('Back to requests') }}
            </a>
        </div>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <livewire:teacher.request-history :key="'teacher-request-history'" />
        </div>
    </div>
</x-app-layout>
