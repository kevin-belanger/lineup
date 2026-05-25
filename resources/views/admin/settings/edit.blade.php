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
                            </dl>
                        </div>

                        <div class="mt-4 rounded-md border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
                            @if (! $updateStatus->checked)
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

                        <select id="timezone" name="timezone" required class="mt-3 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($timezones as $availableTimezone)
                                <option value="{{ $availableTimezone }}" @selected(old('timezone', $timezone) === $availableTimezone)>
                                    {{ $availableTimezone }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('timezone')" class="mt-2" />
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

                    <section class="border-t border-gray-200 bg-gray-50 p-6">
                        <x-primary-button>
                            {{ __('Save') }}
                        </x-primary-button>
                    </section>
                </form>
            </section>

            <section class="mt-6 overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('Request types') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ __('These choices appear in the student request form.') }}
                    </p>

                    <form method="POST" action="{{ route('admin.settings.request-types.store') }}" class="mt-5 flex flex-col gap-3 sm:flex-row">
                        @csrf

                        <div class="min-w-0 flex-1">
                            <x-input-label for="request_type_name" :value="__('New request type')" class="sr-only" />
                            <x-text-input
                                id="request_type_name"
                                name="name"
                                type="text"
                                class="block w-full"
                                :value="old('name')"
                                maxlength="100"
                                placeholder="{{ __('New request type') }}"
                                required
                            />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <x-primary-button class="justify-center">
                            {{ __('Add') }}
                        </x-primary-button>
                    </form>

                    <div class="mt-5 divide-y divide-gray-100 rounded-md border border-gray-200">
                        @forelse ($requestTypes as $requestType)
                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                <span class="min-w-0 truncate text-sm font-medium text-gray-900">{{ $requestType->name }}</span>

                                <form method="POST" action="{{ route('admin.settings.request-types.destroy', $requestType) }}">
                                    @csrf
                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 transition hover:bg-red-50 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                        aria-label="{{ __('Delete request type') }}"
                                        title="{{ __('Delete request type') }}"
                                    >
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673A2.25 2.25 0 0 1 15.916 21H8.084a2.25 2.25 0 0 1-2.244-1.327L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        @empty
                            <div class="px-4 py-5 text-sm text-gray-500">
                                {{ __('No request types configured.') }}
                            </div>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
