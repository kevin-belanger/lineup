<x-app-layout>
    <x-slot name="header">
        <x-admin-breadcrumb :current="__('Settings')" />
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('admin.settings.update') }}">
                    @csrf
                    @method('PATCH')
                    @php
                        $requestTypeNames = collect(old('request_types', $requestTypes->pluck('name')->all()))
                            ->map(fn ($name) => (string) $name)
                            ->values()
                            ->all();
                    @endphp

                    <section class="p-6">
                        <x-input-label for="display_name" :value="__('Application name')" />
                        <x-text-input id="display_name" name="display_name" type="text" class="mt-1 block w-full" :value="old('display_name', $displayName)" required maxlength="100" />
                        <x-input-error :messages="$errors->get('display_name')" class="mt-2" />
                    </section>

                    <section class="border-t border-gray-200 p-6">
                        <x-input-label for="default_locale" :value="__('Default language')" />
                        <select id="default_locale" name="default_locale" required class="mt-1 block w-48 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($availableLocales as $locale)
                                <option value="{{ $locale }}" @selected(old('default_locale', $defaultLocale) === $locale)>
                                    {{ $locale }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('default_locale')" class="mt-2" />
                    </section>

                    <section class="border-t border-gray-200 p-6">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ __('Application version') }}</h3>
                            <dl class="mt-3 space-y-2 text-sm">
                                <div class="flex justify-between gap-4">
                                    <dt class="text-gray-600">{{ __('Installed version') }}</dt>
                                    <dd class="font-medium text-gray-900">{{ $updateStatus->installedVersion }}</dd>
                                </div>

                                @if ($updateStatus->latestVersion !== null)
                                    <div class="flex justify-between gap-4">
                                        <dt class="text-gray-600">{{ __('Latest available version') }}</dt>
                                        <dd class="font-medium text-gray-900">{{ $updateStatus->latestVersion }}</dd>
                                    </div>
                                @endif

                                @if ($updateStatus->isBranchVersion())
                                    <div class="flex justify-between gap-4">
                                        <dt class="text-gray-600">{{ __('Git branch') }}</dt>
                                        <dd class="font-medium text-gray-900">{{ $updateStatus->installedBranch }}</dd>
                                    </div>
                                    <div class="flex justify-between gap-4">
                                        <dt class="text-gray-600">{{ __('Git commit') }}</dt>
                                        <dd class="font-medium text-gray-900">{{ $updateStatus->installedCommit }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>

                        <div class="mt-4 rounded-md border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
                            @if ($updateStatus->isBranchVersion())
                                <p class="font-medium text-gray-900">{{ __('This installation is using a non-stable branch version.') }}</p>
                                <p class="mt-1">{{ __('Stable release comparison does not apply to branch-based versions.') }}</p>
                            @elseif (! $updateStatus->checked)
                                <p>{{ __('Unable to check for updates at this time.') }}</p>
                            @elseif (! $updateStatus->comparisonAvailable)
                                <p>{{ __('Unable to determine whether this installation is up to date.') }}</p>
                            @elseif ($updateStatus->updateAvailable)
                                <p class="font-medium text-gray-900">{{ __('A newer version is available.') }}</p>
                                <p class="mt-1">{{ __('Run update.sh on the server to update the application.') }}</p>
                                <a
                                    href="{{ rtrim(config('app.repository_url'), '/') }}#updating-the-application"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="mt-2 inline-flex text-sm font-medium text-indigo-600 hover:text-indigo-500 hover:underline"
                                >
                                    {{ __('Read the update procedure') }}
                                </a>
                            @else
                                <p>{{ __('The application is up to date.') }}</p>
                            @endif
                        </div>
                    </section>

                    <section class="border-t border-gray-200 p-6">
                        <div>
                            <x-input-label for="timezone" :value="__('Application time zone')" />
                            <p class="mt-1 text-sm text-gray-600">
                                {{ __('This time zone is used for scheduled tasks, such as automatically cancelling requests at the end of the day.') }}
                            </p>
                        </div>

                        <div
                            class="relative mt-3"
                            x-data="{
                                open: false,
                                search: '',
                                selected: @js(old('timezone', $timezone)),
                                timezones: @js($timezones),
                                get filteredTimezones() {
                                    const query = this.search.trim().toLowerCase();

                                    if (query.length === 0) {
                                        return [];
                                    }

                                    return this.timezones.filter((timezone) => timezone.toLowerCase().includes(query));
                                },
                                choose(timezone) {
                                    this.selected = timezone;
                                    this.search = '';
                                    this.open = false;
                                },
                            }"
                            @click.outside="open = false"
                        >
                            <input type="hidden" id="timezone" name="timezone" required value="{{ old('timezone', $timezone) }}" :value="selected">

                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-3 rounded-md border border-gray-300 bg-white px-3 py-2 text-left shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                @click="open = ! open; if (open) { $nextTick(() => $refs.timezoneSearch.focus()) }"
                                aria-haspopup="listbox"
                                :aria-expanded="open.toString()"
                            >
                                <span class="truncate text-sm text-gray-900" x-text="selected">{{ old('timezone', $timezone) }}</span>
                                <svg class="h-4 w-4 shrink-0 text-gray-500 transition" :class="{ 'rotate-180': open }" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>

                            <div
                                x-cloak
                                x-show="open"
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 -translate-y-1"
                                x-transition:enter-end="opacity-100 translate-y-0"
                                x-transition:leave="transition ease-in duration-100"
                                x-transition:leave-start="opacity-100 translate-y-0"
                                x-transition:leave-end="opacity-0 -translate-y-1"
                                class="absolute z-20 mt-2 w-full overflow-hidden rounded-md border border-gray-200 bg-white shadow-lg"
                            >
                                <div class="border-b border-gray-100 p-2">
                                    <x-text-input
                                        x-ref="timezoneSearch"
                                        x-model="search"
                                        type="search"
                                        class="block w-full text-sm"
                                        placeholder="{{ __('Search time zones') }}"
                                        autocomplete="off"
                                    />
                                </div>

                                <div class="max-h-60 overflow-y-auto py-1" role="listbox">
                                    <template x-if="search.trim().length === 0">
                                        <div class="px-3 py-3 text-sm text-gray-500">{{ __('Start typing to search time zones.') }}</div>
                                    </template>

                                    <template x-if="search.trim().length > 0 && filteredTimezones.length === 0">
                                        <div class="px-3 py-3 text-sm text-gray-500">{{ __('No time zones match your search.') }}</div>
                                    </template>

                                    <template x-for="timezone in filteredTimezones" :key="timezone">
                                        <button
                                            type="button"
                                            class="block w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-indigo-50 hover:text-indigo-800 focus:bg-indigo-50 focus:text-indigo-800 focus:outline-none"
                                            role="option"
                                            :aria-selected="(timezone === selected).toString()"
                                            @click="choose(timezone)"
                                            x-text="timezone"
                                        ></button>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <x-input-error :messages="$errors->get('timezone')" class="mt-2" />

                        @if ($serverClockWarning)
                            <div class="mt-3 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                                <p class="font-semibold">{{ __('Warning') }}</p>
                                <p class="mt-1">{{ __('The server time appears to be incorrect.') }}</p>
                            </div>
                        @endif
                    </section>

                    <section class="border-t border-gray-200 p-6">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ __('Maintenance mode') }}</h3>
                            <p class="mt-1 text-sm text-gray-600">
                                {{ __('When enabled, only administrators can access the application.') }}
                            </p>
                        </div>

                        <div class="mt-5 space-y-5">
                            <label for="maintenance_mode" class="flex items-start gap-3">
                                <input type="hidden" name="maintenance_mode" value="0">
                                <input
                                    id="maintenance_mode"
                                    name="maintenance_mode"
                                    type="checkbox"
                                    value="1"
                                    @checked(old('maintenance_mode', $maintenanceModeEnabled))
                                    class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                >
                                <span>
                                    <span class="block text-sm font-medium text-gray-900">{{ __('Enable maintenance mode') }}</span>
                                    <span class="block text-sm text-gray-600">{{ __('Teachers, students, visitors, and public display pages will see the maintenance message.') }}</span>
                                </span>
                            </label>
                            <x-input-error :messages="$errors->get('maintenance_mode')" class="mt-2" />

                            <div>
                                <x-input-label for="maintenance_message" :value="__('Maintenance message')" />
                                <textarea
                                    id="maintenance_message"
                                    name="maintenance_message"
                                    rows="3"
                                    maxlength="500"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >{{ old('maintenance_message', $maintenanceMessage) }}</textarea>
                                <x-input-error :messages="$errors->get('maintenance_message')" class="mt-2" />
                            </div>
                        </div>
                    </section>

                    <section class="border-t border-gray-200 p-6">
                        <div>
                            <x-input-label for="priority_request_default_message" :value="__('Default priority request message')" />
                            <p class="mt-1 text-sm text-gray-600">
                                {{ __('This message is automatically inserted when a teacher opens the priority request page.') }}
                            </p>
                        </div>

                        <textarea
                            id="priority_request_default_message"
                            name="priority_request_default_message"
                            rows="4"
                            maxlength="500"
                            class="mt-3 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >{{ old('priority_request_default_message', $priorityRequestDefaultMessage) }}</textarea>
                        <x-input-error :messages="$errors->get('priority_request_default_message')" class="mt-2" />
                    </section>

                    <section class="border-t border-gray-200 p-6">
                        <label for="reuse_course_url_tab" class="flex items-start gap-3">
                            <input type="hidden" name="reuse_course_url_tab" value="0">
                            <input
                                id="reuse_course_url_tab"
                                name="reuse_course_url_tab"
                                type="checkbox"
                                value="1"
                                @checked(old('reuse_course_url_tab', $reuseCourseUrlTab))
                                class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                            >
                            <span>
                                <span class="block text-sm font-medium text-gray-900">{{ __('Reuse the same tab when opening a course URL') }}</span>
                                <span class="block text-sm text-gray-600">{{ __('When enabled, course links open in a named browser tab instead of creating a new tab for each click.') }}</span>
                            </span>
                        </label>
                        <x-input-error :messages="$errors->get('reuse_course_url_tab')" class="mt-2" />
                    </section>

                    <section class="border-t border-gray-200 p-6">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ __('Automatic request cancellation') }}</h3>
                            <p class="mt-1 text-sm text-gray-600">
                                {{ __('At the selected time, all requests that are still waiting or taken will be cancelled automatically. Completed or already cancelled requests will not be changed.') }}
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
                                    <span class="block text-sm font-medium text-gray-900">{{ __('Automatically cancel active requests at the end of the day') }}</span>
                                    <span class="block text-sm text-gray-600">{{ __('If this option is disabled, no automatic cancellation will be performed.') }}</span>
                                </span>
                            </label>
                            <x-input-error :messages="$errors->get('auto_cancel_requests_enabled')" class="mt-2" />

                            <div>
                                <x-input-label for="auto_cancel_requests_time" :value="__('Automatic cancellation time')" />
                                <x-text-input id="auto_cancel_requests_time" name="auto_cancel_requests_time" type="time" class="mt-1 block w-48" :value="old('auto_cancel_requests_time', $autoCancelRequestsTime)" />
                                <p class="mt-1 text-sm text-gray-600">
                                    {{ __('This time uses the application time zone configured above.') }}
                                </p>
                                <x-input-error :messages="$errors->get('auto_cancel_requests_time')" class="mt-2" />
                            </div>
                        </div>
                    </section>

                    <section
                        class="border-t border-gray-200 p-6"
                        x-data="{
                            requestTypes: @js($requestTypeNames),
                            requestTypeRequired: @js((bool) old('request_type_required', $requestTypeRequired) && count($requestTypeNames) > 0),
                            add() {
                                this.requestTypes.push('');
                                this.$nextTick(() => this.$refs.requestTypes?.lastElementChild?.querySelector('input')?.focus());
                            },
                            remove(index) {
                                this.requestTypes.splice(index, 1);
                                if (this.requestTypes.length === 0) {
                                    this.requestTypeRequired = false;
                                }
                            },
                        }"
                    >
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ __('Request types') }}</h3>
                            <p class="mt-1 text-sm text-gray-600">
                                {{ __('These choices appear in the student request form.') }}
                            </p>
                        </div>

                        <div class="mt-5 space-y-3" x-ref="requestTypes">
                            <template x-for="(requestType, index) in requestTypes" :key="index">
                                <div class="flex items-start gap-3">
                                    <div class="min-w-0 flex-1">
                                        <label :for="`request_type_name_${index}`" class="sr-only">{{ __('Request type') }}</label>
                                        <input
                                            :id="`request_type_name_${index}`"
                                            name="request_types[]"
                                            type="text"
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            maxlength="100"
                                            x-model="requestTypes[index]"
                                            placeholder="{{ __('Request type') }}"
                                            required
                                        >
                                    </div>

                                    <button
                                        type="button"
                                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md text-gray-400 transition hover:bg-red-50 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                        aria-label="{{ __('Delete request type') }}"
                                        title="{{ __('Delete request type') }}"
                                        @click="remove(index)"
                                    >
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673A2.25 2.25 0 0 1 15.916 21H8.084a2.25 2.25 0 0 1-2.244-1.327L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </template>

                            <div x-show="requestTypes.length === 0" class="rounded-md border border-dashed border-gray-300 bg-gray-50 px-4 py-5 text-sm text-gray-500">
                                {{ __('No request types configured.') }}
                            </div>
                        </div>

                        <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <label for="request_type_required" class="flex items-start gap-3">
                                <input type="hidden" name="request_type_required" value="0">
                                <input
                                    id="request_type_required"
                                    name="request_type_required"
                                    type="checkbox"
                                    value="1"
                                    x-model="requestTypeRequired"
                                    :disabled="requestTypes.length === 0"
                                    class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                <span>
                                    <span class="block text-sm font-medium text-gray-900">{{ __('Make required') }}</span>
                                    <span class="block text-sm text-gray-600">{{ __('Students must choose a request type when creating or editing a request.') }}</span>
                                </span>
                            </label>

                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                @click="add()"
                            >
                                {{ __('Add') }}
                            </button>
                        </div>

                        <x-input-error :messages="$errors->get('request_types')" class="mt-2" />
                        <x-input-error :messages="$errors->get('request_type_required')" class="mt-2" />
                        @foreach ($errors->get('request_types.*') as $messages)
                            <x-input-error :messages="$messages" class="mt-2" />
                        @endforeach
                    </section>

                    <section class="border-t border-gray-200 bg-gray-50 p-6">
                        <x-primary-button>
                            {{ __('Save') }}
                        </x-primary-button>
                    </section>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
