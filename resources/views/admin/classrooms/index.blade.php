<x-app-layout>
    <x-slot name="header">
        <x-admin-breadcrumb :current="__('Rooms')" />
    </x-slot>

    @php
        $shouldRestoreClassroomCreateInput = old('create_panel') === 'create-classroom' && ($errors->any() || session('classroom_create_validation_failed'));
        $openCreatePanel = session('open_create_panel') === 'classrooms' || $shouldRestoreClassroomCreateInput;
    @endphp

    <div class="py-8">
        <div
            class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8"
            x-data="{
                classrooms: @js($classroomValidationOptions),
                duplicateClassroomMessage: @js(__('A room with this name already exists.')),
                normalize(value) {
                    return value.trim().toLowerCase();
                },
                classroomNameExists(value, ignoredId = null) {
                    const name = this.normalize(value);

                    if (name === '') {
                        return false;
                    }

                    return this.classrooms.some((classroom) => classroom.name === name && Number(classroom.id) !== Number(ignoredId));
                },
            }"
        >
            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <details open x-data="{ open: @js($openCreatePanel) }">
                    <summary
                        class="flex cursor-pointer list-none items-center justify-between gap-3 px-6 py-4 transition hover:bg-gray-50"
                        x-on:click.prevent="open = ! open"
                        x-bind:aria-expanded="open.toString()"
                    >
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ __('Create a new room') }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ __('Add an available room to the application.') }}</p>
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

                    <form
                        method="POST"
                        action="{{ route('admin.classrooms.store') }}"
                        class="space-y-4 border-t border-gray-100 p-6"
                        x-show="open"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 -translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 -translate-y-1"
                        x-cloak
                        x-data="{
                            name: @js($shouldRestoreClassroomCreateInput ? old('name', '') : ''),
                            publicEnabled: @js((bool) old('public_enabled')),
                            publicSlug: @js(old('public_slug', '')),
                            publicUrl: @js(old('public_slug') ? route('public-display.show', old('public_slug')) : ''),
                            publicSlugError: '',
                            reservingPublicSlug: false,
                            publicSlugEndpoint: @js(route('admin.classrooms.public-slugs.store')),
                            publicSlugErrorMessage: @js(__('Unable to generate the public URL.')),
                            nameError: '',
                            async reservePublicSlug() {
                                this.publicSlugError = '';
                                this.reservingPublicSlug = true;

                                try {
                                    const response = await fetch(this.publicSlugEndpoint, {
                                        method: 'POST',
                                        headers: {
                                            'Accept': 'application/json',
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': @js(csrf_token()),
                                        },
                                        body: '{}',
                                    });

                                    if (! response.ok) {
                                        throw new Error('Unable to reserve slug.');
                                    }

                                    const payload = await response.json();
                                    this.publicSlug = payload.slug;
                                    this.publicUrl = payload.url;
                                } catch (error) {
                                    this.publicSlugError = this.publicSlugErrorMessage;
                                } finally {
                                    this.reservingPublicSlug = false;
                                }
                            },
                            validateName() {
                                this.nameError = this.classroomNameExists(this.name)
                                    ? this.duplicateClassroomMessage
                                    : '';

                                return this.nameError === '';
                            },
                        }"
                        x-on:submit="if (! validateName() || (publicEnabled && publicSlug === '') || reservingPublicSlug) $event.preventDefault()"
                    >
                        @csrf
                        <input type="hidden" name="create_panel" value="create-classroom">

                        <div class="grid gap-4 md:grid-cols-[1fr_2fr] md:items-end">
                            <div>
                                <x-input-label for="name" :value="__('Name')" />
                                <x-text-input id="name" name="name" x-model="name" x-on:input="validateName()" class="mt-1 block w-full" required />
                                <p x-show="nameError" x-text="nameError" class="mt-2 text-sm text-red-600"></p>
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="description" :value="__('Description')" />
                                <x-text-input id="description" name="description" class="mt-1 block w-full" :value="$shouldRestoreClassroomCreateInput ? old('description') : ''" />
                                <x-input-error :messages="$errors->get('description')" class="mt-2" />
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-4">
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="is_active" value="1" @checked(! $shouldRestoreClassroomCreateInput || old('is_active')) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                {{ __('Active') }}
                            </label>

                            <x-primary-button x-bind:disabled="nameError !== '' || (publicEnabled && publicSlug === '') || reservingPublicSlug" class="disabled:opacity-50">
                                {{ __('Create') }}
                            </x-primary-button>
                        </div>

                        <div class="space-y-3 rounded-md border border-gray-200 bg-gray-50 p-4">
                            <input type="hidden" name="public_enabled" value="0">
                            <input type="hidden" name="public_slug" x-bind:value="publicSlug">
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                                <input
                                    type="checkbox"
                                    name="public_enabled"
                                    value="1"
                                    x-model="publicEnabled"
                                    x-on:change="if (publicEnabled && publicSlug === '') reservePublicSlug()"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                >
                                {{ __('Create a public page') }}
                            </label>

                            <div x-show="publicEnabled && publicUrl !== ''" class="grid gap-2 sm:grid-cols-[1fr_auto] sm:items-end" x-cloak>
                                <div>
                                    <x-input-label for="new-classroom-public-url" :value="__('Public URL')" />
                                    <x-text-input id="new-classroom-public-url" type="text" class="mt-1 block w-full bg-white text-sm" x-bind:value="publicUrl" readonly />
                                </div>

                                <button
                                    type="button"
                                    x-on:click="reservePublicSlug()"
                                    x-bind:disabled="reservingPublicSlug"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50"
                                    title="{{ __('Regenerate public URL') }}"
                                    aria-label="{{ __('Regenerate public URL') }}"
                                >
                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M7.977 14.652H2.985m0 0v.001m18.03-10.295v4.992m0 0h-4.992m4.993 0-3.181-3.183a8.25 8.25 0 0 0-13.803 3.7" />
                                    </svg>
                                </button>
                            </div>

                            <p x-show="publicEnabled && reservingPublicSlug" class="text-sm text-gray-500" x-cloak>
                                {{ __('Generating public URL...') }}
                            </p>
                            <p x-show="publicSlugError" x-text="publicSlugError" class="text-sm text-red-600" x-cloak></p>
                        </div>
                    </form>
                </details>
            </section>

            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="border-b border-gray-100">
                    <div class="flex flex-col gap-2 px-6 py-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ __('Rooms') }}</h3>
                        </div>

                        <span class="inline-flex w-fit rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700">
                            {{ trans_choice('{0} No rooms shown|{1} 1 room shown|[2,*] :count rooms shown', $classrooms->total(), ['count' => $classrooms->total()]) }}
                        </span>
                    </div>

                    @php
                        $hasActiveClassroomFilters = $filters['search'] !== '' || $filters['status'] !== 'all';
                    @endphp

                    <div
                        class="border-t border-gray-100"
                        x-data="{ open: @js($hasActiveClassroomFilters) }"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-3 bg-gray-50 px-6 py-2 text-left transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-inset"
                            x-on:click="open = ! open"
                            x-bind:aria-expanded="open.toString()"
                            aria-controls="classroom-filters-panel"
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
                            id="classroom-filters-panel"
                            method="GET"
                            action="{{ route('admin.classrooms.index') }}"
                            class="grid gap-3 border-t border-gray-100 bg-gray-50/40 px-6 py-3 sm:grid-cols-2 lg:grid-cols-10 lg:items-end"
                            x-show="open"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 -translate-y-1"
                            x-cloak
                        >
                            <div class="lg:col-span-7">
                                <x-input-label for="classroom-search" :value="__('Search')" />
                                <x-text-input id="classroom-search" name="search" type="search" class="mt-1 block w-full text-sm" :value="$filters['search']" placeholder="{{ __('Name or description') }}" />
                            </div>

                            <div class="lg:col-span-3">
                                <x-input-label for="classroom-status" :value="__('Status')" />
                                <select id="classroom-status" name="status" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @foreach ($statusOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ __($label) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="flex gap-2 sm:col-span-2 lg:col-span-10 lg:justify-end">
                                <x-primary-button>
                                    {{ __('Filter') }}
                                </x-primary-button>

                                <a href="{{ route('admin.classrooms.index') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50">
                                    {{ __('Reset') }}
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Room') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Status') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody x-data="{ editingClassroom: null }" class="divide-y divide-gray-200 bg-white">
                            @forelse ($classrooms as $classroom)
                                <tr x-show="editingClassroom !== {{ $classroom->id }}" x-transition.opacity.duration.150ms>
                                    <td class="px-4 py-4 align-top">
                                        <div class="space-y-2">
                                            <div class="font-semibold text-gray-900">{{ $classroom->name }}</div>
                                            @if ($classroom->description)
                                                <div class="text-sm text-gray-600">{{ $classroom->description }}</div>
                                            @else
                                                <div class="text-sm text-gray-400">{{ __('No description.') }}</div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 align-top text-sm">
                                        <span class="{{ $classroom->is_active ? 'text-green-700' : 'text-red-700' }}">
                                            {{ $classroom->is_active ? __('Active') : __('Inactive') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 align-top text-right">
                                        <x-secondary-button type="button" x-on:click="editingClassroom = {{ $classroom->id }}">
                                            {{ __('Edit') }}
                                        </x-secondary-button>
                                    </td>
                                </tr>

                                <tr x-show="editingClassroom === {{ $classroom->id }}" x-transition.opacity.duration.200ms x-cloak>
                                    <td colspan="3" class="bg-indigo-50/50 px-4 py-5 align-top">
                                        <form
                                            method="POST"
                                            action="{{ route('admin.classrooms.update', $classroom) }}"
                                            class="space-y-4 rounded-lg border border-indigo-100 bg-white p-4 shadow-sm"
                                            x-data="{
                                                name: @js($classroom->name),
                                                publicEnabled: @js($classroom->public_enabled),
                                                publicSlug: @js($classroom->public_slug ?? ''),
                                                publicUrl: @js($classroom->public_slug ? route('public-display.show', $classroom->public_slug) : ''),
                                                publicSlugError: '',
                                                reservingPublicSlug: false,
                                                publicSlugEndpoint: @js(route('admin.classrooms.public-slugs.store')),
                                                publicSlugErrorMessage: @js(__('Unable to generate the public URL.')),
                                                nameError: '',
                                                async reservePublicSlug() {
                                                    this.publicSlugError = '';
                                                    this.reservingPublicSlug = true;

                                                    try {
                                                        const response = await fetch(this.publicSlugEndpoint, {
                                                            method: 'POST',
                                                            headers: {
                                                                'Accept': 'application/json',
                                                                'Content-Type': 'application/json',
                                                                'X-CSRF-TOKEN': @js(csrf_token()),
                                                            },
                                                            body: '{}',
                                                        });

                                                        if (! response.ok) {
                                                            throw new Error('Unable to reserve slug.');
                                                        }

                                                        const payload = await response.json();
                                                        this.publicSlug = payload.slug;
                                                        this.publicUrl = payload.url;
                                                    } catch (error) {
                                                        this.publicSlugError = this.publicSlugErrorMessage;
                                                    } finally {
                                                        this.reservingPublicSlug = false;
                                                    }
                                                },
                                                validateName() {
                                                    this.nameError = this.classroomNameExists(this.name, {{ $classroom->id }})
                                                        ? this.duplicateClassroomMessage
                                                        : '';

                                                    return this.nameError === '';
                                                },
                                            }"
                                            x-on:submit="if (! validateName() || (publicEnabled && publicSlug === '') || reservingPublicSlug) $event.preventDefault()"
                                        >
                                            @csrf
                                            @method('PATCH')

                                            <div class="grid gap-3 md:grid-cols-[1fr_2fr] md:items-end">
                                                <div>
                                                    <x-input-label for="classroom-{{ $classroom->id }}-name" :value="__('Name')" />
                                                    <x-text-input id="classroom-{{ $classroom->id }}-name" name="name" x-model="name" x-on:input="validateName()" class="mt-1 block w-full" required />
                                                    <p x-show="nameError" x-text="nameError" class="mt-2 text-sm text-red-600"></p>
                                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                                </div>

                                                <div>
                                                    <x-input-label for="classroom-{{ $classroom->id }}-description" :value="__('Description')" />
                                                    <x-text-input id="classroom-{{ $classroom->id }}-description" name="description" value="{{ $classroom->description }}" class="mt-1 block w-full" />
                                                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                                                </div>
                                            </div>

                                            <div class="flex items-center justify-between gap-4">
                                                <input type="hidden" name="is_active" value="0">
                                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                                    <input type="checkbox" name="is_active" value="1" @checked($classroom->is_active) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                                    {{ __('Active') }}
                                                </label>
                                            </div>

                                            <div class="space-y-3 rounded-md border border-gray-200 bg-gray-50 p-4">
                                                <input type="hidden" name="public_enabled" value="0">
                                                <input type="hidden" name="public_slug" x-bind:value="publicSlug">
                                                <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                                                    <input
                                                        type="checkbox"
                                                        name="public_enabled"
                                                        value="1"
                                                        x-model="publicEnabled"
                                                        x-on:change="if (publicEnabled && publicSlug === '') reservePublicSlug()"
                                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                    >
                                                    {{ __('Create a public page') }}
                                                </label>

                                                <div x-show="publicEnabled && publicUrl !== ''" class="grid gap-2 sm:grid-cols-[1fr_auto] sm:items-end" x-cloak>
                                                    <div>
                                                        <x-input-label for="classroom-{{ $classroom->id }}-public-url" :value="__('Public URL')" />
                                                        <x-text-input
                                                            id="classroom-{{ $classroom->id }}-public-url"
                                                            type="text"
                                                            class="mt-1 block w-full bg-white text-sm"
                                                            x-bind:value="publicUrl"
                                                            readonly
                                                        />
                                                    </div>

                                                    <button
                                                        type="button"
                                                        x-on:click="reservePublicSlug()"
                                                        x-bind:disabled="reservingPublicSlug"
                                                        class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50"
                                                        title="{{ __('Regenerate public URL') }}"
                                                        aria-label="{{ __('Regenerate public URL') }}"
                                                    >
                                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M7.977 14.652H2.985m0 0v.001m18.03-10.295v4.992m0 0h-4.992m4.993 0-3.181-3.183a8.25 8.25 0 0 0-13.803 3.7" />
                                                        </svg>
                                                    </button>
                                                </div>

                                                <p x-show="publicEnabled && reservingPublicSlug" class="text-sm text-gray-500" x-cloak>
                                                    {{ __('Generating public URL...') }}
                                                </p>
                                                <p x-show="publicSlugError" x-text="publicSlugError" class="text-sm text-red-600" x-cloak></p>
                                            </div>

                                            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                <x-danger-button type="button" x-data x-on:click="$dispatch('open-modal', 'delete-classroom-{{ $classroom->id }}')">
                                                    {{ __('Delete this room') }}
                                                </x-danger-button>

                                                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                                    <x-secondary-button type="reset" x-on:click="editingClassroom = null">
                                                        {{ __('Cancel') }}
                                                    </x-secondary-button>

                                                    <x-primary-button x-bind:disabled="nameError !== '' || (publicEnabled && publicSlug === '') || reservingPublicSlug" class="disabled:opacity-50">
                                                        {{ __('Save') }}
                                                    </x-primary-button>
                                                </div>
                                            </div>
                                        </form>

                                        <x-modal name="delete-classroom-{{ $classroom->id }}" maxWidth="md" focusable>
                                            <x-confirmation-panel
                                                :title="__('Delete room')"
                                                :message="__('Deleting this room will also remove its associations with subjects, but it will not delete any subjects. Associated requests will remain in history, but the room will show N/A.')"
                                            >
                                                <x-slot name="actions">
                                                    <x-secondary-button type="button" x-on:click="$dispatch('close')">
                                                        {{ __('Back') }}
                                                    </x-secondary-button>

                                                    <form method="POST" action="{{ route('admin.classrooms.destroy', $classroom) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <x-danger-button>
                                                            {{ __('Delete') }}
                                                        </x-danger-button>
                                                    </form>
                                                </x-slot>
                                            </x-confirmation-panel>
                                        </x-modal>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500">
                                        {{ __('No rooms.') }}
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
