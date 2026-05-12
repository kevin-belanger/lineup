<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Language') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Choose the language used to display the application.') }}
        </p>
    </header>

    <form method="post" action="{{ route('profile.language.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('PATCH')

        <div>
            <x-input-label for="preferred_locale" :value="__('Language')" />
            <select
                id="preferred_locale"
                name="preferred_locale"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
                <option value="" @selected($user->preferred_locale === null)>
                    {{ __('Use application default') }}
                </option>

                @foreach ($availableLocales as $locale)
                    <option value="{{ $locale }}" @selected($user->preferred_locale === $locale)>
                        {{ $locale }}
                    </option>
                @endforeach
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('preferred_locale')" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>
        </div>
    </form>
</section>
