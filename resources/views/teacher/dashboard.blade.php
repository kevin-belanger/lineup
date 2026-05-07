<x-app-layout>
    <x-slot name="header">
        <x-teacher-breadcrumb :classroom-name="$currentClassroom->name" />
    </x-slot>

    <div class="py-8">
        <livewire:teacher.dashboard-view />
    </div>
</x-app-layout>
