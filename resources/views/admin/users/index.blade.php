<x-app-layout>
    <x-slot name="header">
        <x-admin-breadcrumb :current="__('Users')" />
    </x-slot>

    <div class="py-8">
        <div
            class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8"
            x-data="{
                editingUser: null,
                createEmail: '',
                createEmailError: '',
                createRolesError: '',
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
                        ? 'This email address is already in use.'
                        : '';

                    return this.createEmailError === '';
                },
                validateRoles(form, errorProperty) {
                    const roleSelector = 'input[type=checkbox][name=is_student], input[type=checkbox][name=is_teacher], input[type=checkbox][name=is_admin]';

                    this[errorProperty] = Array.from(form.querySelectorAll(roleSelector)).some((checkbox) => checkbox.checked)
                        ? ''
                        : 'Please select at least one role.';

                    return this[errorProperty] === '';
                },
            }"
        >
            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <details x-data="{ open: false }" x-on:toggle="open = $el.open">
                    <summary
                        class="flex cursor-pointer list-none items-center justify-between gap-3 px-6 py-4 transition hover:bg-gray-50"
                        x-bind:aria-expanded="open.toString()"
                    >
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ __('Create a new user') }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ __('Add an account and define its roles.') }}</p>
                        </div>
                        <span class="inline-flex items-center text-gray-500">
                            <span class="sr-only" x-text="open ? '{{ __('Close creation section') }}' : '{{ __('Open creation section') }}'"></span>
                            <svg x-show="! open" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                            <svg x-show="open" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </span>
                    </summary>

                    <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-5 border-t border-gray-100 p-6" x-on:submit="if (! (validateCreateEmail() && validateRoles($el, 'createRolesError'))) $event.preventDefault()">
                        @csrf

                        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <x-input-label for="name" :value="__('Name')" />
                                <x-text-input id="name" name="name" class="mt-1 block w-full" required />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="email" :value="__('Email address')" />
                                <x-text-input id="email" name="email" type="email" x-model="createEmail" x-on:input="validateCreateEmail()" class="mt-1 block w-full" required />
                                <p x-show="createEmailError" x-text="createEmailError" class="mt-2 text-sm text-red-600"></p>
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="password" :value="__('Initial password')" />
                                <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required minlength="8" />
                                <x-input-error :messages="$errors->get('password')" class="mt-2" />
                            </div>

                            <div class="space-y-2">
                                <div class="text-sm font-medium text-gray-700">{{ __('Roles') }}</div>
                                <div class="flex flex-wrap gap-4">
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="is_student" value="1" checked x-on:change="validateRoles($el.form, 'createRolesError')" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        {{ __('Student') }}
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="is_teacher" value="1" x-bind:disabled="! $el.form.querySelector('[name=is_approved]').checked" x-on:change="validateRoles($el.form, 'createRolesError')" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 disabled:opacity-50">
                                        {{ __('Teacher') }}
                                    </label>
                                    @if (Auth::user()->is_admin)
                                        <label class="flex items-center gap-2 text-sm text-gray-700">
                                            <input type="checkbox" name="is_admin" value="1" x-bind:disabled="! $el.form.querySelector('[name=is_approved]').checked" x-on:change="validateRoles($el.form, 'createRolesError')" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 disabled:opacity-50">
                                            {{ __('Admin') }}
                                        </label>
                                    @endif
                                </div>
                                <p x-show="createRolesError" x-text="createRolesError" class="text-sm text-red-600"></p>
                                <p class="text-xs text-gray-500">{{ __('A user must be approved before receiving the teacher or administrator role.') }}</p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-4">
                            <div class="flex flex-wrap gap-4">
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    {{ __('Active') }}
                                </label>
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="is_approved" value="1" checked x-on:change="validateRoles($el.form, 'createRolesError')" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    {{ __('Approved') }}
                                </label>
                            </div>

                            <x-primary-button x-bind:disabled="createEmailError !== '' || createRolesError !== ''" class="disabled:opacity-50">
                                {{ __('Create') }}
                            </x-primary-button>
                        </div>
                    </form>
                </details>
            </section>

            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="border-b border-gray-100">
                    <div class="flex flex-col gap-2 px-6 py-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ __('Users') }}</h3>
                        </div>

                        <span class="inline-flex w-fit rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700">
                            {{ trans_choice('{0} No users shown|{1} 1 user shown|[2,*] :count users shown', $users->total(), ['count' => $users->total()]) }}
                        </span>
                    </div>

                    @php
                        $hasActiveUserFilters = $filters['search'] !== '' || $filters['status'] !== 'all' || $filters['role'] !== 'all';
                    @endphp

                    <div
                        class="border-t border-gray-100"
                        x-data="{ open: @js($hasActiveUserFilters) }"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-3 bg-gray-50 px-6 py-2 text-left transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-inset"
                            x-on:click="open = ! open"
                            x-bind:aria-expanded="open.toString()"
                            aria-controls="user-filters-panel"
                        >
                            <span class="inline-flex items-center gap-2 text-sm font-medium text-gray-800">
                                <svg class="h-4 w-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v3.118a2.25 2.25 0 0 1-1.244 2.013l-2.25 1.125A1.125 1.125 0 0 1 9 19.681v-5.249a2.25 2.25 0 0 0-.659-1.591L2.909 7.409a2.25 2.25 0 0 1-.659-1.591V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" />
                                </svg>
                                {{ __('Filters') }}
                            </span>
                            <span class="inline-flex items-center text-gray-500">
                                <span class="sr-only" x-text="open ? '{{ __('Close filters') }}' : '{{ __('Open filters') }}'"></span>
                                <svg
                                    x-show="! open"
                                    class="h-4 w-4"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.8"
                                    stroke="currentColor"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                </svg>
                                <svg
                                    x-show="open"
                                    class="h-4 w-4"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.8"
                                    stroke="currentColor"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </span>
                        </button>

                        <form
                            id="user-filters-panel"
                            method="GET"
                            action="{{ route('admin.users.index') }}"
                            class="grid gap-3 border-t border-gray-100 bg-gray-50/40 px-6 py-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end"
                            x-show="open"
                        >
                            <div class="lg:col-span-2">
                                <x-input-label for="user-search" :value="__('Search')" />
                                <x-text-input id="user-search" name="search" type="search" class="mt-1 block w-full text-sm" :value="$filters['search']" placeholder="{{ __('Name or email') }}" />
                            </div>

                            <div>
                                <x-input-label for="user-status" :value="__('Status')" />
                                <select id="user-status" name="status" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @foreach ($statusOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ __($label) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-input-label for="user-role" :value="__('Role')" />
                                <select id="user-role" name="role" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @foreach ($roleOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($filters['role'] === $value)>{{ __($label) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="flex gap-2 sm:col-span-2 lg:col-span-4 lg:justify-end">
                                <x-primary-button>
                                    {{ __('Filter') }}
                                </x-primary-button>

                                <a href="{{ route('admin.users.index') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50">
                                    {{ __('Reset') }}
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                @include('admin.users.partials.user-table', ['users' => $users, 'emptyMessage' => __('No users match the filters.')])
            </section>
        </div>
    </div>
</x-app-layout>
