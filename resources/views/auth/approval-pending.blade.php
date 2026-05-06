<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Compte en attente') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p class="font-medium">
                        {{ __('Votre compte doit être approuvé avant de pouvoir utiliser LineUp.') }}
                    </p>
                    <p class="mt-2 text-sm text-gray-600">
                        {{ __('Un administrateur doit valider votre compte. Vous pourrez ensuite accéder à votre espace selon vos rôles.') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
