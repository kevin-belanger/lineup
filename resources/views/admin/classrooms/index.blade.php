<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Locaux') }}
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

            <section class="bg-white shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('admin.classrooms.store') }}" class="grid gap-4 p-6 md:grid-cols-[1fr_2fr_auto_auto] md:items-end">
                    @csrf

                    <div>
                        <x-input-label for="name" :value="__('Nom')" />
                        <x-text-input id="name" name="name" class="mt-1 block w-full" required />
                    </div>

                    <div>
                        <x-input-label for="description" :value="__('Description')" />
                        <x-text-input id="description" name="description" class="mt-1 block w-full" />
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        {{ __('Actif') }}
                    </label>

                    <x-primary-button>
                        {{ __('Creer') }}
                    </x-primary-button>
                </form>
            </section>

            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Local') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Statut') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($classrooms as $classroom)
                                <tr>
                                    <td class="px-4 py-4 align-top">
                                        <form method="POST" action="{{ route('admin.classrooms.update', $classroom) }}" class="grid gap-3 md:grid-cols-[1fr_2fr_auto] md:items-end">
                                            @csrf
                                            @method('PATCH')

                                            <div>
                                                <x-input-label for="classroom-{{ $classroom->id }}-name" :value="__('Nom')" />
                                                <x-text-input id="classroom-{{ $classroom->id }}-name" name="name" value="{{ $classroom->name }}" class="mt-1 block w-full" required />
                                            </div>

                                            <div>
                                                <x-input-label for="classroom-{{ $classroom->id }}-description" :value="__('Description')" />
                                                <x-text-input id="classroom-{{ $classroom->id }}-description" name="description" value="{{ $classroom->description }}" class="mt-1 block w-full" />
                                            </div>

                                            <input type="hidden" name="is_active" value="{{ $classroom->is_active ? '1' : '0' }}">

                                            <x-secondary-button type="submit">
                                                {{ __('Sauvegarder') }}
                                            </x-secondary-button>
                                        </form>
                                    </td>
                                    <td class="px-4 py-4 align-top text-sm">
                                        <span class="{{ $classroom->is_active ? 'text-green-700' : 'text-red-700' }}">
                                            {{ $classroom->is_active ? __('Actif') : __('Inactif') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 align-top text-right">
                                        <div class="flex flex-col items-end gap-2">
                                            <form method="POST" action="{{ route('admin.classrooms.active', $classroom) }}">
                                                @csrf
                                                @method('PATCH')
                                                <x-secondary-button type="submit">
                                                    {{ $classroom->is_active ? __('Desactiver') : __('Activer') }}
                                                </x-secondary-button>
                                            </form>

                                            <x-danger-button type="button" x-data x-on:click="$dispatch('open-modal', 'delete-classroom-{{ $classroom->id }}')">
                                                {{ __('Supprimer') }}
                                            </x-danger-button>

                                            <x-modal name="delete-classroom-{{ $classroom->id }}" maxWidth="md" focusable>
                                                <x-confirmation-panel
                                                    :title="__('Supprimer le local')"
                                                    :message="__('Les demandes associées resteront dans l’historique, mais le local affichera N/A. Voulez-vous supprimer ce local ?')"
                                                >
                                                    <x-slot name="actions">
                                                        <x-secondary-button type="button" x-on:click="$dispatch('close')">
                                                            {{ __('Retour') }}
                                                        </x-secondary-button>

                                                        <form method="POST" action="{{ route('admin.classrooms.destroy', $classroom) }}">
                                                            @csrf
                                                            @method('DELETE')
                                                            <x-danger-button>
                                                                {{ __('Supprimer') }}
                                                            </x-danger-button>
                                                        </form>
                                                    </x-slot>
                                                </x-confirmation-panel>
                                            </x-modal>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500">
                                        {{ __('Aucun local.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-gray-200 px-4 py-3">
                    {{ $classrooms->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
