<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Locaux') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <details>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-6 py-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ __('Creer un nouveau local') }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ __('Ajouter un local disponible dans l application.') }}</p>
                        </div>
                        <span class="text-sm font-medium text-indigo-700">{{ __('Ouvrir') }}</span>
                    </summary>

                    <form
                        method="POST"
                        action="{{ route('admin.classrooms.store') }}"
                        class="space-y-4 border-t border-gray-100 p-6"
                        x-data="{
                            name: '',
                            nameError: '',
                            classrooms: @js($classroomValidationOptions),
                            normalize(value) {
                                return value.trim().toLowerCase();
                            },
                            validateName() {
                                this.nameError = '';

                                if (this.name.trim() === '') {
                                    return true;
                                }

                                if (this.classrooms.includes(this.normalize(this.name))) {
                                    this.nameError = 'Un local avec ce nom existe deja.';

                                    return false;
                                }

                                return true;
                            },
                        }"
                        x-on:submit="if (! validateName()) $event.preventDefault()"
                    >
                        @csrf

                        <div class="grid gap-4 md:grid-cols-[1fr_2fr] md:items-end">
                            <div>
                                <x-input-label for="name" :value="__('Nom')" />
                                <x-text-input id="name" name="name" x-model="name" x-on:input="validateName()" class="mt-1 block w-full" required />
                                <p x-show="nameError" x-text="nameError" class="mt-2 text-sm text-red-600"></p>
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="description" :value="__('Description')" />
                                <x-text-input id="description" name="description" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('description')" class="mt-2" />
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-4">
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                {{ __('Actif') }}
                            </label>

                            <x-primary-button x-bind:disabled="nameError !== ''" class="disabled:opacity-50">
                                {{ __('Creer') }}
                            </x-primary-button>
                        </div>
                    </form>
                </details>
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
                        <tbody x-data="{ editingClassroom: null }" class="divide-y divide-gray-200 bg-white">
                            @forelse ($classrooms as $classroom)
                                <tr x-show="editingClassroom !== {{ $classroom->id }}">
                                    <td class="px-4 py-4 align-top">
                                        <div class="space-y-2">
                                            <div class="font-semibold text-gray-900">{{ $classroom->name }}</div>
                                            @if ($classroom->description)
                                                <div class="text-sm text-gray-600">{{ $classroom->description }}</div>
                                            @else
                                                <div class="text-sm text-gray-400">{{ __('Aucune description.') }}</div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 align-top text-sm">
                                        <span class="{{ $classroom->is_active ? 'text-green-700' : 'text-red-700' }}">
                                            {{ $classroom->is_active ? __('Actif') : __('Inactif') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 align-top text-right">
                                        <div class="flex flex-col items-end gap-2">
                                            <x-secondary-button type="button" x-on:click="editingClassroom = {{ $classroom->id }}">
                                                {{ __('Modifier') }}
                                            </x-secondary-button>

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

                                <tr x-show="editingClassroom === {{ $classroom->id }}">
                                    <td colspan="3" class="bg-indigo-50/50 px-4 py-5 align-top">
                                        <form method="POST" action="{{ route('admin.classrooms.update', $classroom) }}" class="space-y-4 rounded-lg border border-indigo-100 bg-white p-4 shadow-sm">
                                            @csrf
                                            @method('PATCH')

                                            <div class="grid gap-3 md:grid-cols-[1fr_2fr] md:items-end">
                                                <div>
                                                    <x-input-label for="classroom-{{ $classroom->id }}-name" :value="__('Nom')" />
                                                    <x-text-input id="classroom-{{ $classroom->id }}-name" name="name" value="{{ $classroom->name }}" class="mt-1 block w-full" required />
                                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                                </div>

                                                <div>
                                                    <x-input-label for="classroom-{{ $classroom->id }}-description" :value="__('Description')" />
                                                    <x-text-input id="classroom-{{ $classroom->id }}-description" name="description" value="{{ $classroom->description }}" class="mt-1 block w-full" />
                                                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                                                </div>
                                            </div>

                                            <input type="hidden" name="is_active" value="{{ $classroom->is_active ? '1' : '0' }}">

                                            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                                <x-secondary-button type="reset" x-on:click="editingClassroom = null">
                                                    {{ __('Annuler') }}
                                                </x-secondary-button>

                                                <x-primary-button>
                                                    {{ __('Enregistrer') }}
                                                </x-primary-button>
                                            </div>
                                        </form>
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
