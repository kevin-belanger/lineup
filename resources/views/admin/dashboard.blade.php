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
                            <div class="font-medium text-gray-900">{{ __('Utilisateurs') }}</div>
                            <div class="mt-1 text-sm text-gray-600">{{ __('Approbation et roles') }}</div>
                        </a>
                        <a href="{{ route('admin.classrooms.index') }}" class="rounded-md border border-gray-200 p-4 hover:border-indigo-300 hover:bg-indigo-50">
                            <div class="font-medium text-gray-900">{{ __('Locaux') }}</div>
                            <div class="mt-1 text-sm text-gray-600">{{ __('Classes physiques') }}</div>
                        </a>
                        <a href="{{ route('admin.subjects.index') }}" class="rounded-md border border-gray-200 p-4 hover:border-indigo-300 hover:bg-indigo-50">
                            <div class="font-medium text-gray-900">{{ __('Matieres') }}</div>
                            <div class="mt-1 text-sm text-gray-600">{{ __('Domaines de cours') }}</div>
                        </a>
                        <a href="{{ route('admin.settings.edit') }}" class="rounded-md border border-gray-200 p-4 hover:border-indigo-300 hover:bg-indigo-50">
                            <div class="font-medium text-gray-900">{{ __('Paramètres') }}</div>
                            <div class="mt-1 text-sm text-gray-600">{{ __('Configuration globale') }}</div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
