<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ __('Parametres') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-8 p-6">
                    @csrf
                    @method('PATCH')

                    <section>
                        <x-input-label for="display_name" :value="__('Nom de l application')" />
                        <x-text-input id="display_name" name="display_name" type="text" class="mt-1 block w-full" :value="old('display_name', $displayName)" required maxlength="100" />
                        <x-input-error :messages="$errors->get('display_name')" class="mt-2" />
                    </section>

                    <section class="border-t border-gray-200 pt-6">
                        <div>
                            <x-input-label for="timezone" :value="__('Fuseau horaire de l application')" />
                            <p class="mt-1 text-sm text-gray-600">
                                Ce fuseau horaire est utilise pour les taches planifiees, comme l annulation automatique des demandes en fin de journee.
                            </p>
                        </div>

                        <select id="timezone" name="timezone" required class="mt-3 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($timezones as $availableTimezone)
                                <option value="{{ $availableTimezone }}" @selected(old('timezone', $timezone) === $availableTimezone)>
                                    {{ $availableTimezone }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('timezone')" class="mt-2" />
                    </section>

                    <section class="border-t border-gray-200 pt-6">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Annulation automatique des demandes</h3>
                            <p class="mt-1 text-sm text-gray-600">
                                A l'heure indiquee, toutes les demandes encore en attente ou prises en charge seront annulees automatiquement. Les demandes terminees ou deja annulees ne seront pas modifiees.
                            </p>
                        </div>

                        <div class="mt-5 space-y-5">
                            <label for="auto_cancel_requests_enabled" class="flex items-start gap-3">
                                <input type="hidden" name="auto_cancel_requests_enabled" value="0">
                                <input
                                    id="auto_cancel_requests_enabled"
                                    name="auto_cancel_requests_enabled"
                                    type="checkbox"
                                    value="1"
                                    @checked(old('auto_cancel_requests_enabled', $autoCancelRequestsEnabled))
                                    class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                >
                                <span>
                                    <span class="block text-sm font-medium text-gray-900">Annuler automatiquement les demandes actives en fin de journee</span>
                                    <span class="block text-sm text-gray-600">Si cette option est desactivee, aucune annulation automatique ne sera faite.</span>
                                </span>
                            </label>
                            <x-input-error :messages="$errors->get('auto_cancel_requests_enabled')" class="mt-2" />

                            <div>
                                <x-input-label for="auto_cancel_requests_time" :value="__('Heure d annulation automatique')" />
                                <x-text-input id="auto_cancel_requests_time" name="auto_cancel_requests_time" type="time" class="mt-1 block w-48" :value="old('auto_cancel_requests_time', $autoCancelRequestsTime)" />
                                <p class="mt-1 text-sm text-gray-600">
                                    Cette heure utilise le fuseau horaire configure ci-dessus.
                                </p>
                                <x-input-error :messages="$errors->get('auto_cancel_requests_time')" class="mt-2" />
                            </div>
                        </div>
                    </section>

                    <x-primary-button>
                        {{ __('Sauvegarder') }}
                    </x-primary-button>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
