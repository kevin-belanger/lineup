<x-app-layout>
    <x-slot name="header">
        <x-admin-breadcrumb />
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <a href="{{ route('admin.users.index') }}" class="rounded-md border border-gray-200 p-4 hover:border-indigo-300 hover:bg-indigo-50">
                            <div class="font-medium text-gray-900">{{ __('Users') }}</div>
                            <div class="mt-1 text-sm text-gray-600">{{ __('Approval and roles') }}</div>
                        </a>
                        <a href="{{ route('admin.classrooms.index') }}" class="rounded-md border border-gray-200 p-4 hover:border-indigo-300 hover:bg-indigo-50">
                            <div class="font-medium text-gray-900">{{ __('Rooms') }}</div>
                            <div class="mt-1 text-sm text-gray-600">{{ __('Physical rooms') }}</div>
                        </a>
                        <a href="{{ route('admin.subjects.index') }}" class="rounded-md border border-gray-200 p-4 hover:border-indigo-300 hover:bg-indigo-50">
                            <div class="font-medium text-gray-900">{{ __('Subjects') }}</div>
                            <div class="mt-1 text-sm text-gray-600">{{ __('Course subjects') }}</div>
                        </a>
                        <a href="{{ route('admin.settings.edit') }}" class="rounded-md border border-gray-200 p-4 hover:border-indigo-300 hover:bg-indigo-50">
                            <div class="font-medium text-gray-900">{{ __('Settings') }}</div>
                            <div class="mt-1 text-sm text-gray-600">{{ __('Global configuration') }}</div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
