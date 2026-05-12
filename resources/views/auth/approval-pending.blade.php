<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Account pending') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p class="font-medium">
                        {{ __('Your account must be approved before you can use LineUp.') }}
                    </p>
                    <p class="mt-2 text-sm text-gray-600">
                        {{ __('An administrator must approve your account. You will then be able to access your area according to your roles.') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
