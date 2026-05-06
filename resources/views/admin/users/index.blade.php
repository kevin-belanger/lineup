<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Utilisateurs') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div
            class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8"
            x-data="{
                editingUser: null,
                createEmail: '',
                createEmailError: '',
                emails: @js($emailValidationOptions),
                normalize(value) {
                    return value.trim().toLowerCase();
                },
                emailExists(value, ignoredId = null) {
                    const email = this.normalize(value);

                    if (email === '') {
                        return false;
                    }

                    return this.emails.some((user) => user.email === email && Number(user.id) !== Number(ignoredId));
                },
                validateCreateEmail() {
                    this.createEmailError = this.emailExists(this.createEmail)
                        ? 'Cette adresse courriel est deja utilisee.'
                        : '';

                    return this.createEmailError === '';
                },
            }"
        >
            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <details>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-6 py-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ __('Creer un nouvel utilisateur') }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ __('Ajouter un compte et definir ses roles.') }}</p>
                        </div>
                        <span class="text-sm font-medium text-indigo-700">{{ __('Ouvrir') }}</span>
                    </summary>

                    <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-5 border-t border-gray-100 p-6" x-on:submit="if (! validateCreateEmail()) $event.preventDefault()">
                        @csrf

                        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <x-input-label for="name" :value="__('Nom')" />
                                <x-text-input id="name" name="name" class="mt-1 block w-full" required />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="email" :value="__('Adresse courriel')" />
                                <x-text-input id="email" name="email" type="email" x-model="createEmail" x-on:input="validateCreateEmail()" class="mt-1 block w-full" required />
                                <p x-show="createEmailError" x-text="createEmailError" class="mt-2 text-sm text-red-600"></p>
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="password" :value="__('Mot de passe initial')" />
                                <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required minlength="8" />
                                <x-input-error :messages="$errors->get('password')" class="mt-2" />
                            </div>

                            <div class="space-y-2">
                                <div class="text-sm font-medium text-gray-700">{{ __('Roles') }}</div>
                                <div class="flex flex-wrap gap-4">
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="is_student" value="1" checked class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        {{ __('Etudiant') }}
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="is_teacher" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        {{ __('Enseignant') }}
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="is_admin" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        {{ __('Admin') }}
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-4">
                            <div class="flex flex-wrap gap-4">
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    {{ __('Actif') }}
                                </label>
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="is_approved" value="1" checked class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    {{ __('Approuve') }}
                                </label>
                            </div>

                            <x-primary-button x-bind:disabled="createEmailError !== ''" class="disabled:opacity-50">
                                {{ __('Creer') }}
                            </x-primary-button>
                        </div>
                    </form>
                </details>
            </section>

            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('Utilisateurs actifs') }}</h3>
                </div>

                @include('admin.users.partials.user-table', ['users' => $activeUsers, 'emptyMessage' => __('Aucun utilisateur actif.')])
            </section>

            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <details>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-6 py-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ __('Utilisateurs inactifs') }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ __('Comptes desactives pouvant etre reactives.') }}</p>
                        </div>
                        <span class="rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700">{{ $inactiveUsers->count() }}</span>
                    </summary>

                    <div class="border-t border-gray-100">
                        @include('admin.users.partials.user-table', ['users' => $inactiveUsers, 'emptyMessage' => __('Aucun utilisateur inactif.')])
                    </div>
                </details>
            </section>
        </div>
    </div>
</x-app-layout>
