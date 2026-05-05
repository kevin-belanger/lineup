<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Espace etudiant') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    {{ $errors->first() }}
                </div>
            @endif

            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="text-sm font-medium text-gray-500">{{ __('Local courant') }}</div>
                        <div class="mt-1 text-lg font-semibold text-gray-900">
                            {{ $currentClassroom?->name ?? __('Aucun local choisi') }}
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('student.classroom.edit') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50">
                            {{ __('Changer de local') }}
                        </a>
                        @if ($currentClassroom)
                            <a href="{{ route('student.requests.create') }}" class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700">
                                {{ __('Nouvelle demande') }}
                            </a>
                        @endif
                        <a href="{{ route('student.history') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50">
                            {{ __('Historique') }}
                        </a>
                    </div>
                </div>
            </section>

            <livewire:student.active-requests />
        </div>
    </div>
</x-app-layout>
