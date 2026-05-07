<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Utilisateur') }}</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Statut') }}</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Roles') }}</th>
                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            @forelse ($users as $user)
                <tr x-show="editingUser !== {{ $user->id }}">
                    <td class="px-4 py-4 align-top">
                        <div class="font-medium text-gray-900">{{ $user->name }}</div>
                        <div class="text-sm text-gray-500">{{ $user->email }}</div>
                        @if ($user->approver)
                            <div class="mt-1 text-xs text-gray-400">
                                {{ __('Approuve par :name', ['name' => $user->approver->name]) }}
                            </div>
                        @endif
                    </td>
                    <td class="px-4 py-4 align-top text-sm">
                        <div>
                            <span class="{{ $user->is_approved ? 'text-green-700' : 'text-amber-700' }}">
                                {{ $user->is_approved ? __('Approuve') : __('En attente') }}
                            </span>
                        </div>
                        <div>
                            <span class="{{ $user->is_active ? 'text-green-700' : 'text-red-700' }}">
                                {{ $user->is_active ? __('Actif') : __('Inactif') }}
                            </span>
                        </div>
                    </td>
                    <td class="px-4 py-4 align-top text-sm text-gray-700">
                        <div class="flex flex-wrap gap-2">
                            @if ($user->is_student)
                                <span class="rounded-full bg-gray-100 px-2.5 py-1">{{ __('Etudiant') }}</span>
                            @endif
                            @if ($user->is_teacher)
                                <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-indigo-800">{{ __('Enseignant') }}</span>
                            @endif
                            @if ($user->is_admin)
                                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-amber-800">{{ __('Admin') }}</span>
                            @endif
                        </div>
                    </td>
                    <td class="px-4 py-4 align-top text-right">
                        <x-secondary-button type="button" x-on:click="editingUser = {{ $user->id }}">
                            {{ __('Modifier') }}
                        </x-secondary-button>
                    </td>
                </tr>

                <tr x-show="editingUser === {{ $user->id }}">
                    <td colspan="4" class="bg-indigo-50/50 px-4 py-5 align-top">
                        <form
                            method="POST"
                            action="{{ route('admin.users.update', $user) }}"
                            class="space-y-4 rounded-lg border border-indigo-100 bg-white p-4 shadow-sm"
                            x-data="{
                                email: @js($user->email),
                                emailError: '',
                                rolesError: '',
                                validateEmail() {
                                    this.emailError = emailExists(this.email, {{ $user->id }})
                                        ? 'Cette adresse courriel est deja utilisee.'
                                        : '';

                                    return this.emailError === '';
                                },
                                validateRoles(form) {
                                    const roleSelector = 'input[type=checkbox][name=is_student], input[type=checkbox][name=is_teacher], input[type=checkbox][name=is_admin]';

                                    this.rolesError = Array.from(form.querySelectorAll(roleSelector)).some((checkbox) => checkbox.checked)
                                        ? ''
                                        : 'Veuillez sélectionner au moins un rôle.';

                                    return this.rolesError === '';
                                },
                            }"
                            x-on:submit="if (! (validateEmail() && validateRoles($el))) $event.preventDefault()"
                        >
                            @csrf
                            @method('PATCH')

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label for="user-{{ $user->id }}-name" :value="__('Nom')" />
                                    <x-text-input id="user-{{ $user->id }}-name" name="name" value="{{ $user->name }}" class="mt-1 block w-full" required />
                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="user-{{ $user->id }}-email" :value="__('Adresse courriel')" />
                                    <x-text-input id="user-{{ $user->id }}-email" name="email" type="email" x-model="email" x-on:input="validateEmail()" class="mt-1 block w-full" required />
                                    <p x-show="emailError" x-text="emailError" class="mt-2 text-sm text-red-600"></p>
                                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-3">
                                <div class="space-y-2">
                                    <div class="text-sm font-medium text-gray-700">{{ __('Roles') }}</div>
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="is_student" value="1" @checked($user->is_student) x-on:change="validateRoles($el.form)" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        {{ __('Etudiant') }}
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="is_teacher" value="1" @checked($user->is_teacher) x-on:change="validateRoles($el.form)" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        {{ __('Enseignant') }}
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="is_admin" value="1" @checked($user->is_admin) x-on:change="validateRoles($el.form)" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        {{ __('Admin') }}
                                    </label>
                                    <p x-show="rolesError" x-text="rolesError" class="text-sm text-red-600"></p>
                                </div>

                                <div class="space-y-2">
                                    <div class="text-sm font-medium text-gray-700">{{ __('Statuts') }}</div>
                                    <input type="hidden" name="is_active" value="0">
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="is_active" value="1" @checked($user->is_active) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        {{ __('Actif') }}
                                    </label>

                                    <input type="hidden" name="is_approved" value="0">
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="is_approved" value="1" @checked($user->is_approved) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        {{ __('Approuve') }}
                                    </label>
                                </div>
                            </div>

                            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                <x-secondary-button type="reset" x-on:click="editingUser = null">
                                    {{ __('Annuler') }}
                                </x-secondary-button>

                                <x-secondary-button
                                    type="button"
                                    x-on:click="$dispatch('generate-password-user-{{ $user->id }}'); $dispatch('open-modal', 'password-user-{{ $user->id }}')"
                                >
                                    {{ __('Modifier le mot de passe') }}
                                </x-secondary-button>

                                <x-primary-button x-bind:disabled="emailError !== '' || rolesError !== ''" class="disabled:opacity-50">
                                    {{ __('Enregistrer') }}
                                </x-primary-button>
                            </div>
                        </form>

                        <x-modal name="password-user-{{ $user->id }}" maxWidth="lg" focusable>
                            <div
                                class="p-6"
                                x-data="{
                                    password: '',
                                    copied: false,
                                    passwordError: '',
                                    upper: 'ABCDEFGHJKLMNPQRSTUVWXYZ',
                                    lower: 'abcdefghijkmnopqrstuvwxyz',
                                    numbers: '23456789',
                                    symbols: '!@#$%?*-_',
                                    randomChar(chars) {
                                        return chars[Math.floor(Math.random() * chars.length)];
                                    },
                                    shuffle(value) {
                                        return value.split('').sort(() => Math.random() - 0.5).join('');
                                    },
                                    generate() {
                                        const all = this.upper + this.lower + this.numbers + this.symbols;
                                        let value = [
                                            this.randomChar(this.upper),
                                            this.randomChar(this.lower),
                                            this.randomChar(this.numbers),
                                            this.randomChar(this.symbols),
                                        ].join('');

                                        while (value.length < 16) {
                                            value += this.randomChar(all);
                                        }

                                        this.password = this.shuffle(value);
                                        this.passwordError = '';
                                        this.copied = false;
                                    },
                                    copy() {
                                        if (! this.password) {
                                            return;
                                        }

                                        navigator.clipboard?.writeText(this.password);
                                        this.copied = true;
                                        setTimeout(() => this.copied = false, 1800);
                                    },
                                    validate() {
                                        this.passwordError = this.password.trim().length < 8
                                            ? 'Le mot de passe doit contenir au moins 8 caracteres.'
                                            : '';

                                        return this.passwordError === '';
                                    },
                                }"
                                x-on:generate-password-user-{{ $user->id }}.window="generate()"
                                x-init="generate()"
                            >
                                <h3 class="text-lg font-semibold text-gray-900">{{ __('Modifier le mot de passe') }}</h3>
                                <p class="mt-2 text-sm text-gray-600">
                                    {{ __('Copiez ce mot de passe et transmettez-le a l utilisateur de facon securitaire.') }}
                                </p>

                                <form method="POST" action="{{ route('admin.users.password', $user) }}" class="mt-6 space-y-5" x-on:submit="if (! validate()) $event.preventDefault()">
                                    @csrf
                                    @method('PATCH')

                                    <div>
                                        <x-input-label for="user-{{ $user->id }}-password" :value="__('Nouveau mot de passe')" />
                                        <div class="mt-1 flex gap-2">
                                            <x-text-input id="user-{{ $user->id }}-password" name="password" type="text" x-model="password" x-on:input="validate()" class="block w-full font-mono" required autocomplete="new-password" />
                                            <x-secondary-button type="button" x-on:click="copy">
                                                {{ __('Copier') }}
                                            </x-secondary-button>
                                        </div>
                                        <p x-show="copied" class="mt-2 text-sm text-green-700">{{ __('Mot de passe copie.') }}</p>
                                        <p x-show="passwordError" x-text="passwordError" class="mt-2 text-sm text-red-600"></p>
                                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                                    </div>

                                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between">
                                        <x-secondary-button type="button" x-on:click="generate">
                                            {{ __('Generer un autre mot de passe') }}
                                        </x-secondary-button>

                                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                            <x-secondary-button type="button" x-on:click="$dispatch('close')">
                                                {{ __('Annuler') }}
                                            </x-secondary-button>

                                            <x-primary-button x-bind:disabled="passwordError !== ''" class="disabled:opacity-50">
                                                {{ __('Enregistrer le mot de passe') }}
                                            </x-primary-button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </x-modal>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">
                        {{ $emptyMessage }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($users->hasPages())
    <div class="border-t border-gray-200 px-4 py-3">
        {{ $users->links() }}
    </div>
@endif
