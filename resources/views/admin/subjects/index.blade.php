<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Matieres') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <details>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-6 py-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ __('Creer une nouvelle matiere') }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ __('Ajouter une matiere associee a un local.') }}</p>
                        </div>
                        <span class="text-sm font-medium text-indigo-700">{{ __('Ouvrir') }}</span>
                    </summary>

                    <form
                        method="POST"
                        action="{{ route('admin.subjects.store') }}"
                        class="space-y-4 border-t border-gray-100 p-6"
                        x-data="{
                            classroomId: '',
                            name: '',
                            url: '',
                            urlError: '',
                            nameError: '',
                            subjects: @js($subjectValidationOptions),
                            normalize(value) {
                                return value.trim().toLowerCase();
                            },
                            validateUrl() {
                                this.urlError = '';

                                if (this.url.trim() === '') {
                                    return true;
                                }

                                try {
                                    const candidate = this.url.replaceAll('[table]', '1').replaceAll('[section]', '1');
                                    new URL(candidate);

                                    return true;
                                } catch (error) {
                                    this.urlError = 'L URL doit etre valide.';

                                    return false;
                                }
                            },
                            validateName() {
                                this.nameError = '';

                                if (this.classroomId === '' || this.name.trim() === '') {
                                    return true;
                                }

                                const exists = this.subjects.some((subject) => String(subject.classroom_id) === String(this.classroomId) && subject.name === this.normalize(this.name));

                                if (exists) {
                                    this.nameError = 'Une matiere avec ce nom existe deja dans ce local.';

                                    return false;
                                }

                                return true;
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

                        <div class="grid gap-4 md:grid-cols-[1fr_1fr_2fr] md:items-end">
                            <div>
                                <x-input-label for="classroom_id" :value="__('Local')" />
                                <select id="classroom_id" name="classroom_id" x-model="classroomId" x-on:change="validateName()" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                    <option value="">{{ __('Choisir') }}</option>
                                    @foreach ($classrooms as $classroom)
                                        <option value="{{ $classroom->id }}" @selected((int) old('classroom_id') === $classroom->id)>
                                            {{ $classroom->name }}{{ $classroom->is_active ? '' : ' - inactif' }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('classroom_id')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="name" :value="__('Nom')" />
                                <x-text-input id="name" name="name" x-model="name" x-on:input="validateName()" class="mt-1 block w-full" required />
                                <p x-show="nameError" x-text="nameError" class="mt-2 text-sm text-red-600"></p>
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="description" :value="__('Description')" />
                                <x-text-input id="description" name="description" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('description')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="url" :value="__('URL')" />
                            <x-text-input id="url" name="url" type="text" x-model="url" x-on:input="validateUrl()" class="mt-1 block w-full" />
                            <p class="mt-1 text-xs text-gray-500">
                                {{ __('Vous pouvez utiliser [table] pour inserer le numero de table et [section] pour inserer le numero de tuile Moodle dans l URL.') }}
                            </p>
                            <p x-show="urlError" x-text="urlError" class="mt-2 text-sm text-red-600"></p>
                            <x-input-error :messages="$errors->get('url')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-between gap-4">
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                {{ __('Actif') }}
                            </label>

                            <x-primary-button x-bind:disabled="hasClientErrors()" class="disabled:opacity-50">
                                {{ __('Creer') }}
                            </x-primary-button>
                        </div>
                    </form>
                </details>
            </section>

            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="border-b border-gray-100">
                    <div class="flex flex-col gap-2 px-6 py-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ __('Matières') }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ __('Recherche, filtres et pagination des matières.') }}</p>
                        </div>

                        <span class="inline-flex w-fit rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700">
                            {{ trans_choice('{0} Aucune matière affichée|{1} 1 matière affichée|[2,*] :count matières affichées', $subjects->total(), ['count' => $subjects->total()]) }}
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
                                {{ __('Filtres') }}
                            </span>
                            <span class="inline-flex items-center text-gray-500">
                                <span class="sr-only" x-text="open ? '{{ __('Fermer les filtres') }}' : '{{ __('Ouvrir les filtres') }}'"></span>
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
                        >
                            <div class="lg:col-span-2">
                                <x-input-label for="subject-search" :value="__('Recherche')" />
                                <x-text-input id="subject-search" name="search" type="search" class="mt-1 block w-full text-sm" :value="$filters['search']" placeholder="{{ __('Nom ou description') }}" />
                            </div>

                            <div>
                                <x-input-label for="subject-classroom" :value="__('Local')" />
                                <select id="subject-classroom" name="classroom" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="all" @selected($filters['classroom'] === 'all')>{{ __('Tous les locaux') }}</option>
                                    <option value="none" @selected($filters['classroom'] === 'none')>{{ __('Sans local') }}</option>
                                    @foreach ($classrooms as $classroom)
                                        <option value="{{ $classroom->id }}" @selected($filters['classroom'] === (string) $classroom->id)>
                                            {{ $classroom->name }}{{ $classroom->is_active ? '' : ' - inactif' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-input-label for="subject-status" :value="__('Statut')" />
                                <select id="subject-status" name="status" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @foreach ($statusOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ __($label) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="flex gap-2 sm:col-span-2 lg:col-span-4 lg:justify-end">
                                <x-primary-button>
                                    {{ __('Filtrer') }}
                                </x-primary-button>

                                <a href="{{ route('admin.subjects.index') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50">
                                    {{ __('Réinitialiser') }}
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Matiere') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Local') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Statut') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody x-data="{ editingSubject: null }" class="divide-y divide-gray-200 bg-white">
                            @forelse ($subjects as $subject)
                                <tr x-show="editingSubject !== {{ $subject->id }}">
                                    <td class="px-4 py-4 align-top">
                                        <div class="space-y-2">
                                            <div class="font-semibold text-gray-900">{{ $subject->name }}</div>
                                            @if ($subject->description)
                                                <div class="text-sm text-gray-600">{{ $subject->description }}</div>
                                            @else
                                                <div class="text-sm text-gray-400">{{ __('Aucune description.') }}</div>
                                            @endif

                                            @if ($subject->url)
                                                <div class="break-all text-sm text-indigo-700">{{ $subject->url }}</div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 align-top text-sm">
                                        <div class="font-medium text-gray-900">{{ $subject->classroom?->name ?? __('Aucun') }}</div>
                                        @if ($subject->classroom && ! $subject->classroom->is_active)
                                            <div class="text-xs text-amber-700">{{ __('Local inactif') }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 align-top text-sm">
                                        <span class="{{ $subject->is_active ? 'text-green-700' : 'text-red-700' }}">
                                            {{ $subject->is_active ? __('Actif') : __('Inactif') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 align-top text-right">
                                        <x-secondary-button type="button" x-on:click="editingSubject = {{ $subject->id }}">
                                            {{ __('Modifier') }}
                                        </x-secondary-button>
                                    </td>
                                </tr>

                                <tr x-show="editingSubject === {{ $subject->id }}">
                                    <td colspan="4" class="bg-indigo-50/50 px-4 py-5 align-top">
                                        <form method="POST" action="{{ route('admin.subjects.update', $subject) }}" class="space-y-4 rounded-lg border border-indigo-100 bg-white p-4 shadow-sm">
                                            @csrf
                                            @method('PATCH')

                                            <div class="grid gap-3 md:grid-cols-[1fr_1fr_2fr] md:items-end">
                                                <div>
                                                    <x-input-label for="subject-{{ $subject->id }}-classroom" :value="__('Local')" />
                                                    <select id="subject-{{ $subject->id }}-classroom" name="classroom_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                                        @foreach ($classrooms as $classroom)
                                                            <option value="{{ $classroom->id }}" @selected($subject->classroom_id === $classroom->id)>
                                                                {{ $classroom->name }}{{ $classroom->is_active ? '' : ' - inactif' }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <x-input-error :messages="$errors->get('classroom_id')" class="mt-2" />
                                                </div>

                                                <div>
                                                    <x-input-label for="subject-{{ $subject->id }}-name" :value="__('Nom')" />
                                                    <x-text-input id="subject-{{ $subject->id }}-name" name="name" value="{{ $subject->name }}" class="mt-1 block w-full" required />
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
                                                <x-text-input id="subject-{{ $subject->id }}-url" name="url" type="text" value="{{ $subject->url }}" class="mt-1 block w-full" />
                                                <p class="mt-1 text-xs text-gray-500">
                                                    {{ __('Vous pouvez utiliser [table] pour inserer le numero de table et [section] pour inserer le numero de tuile Moodle dans l URL.') }}
                                                </p>
                                                <x-input-error :messages="$errors->get('url')" class="mt-2" />
                                            </div>

                                            <div class="flex items-center justify-between gap-4">
                                                <input type="hidden" name="is_active" value="0">
                                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                                    <input type="checkbox" name="is_active" value="1" @checked($subject->is_active) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                                    {{ __('Actif') }}
                                                </label>
                                            </div>

                                            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                <x-danger-button type="button" x-data x-on:click="$dispatch('open-modal', 'delete-subject-{{ $subject->id }}')">
                                                    {{ __('Supprimer cette matière') }}
                                                </x-danger-button>

                                                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                                    <x-secondary-button type="reset" x-on:click="editingSubject = null">
                                                        {{ __('Annuler') }}
                                                    </x-secondary-button>

                                                    <x-primary-button>
                                                        {{ __('Enregistrer') }}
                                                    </x-primary-button>
                                                </div>
                                            </div>
                                        </form>

                                        <x-modal name="delete-subject-{{ $subject->id }}" maxWidth="md" focusable>
                                            <x-confirmation-panel
                                                :title="__('Supprimer la matière')"
                                                :message="__('Les demandes associées resteront dans l’historique, mais la matière affichera N/A. Voulez-vous supprimer cette matière ?')"
                                            >
                                                <x-slot name="actions">
                                                    <x-secondary-button type="button" x-on:click="$dispatch('close')">
                                                        {{ __('Retour') }}
                                                    </x-secondary-button>

                                                    <form method="POST" action="{{ route('admin.subjects.destroy', $subject) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <x-danger-button>
                                                            {{ __('Supprimer') }}
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
                                        {{ __('Aucune matiere.') }}
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
