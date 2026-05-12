<x-app-layout>
    <x-slot name="header">
        <div class="text-sm font-semibold text-gray-800">
            {{ __('Priority requests') }}
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <livewire:teacher.priority-requests />
        </div>
    </div>
</x-app-layout>
