<x-app-layout>
    <x-slot name="header">
        <x-admin-breadcrumb :current="__('Statistics')" />
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <livewire:admin.request-statistics />
        </div>
    </div>
</x-app-layout>
