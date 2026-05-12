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

                    <section class="border-t border-gray-200 p-6">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ __('Database backup') }}</h3>
                            <p class="mt-1 text-sm text-gray-600">
                                {{ __('Download a SQL dump of the current application database. Keep this file private because it may contain sensitive data.') }}
                            </p>
                        </div>

                        <div class="mt-4">
                            <a
                                href="{{ route('admin.database.backup.download') }}"
                                class="text-sm font-medium text-indigo-600 hover:text-indigo-500 hover:underline focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            >
                                {{ __('Download database backup') }}
                            </a>
                        </div>
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
