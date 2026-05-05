<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Utilisateurs') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Nom') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Statut') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Roles') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($users as $user)
                                <tr>
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
                                    <td class="px-4 py-4 align-top">
                                        <form method="POST" action="{{ route('admin.users.roles', $user) }}" class="space-y-2">
                                            @csrf
                                            @method('PATCH')

                                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                                <input type="checkbox" name="is_student" value="1" @checked($user->is_student) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                                {{ __('Etudiant') }}
                                            </label>
                                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                                <input type="checkbox" name="is_teacher" value="1" @checked($user->is_teacher) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                                {{ __('Enseignant') }}
                                            </label>
                                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                                <input type="checkbox" name="is_admin" value="1" @checked($user->is_admin) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                                {{ __('Admin') }}
                                            </label>

                                            <x-secondary-button type="submit">
                                                {{ __('Sauvegarder') }}
                                            </x-secondary-button>
                                        </form>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <div class="flex flex-col items-end gap-2">
                                            @unless ($user->is_approved)
                                                <form method="POST" action="{{ route('admin.users.approve', $user) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <x-primary-button>
                                                        {{ __('Approuver') }}
                                                    </x-primary-button>
                                                </form>
                                            @endunless

                                            <form method="POST" action="{{ route('admin.users.active', $user) }}">
                                                @csrf
                                                @method('PATCH')
                                                <x-secondary-button type="submit">
                                                    {{ $user->is_active ? __('Desactiver') : __('Activer') }}
                                                </x-secondary-button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">
                                        {{ __('Aucun utilisateur.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-gray-200 px-4 py-3">
                    {{ $users->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
