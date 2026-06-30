<x-app-layout>
    <x-slot name="header">
        <x-admin-breadcrumb :current="__('Subjects')" />
    </x-slot>

    @php
        $shouldRestoreSubjectCreateInput = old('create_panel') === 'create-subject' && ($errors->any() || session('subject_create_validation_failed') || session('subject_duplicate_name'));
        $openCreatePanel = session('open_create_panel') === 'subjects' || $shouldRestoreSubjectCreateInput;
    @endphp

    <div class="py-8">
        <div
            class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8"
            x-data="{
                subjects: @js($subjectValidationOptions),
                duplicateSubjectMessage: @js(__('A subject with this name already exists.')),
                invalidUrlMessage: @js(__('The URL must be valid.')),
                normalize(value) {
                    return value.trim().toLowerCase();
                },
                subjectNameExists(value, ignoredId = null) {
                    const name = this.normalize(value);

                    if (name === '') {
                        return false;
                    }

                    return this.subjects.some((subject) => subject.name === name && Number(subject.id) !== Number(ignoredId));
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
                            <h3 class="text-base font-semibold text-gray-900">{{ __('Create a new subject') }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ __('Add a subject associated with a room.') }}</p>
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
                        action="{{ route('admin.subjects.store') }}"
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
                            name: @js($shouldRestoreSubjectCreateInput ? old('name', '') : ''),
                            url: @js($shouldRestoreSubjectCreateInput ? old('url', '') : ''),
                            requestFields: @js(collect($shouldRestoreSubjectCreateInput ? old('request_fields', []) : [])->values()->map(fn ($field, $index) => [
                                'key' => 'new-'.$index,
                                'id' => $field['id'] ?? '',
                                'name' => $field['name'] ?? '',
                                'type' => $field['type'] ?? \App\Models\SubjectRequestField::TYPE_TEXT,
                                'is_required' => (bool) ($field['is_required'] ?? false),
                            ])->all()),
                            nameError: '',
                            urlError: '',
                            addRequestField() {
                                this.requestFields.push({
                                    key: `new-${Date.now()}-${this.requestFields.length}`,
                                    id: '',
                                    name: '',
                                    type: @js(\App\Models\SubjectRequestField::TYPE_TEXT),
                                    is_required: false,
                                });
                            },
                            removeRequestField(index) {
                                this.requestFields.splice(index, 1);
                            },
                            validateName() {
                                this.nameError = this.subjectNameExists(this.name)
                                    ? this.duplicateSubjectMessage
                                    : '';

                                return this.nameError === '';
                            },
                            validateUrl() {
                                this.urlError = '';

                                if (this.url.trim() === '') {
                                    return true;
                                }

                                try {
                                    const candidate = this.url.replace(/\[[^\]]+\]/g, '1');
                                    new URL(candidate);

                                    return true;
                                } catch (error) {
                                    this.urlError = this.invalidUrlMessage;

                                    return false;
                                }
                            },
                            validateCreate() {
                                const nameIsValid = this.validateName();
                                const urlIsValid = this.validateUrl();

                                return nameIsValid && urlIsValid;
                            },
                            hasClientErrors() {
                                return this.nameError !== '' || this.urlError !== '';
                            },
                        }"
                        x-on:submit="if (! validateCreate()) $event.preventDefault()"
                    >
                        @csrf
                        <input type="hidden" name="create_panel" value="create-subject">

                        <div class="grid gap-4 md:grid-cols-[1fr_2fr] md:items-end">
                            <div>
                                <x-input-label for="name" :value="__('Name')" />
                                <x-text-input id="name" name="name" x-model="name" x-on:input="validateName()" class="mt-1 block w-full" required />
                                <p x-show="nameError" x-text="nameError" class="mt-2 text-sm text-red-600"></p>
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="description" :value="__('Description')" />
                                <x-text-input id="description" name="description" class="mt-1 block w-full" :value="$shouldRestoreSubjectCreateInput ? old('description') : ''" />
                                <x-input-error :messages="$errors->get('description')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="url" :value="__('URL')" />
                            <x-text-input id="url" name="url" type="text" x-model="url" x-on:input="validateUrl()" class="mt-1 block w-full" />
                            <p class="mt-1 text-xs text-gray-500">
                                {{ __('You can use [table] and request field names in brackets in the URL.') }}
                            </p>
                            <p x-show="urlError" x-text="urlError" class="mt-2 text-sm text-red-600"></p>
                            <x-input-error :messages="$errors->get('url')" class="mt-2" />
                        </div>

                        <div class="rounded-md border border-gray-100 bg-gray-50 p-4">
                            <div>
                                <div class="text-sm font-medium text-gray-900">{{ __('Request fields') }}</div>
                                <p class="text-sm text-gray-500">{{ __('These questions are shown to students when this subject is selected.') }}</p>
                            </div>

                            <div class="mt-3 space-y-3">
                                <template x-for="(field, index) in requestFields" :key="field.key">
                                    <div class="rounded-md border border-gray-200 bg-white p-3">
                                        <input type="hidden" x-bind:name="`request_fields[${index}][id]`" x-model="field.id">

                                        <div class="grid grid-cols-[minmax(0,1fr)_11rem] items-end gap-3">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700" x-bind:for="`request-field-create-${index}-name`">{{ __('Name') }}</label>
                                                <input x-bind:id="`request-field-create-${index}-name`" x-bind:name="`request_fields[${index}][name]`" x-model="field.name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700" x-bind:for="`request-field-create-${index}-type`">{{ __('Type') }}</label>
                                                <select x-bind:id="`request-field-create-${index}-type`" x-bind:name="`request_fields[${index}][type]`" x-model="field.type" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    <option value="{{ \App\Models\SubjectRequestField::TYPE_TEXT }}">{{ __('Text') }}</option>
                                                    <option value="{{ \App\Models\SubjectRequestField::TYPE_INTEGER }}">{{ __('Whole number') }}</option>
                                                    <option value="{{ \App\Models\SubjectRequestField::TYPE_DECIMAL }}">{{ __('Decimal number') }}</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                                <input type="checkbox" x-bind:name="`request_fields[${index}][is_required]`" value="1" x-model="field.is_required" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                                {{ __('Required') }}
                                            </label>

                                            <button type="button" x-on:click="removeRequestField(index)" class="inline-flex w-auto items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                                {{ __('Remove') }}
                                            </button>
                                        </div>
                                    </div>
                                </template>

                                <div x-show="requestFields.length === 0" class="rounded-md border border-dashed border-gray-200 bg-white p-4 text-center text-sm text-gray-500">
                                    {{ __('No request fields.') }}
                                </div>

                                <button type="button" x-on:click="addRequestField()" class="flex w-full items-center justify-center rounded-md border border-dashed border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 transition hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                    {{ __('Add field') }}
                                </button>
                            </div>

                            <x-input-error :messages="$errors->get('request_fields')" class="mt-2" />
                            <x-input-error :messages="$errors->get('request_fields.*.name')" class="mt-2" />
                            <x-input-error :messages="$errors->get('request_fields.*.type')" class="mt-2" />
                        </div>

                        @php
                            $selectedLocalIds = collect($shouldRestoreSubjectCreateInput ? old('local_ids', []) : [])->map(fn ($localId) => (int) $localId)->all();
                        @endphp

                        <div class="rounded-md border border-gray-100 bg-gray-50 p-4">
                            <div class="flex flex-col gap-1">
                                <div class="text-sm font-medium text-gray-900">{{ __('Associated rooms') }}</div>
                                <p class="text-sm text-gray-500">{{ __('If no room is selected, this subject remains available in the admin interface but will not be offered to students in any room.') }}</p>
                            </div>

                            <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($classrooms as $classroom)
                                    <label class="flex items-center gap-2 rounded-md border border-gray-200 bg-white px-3 py-2 text-sm {{ $classroom->is_active ? 'text-gray-700' : 'text-gray-500' }}">
                                        <input type="checkbox" name="local_ids[]" value="{{ $classroom->id }}" @checked(in_array($classroom->id, $selectedLocalIds, true)) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        <span>
                                            {{ $classroom->name }}
                                            @unless ($classroom->is_active)
                                                <span class="text-xs text-amber-700">({{ __('Inactive') }})</span>
                                            @endunless
                                        </span>
                                    </label>
                                @endforeach
                            </div>

                            <x-input-error :messages="$errors->get('local_ids')" class="mt-2" />
                            <x-input-error :messages="$errors->get('local_ids.*')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-between gap-4">
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="is_active" value="1" @checked(! $shouldRestoreSubjectCreateInput || old('is_active')) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                {{ __('Active') }}
                            </label>

                            <x-primary-button x-bind:disabled="hasClientErrors()" class="disabled:opacity-50">
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
                            <h3 class="text-base font-semibold text-gray-900">{{ __('Subjects') }}</h3>
                        </div>

                        <span class="inline-flex w-fit rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700">
                            {{ trans_choice('{0} No subjects shown|{1} 1 subject shown|[2,*] :count subjects shown', $subjects->total(), ['count' => $subjects->total()]) }}
                        </span>
                    </div>

                    @php
                        $hasActiveSubjectFilters = $filters['search'] !== '' || $filters['classroom'] !== 'all' || $filters['status'] !== 'all';
                    @endphp

                    <div
                        class="border-t border-gray-100"
                        x-data="{ open: @js($hasActiveSubjectFilters) }"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-3 bg-gray-50 px-6 py-2 text-left transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-inset"
                            x-on:click="open = ! open"
                            x-bind:aria-expanded="open.toString()"
                            aria-controls="subject-filters-panel"
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
                            id="subject-filters-panel"
                            method="GET"
                            action="{{ route('admin.subjects.index') }}"
                            class="grid gap-3 border-t border-gray-100 bg-gray-50/40 px-6 py-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end"
                            x-show="open"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 -translate-y-1"
                            x-cloak
                        >
                            <div class="lg:col-span-2">
                                <x-input-label for="subject-search" :value="__('Search')" />
                                <x-text-input id="subject-search" name="search" type="search" class="mt-1 block w-full text-sm" :value="$filters['search']" placeholder="{{ __('Name or description') }}" />
                            </div>

                            <div>
                                <x-input-label for="subject-classroom" :value="__('Room')" />
                                <select id="subject-classroom" name="classroom" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="all" @selected($filters['classroom'] === 'all')>{{ __('All rooms') }}</option>
                                    <option value="none" @selected($filters['classroom'] === 'none')>{{ __('No room associated') }}</option>
                                    @foreach ($classrooms as $classroom)
                                    <option value="{{ $classroom->id }}" @selected($filters['classroom'] === (string) $classroom->id)>
                                            {{ $classroom->name }}@unless ($classroom->is_active) - {{ __('Inactive') }}@endunless
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-input-label for="subject-status" :value="__('Status')" />
                                <select id="subject-status" name="status" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @foreach ($statusOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ __($label) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="flex gap-2 sm:col-span-2 lg:col-span-4 lg:justify-end">
                                <x-primary-button>
                                    {{ __('Filter') }}
                                </x-primary-button>

                                <a href="{{ route('admin.subjects.index') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50">
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
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Subject') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Rooms') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Status') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody x-data="{ editingSubject: null }" class="divide-y divide-gray-200 bg-white">
                            @forelse ($subjects as $subject)
                                <tr x-show="editingSubject !== {{ $subject->id }}" x-transition.opacity.duration.150ms>
                                    <td class="px-4 py-4 align-top">
                                        <div class="space-y-2">
                                            <div class="font-semibold text-gray-900">{{ $subject->name }}</div>
                                            @if ($subject->description)
                                                <div class="text-sm text-gray-600">{{ $subject->description }}</div>
                                            @else
                                                <div class="text-sm text-gray-400">{{ __('No description.') }}</div>
                                            @endif

                                            @if ($subject->url)
                                                <div class="break-all text-sm text-indigo-700">{{ $subject->url }}</div>
                                            @endif

                                            @php
                                                $activeRequestFields = $subject->requestFields->whereNull('archived_at');
                                            @endphp
                                            @if ($activeRequestFields->isNotEmpty())
                                                <div class="flex flex-wrap gap-1.5">
                                                    @foreach ($activeRequestFields as $field)
                                                        <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">
                                                            {{ $field->name }} · {{ \App\Models\SubjectRequestField::typeLabels()[$field->type] ?? $field->type }}@if ($field->is_required) · {{ __('Required') }}@endif
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 align-top text-sm">
                                        @if ($subject->locals->isEmpty())
                                            <span class="text-gray-400">{{ __('No room associated') }}</span>
                                        @else
                                            <div class="flex flex-wrap gap-2">
                                                @foreach ($subject->locals as $classroom)
                                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $classroom->is_active ? 'bg-gray-100 text-gray-700' : 'bg-amber-50 text-amber-700' }}">
                                                        {{ $classroom->name }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 align-top text-sm">
                                        <span class="{{ $subject->is_active ? 'text-green-700' : 'text-red-700' }}">
                                            {{ $subject->is_active ? __('Active') : __('Inactive') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 align-top text-right">
                                        <x-secondary-button type="button" x-on:click="editingSubject = {{ $subject->id }}">
                                            {{ __('Edit') }}
                                        </x-secondary-button>
                                    </td>
                                </tr>

                                <tr x-show="editingSubject === {{ $subject->id }}" x-transition.opacity.duration.200ms x-cloak>
                                    <td colspan="4" class="bg-indigo-50/50 px-4 py-5 align-top">
                                        <form
                                            method="POST"
                                            action="{{ route('admin.subjects.update', $subject) }}"
                                            class="space-y-4 rounded-lg border border-indigo-100 bg-white p-4 shadow-sm"
                                            x-data="{
                                                name: @js($subject->name),
                                                url: @js($subject->url ?? ''),
                                                requestFields: @js($subject->requestFields->whereNull('archived_at')->values()->map(fn ($field) => [
                                                    'key' => 'existing-'.$field->id,
                                                    'id' => $field->id,
                                                    'name' => $field->name,
                                                    'type' => $field->type,
                                                    'is_required' => $field->is_required,
                                                ])->all()),
                                                nameError: '',
                                                urlError: '',
                                                addRequestField() {
                                                    this.requestFields.push({
                                                        key: `new-${Date.now()}-${this.requestFields.length}`,
                                                        id: '',
                                                        name: '',
                                                        type: @js(\App\Models\SubjectRequestField::TYPE_TEXT),
                                                        is_required: false,
                                                    });
                                                },
                                                removeRequestField(index) {
                                                    this.requestFields.splice(index, 1);
                                                },
                                                validateName() {
                                                    this.nameError = this.subjectNameExists(this.name, {{ $subject->id }})
                                                        ? this.duplicateSubjectMessage
                                                        : '';

                                                    return this.nameError === '';
                                                },
                                                validateUrl() {
                                                    this.urlError = '';

                                                    if (this.url.trim() === '') {
                                                        return true;
                                                    }

                                                    try {
                                                        const candidate = this.url.replace(/\[[^\]]+\]/g, '1');
                                                        new URL(candidate);

                                                        return true;
                                                    } catch (error) {
                                                        this.urlError = this.invalidUrlMessage;

                                                        return false;
                                                    }
                                                },
                                                validateEdit() {
                                                    const nameIsValid = this.validateName();
                                                    const urlIsValid = this.validateUrl();

                                                    return nameIsValid && urlIsValid;
                                                },
                                                hasClientErrors() {
                                                    return this.nameError !== '' || this.urlError !== '';
                                                },
                                            }"
                                            x-on:submit="if (! validateEdit()) $event.preventDefault()"
                                        >
                                            @csrf
                                            @method('PATCH')

                                            <div class="grid gap-3 md:grid-cols-[1fr_2fr] md:items-end">
                                                <div>
                                                    <x-input-label for="subject-{{ $subject->id }}-name" :value="__('Name')" />
                                                    <x-text-input id="subject-{{ $subject->id }}-name" name="name" x-model="name" x-on:input="validateName()" class="mt-1 block w-full" required />
                                                    <p x-show="nameError" x-text="nameError" class="mt-2 text-sm text-red-600"></p>
                                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                                </div>

                                                <div>
                                                    <x-input-label for="subject-{{ $subject->id }}-description" :value="__('Description')" />
                                                    <x-text-input id="subject-{{ $subject->id }}-description" name="description" value="{{ $subject->description }}" class="mt-1 block w-full" />
                                                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                                                </div>
                                            </div>

                                            <div>
                                                <x-input-label for="subject-{{ $subject->id }}-url" :value="__('URL')" />
                                                <x-text-input id="subject-{{ $subject->id }}-url" name="url" type="text" x-model="url" x-on:input="validateUrl()" class="mt-1 block w-full" />
                                                <p class="mt-1 text-xs text-gray-500">
                                                    {{ __('You can use [table] and request field names in brackets in the URL.') }}
                                                </p>
                                                <p x-show="urlError" x-text="urlError" class="mt-2 text-sm text-red-600"></p>
                                                <x-input-error :messages="$errors->get('url')" class="mt-2" />
                                            </div>

                                            <div class="rounded-md border border-gray-100 bg-gray-50 p-4">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">{{ __('Request fields') }}</div>
                                                    <p class="text-sm text-gray-500">{{ __('These questions are shown to students when this subject is selected.') }}</p>
                                                </div>

                                                <div class="mt-3 space-y-3">
                                                    <template x-for="(field, index) in requestFields" :key="field.key">
                                                        <div class="rounded-md border border-gray-200 bg-white p-3">
                                                            <input type="hidden" x-bind:name="`request_fields[${index}][id]`" x-model="field.id">

                                                            <div class="grid grid-cols-[minmax(0,1fr)_11rem] items-end gap-3">
                                                                <div>
                                                                    <label class="block text-sm font-medium text-gray-700" x-bind:for="`request-field-edit-{{ $subject->id }}-${index}-name`">{{ __('Name') }}</label>
                                                                    <input x-bind:id="`request-field-edit-{{ $subject->id }}-${index}-name`" x-bind:name="`request_fields[${index}][name]`" x-model="field.name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                                </div>

                                                                <div>
                                                                    <label class="block text-sm font-medium text-gray-700" x-bind:for="`request-field-edit-{{ $subject->id }}-${index}-type`">{{ __('Type') }}</label>
                                                                    <select x-bind:id="`request-field-edit-{{ $subject->id }}-${index}-type`" x-bind:name="`request_fields[${index}][type]`" x-model="field.type" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                                        <option value="{{ \App\Models\SubjectRequestField::TYPE_TEXT }}">{{ __('Text') }}</option>
                                                                        <option value="{{ \App\Models\SubjectRequestField::TYPE_INTEGER }}">{{ __('Whole number') }}</option>
                                                                        <option value="{{ \App\Models\SubjectRequestField::TYPE_DECIMAL }}">{{ __('Decimal number') }}</option>
                                                                    </select>
                                                                </div>
                                                            </div>

                                                            <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                                                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                                                    <input type="checkbox" x-bind:name="`request_fields[${index}][is_required]`" value="1" x-model="field.is_required" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                                                    {{ __('Required') }}
                                                                </label>

                                                                <button type="button" x-on:click="removeRequestField(index)" class="inline-flex w-auto items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                                                    {{ __('Remove') }}
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </template>

                                                    <div x-show="requestFields.length === 0" class="rounded-md border border-dashed border-gray-200 bg-white p-4 text-center text-sm text-gray-500">
                                                        {{ __('No request fields.') }}
                                                    </div>

                                                    <button type="button" x-on:click="addRequestField()" class="flex w-full items-center justify-center rounded-md border border-dashed border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 transition hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                                        {{ __('Add field') }}
                                                    </button>
                                                </div>

                                                <x-input-error :messages="$errors->get('request_fields')" class="mt-2" />
                                                <x-input-error :messages="$errors->get('request_fields.*.name')" class="mt-2" />
                                                <x-input-error :messages="$errors->get('request_fields.*.type')" class="mt-2" />
                                            </div>

                                            @php
                                                $selectedLocalIds = collect(old('local_ids', $subject->locals->pluck('id')->all()))->map(fn ($localId) => (int) $localId)->all();
                                            @endphp

                                            <div class="rounded-md border border-gray-100 bg-gray-50 p-4">
                                                <div class="flex flex-col gap-1">
                                                    <div class="text-sm font-medium text-gray-900">{{ __('Associated rooms') }}</div>
                                                    <p class="text-sm text-gray-500">{{ __('If no room is selected, this subject remains available in the admin interface but will not be offered to students in any room.') }}</p>
                                                </div>

                                                <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                                    @foreach ($classrooms as $classroom)
                                                        <label class="flex items-center gap-2 rounded-md border border-gray-200 bg-white px-3 py-2 text-sm {{ $classroom->is_active ? 'text-gray-700' : 'text-gray-500' }}">
                                                            <input type="checkbox" name="local_ids[]" value="{{ $classroom->id }}" @checked(in_array($classroom->id, $selectedLocalIds, true)) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                                            <span>
                                                                {{ $classroom->name }}
                                                                @unless ($classroom->is_active)
                                                                    <span class="text-xs text-amber-700">({{ __('Inactive') }})</span>
                                                                @endunless
                                                            </span>
                                                        </label>
                                                    @endforeach
                                                </div>

                                                <x-input-error :messages="$errors->get('local_ids')" class="mt-2" />
                                                <x-input-error :messages="$errors->get('local_ids.*')" class="mt-2" />
                                            </div>

                                            <div class="flex items-center justify-between gap-4">
                                                <input type="hidden" name="is_active" value="0">
                                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                                    <input type="checkbox" name="is_active" value="1" @checked($subject->is_active) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                                    {{ __('Active') }}
                                                </label>
                                            </div>

                                            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                <x-danger-button type="button" x-data x-on:click="$dispatch('open-modal', 'delete-subject-{{ $subject->id }}')">
                                                    {{ __('Delete this subject') }}
                                                </x-danger-button>

                                                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                                    <x-secondary-button type="reset" x-on:click="editingSubject = null">
                                                        {{ __('Cancel') }}
                                                    </x-secondary-button>

                                                    <x-primary-button x-bind:disabled="hasClientErrors()" class="disabled:opacity-50">
                                                        {{ __('Save') }}
                                                    </x-primary-button>
                                                </div>
                                            </div>
                                        </form>

                                        <x-modal name="delete-subject-{{ $subject->id }}" maxWidth="md" focusable>
                                            <x-confirmation-panel
                                                :title="__('Delete subject')"
                                                :message="__('Deleting this subject will also remove its associations with rooms, but it will not delete any rooms. Associated requests will remain in history, but the subject will show N/A.')"
                                            >
                                                <x-slot name="actions">
                                                    <x-secondary-button type="button" x-on:click="$dispatch('close')">
                                                        {{ __('Back') }}
                                                    </x-secondary-button>

                                                    <form method="POST" action="{{ route('admin.subjects.destroy', $subject) }}">
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
                                    <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">
                                        {{ __('No subjects.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-gray-200 px-4 py-3">
                    {{ $subjects->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
