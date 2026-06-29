<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $supportRequest->exists ? __('Edit request') : __('New request') }}
        </h2>
    </x-slot>

    @php
        $subjectOptions = $subjects->map(fn ($subject): array => [
            'id' => (string) $subject->id,
            'name' => $subject->name,
            'fields' => $subject->activeRequestFields->map(fn ($field): array => [
                'id' => (string) $field->id,
                'name' => $field->name,
                'type' => $field->type,
                'is_required' => $field->is_required,
            ])->values()->all(),
        ])->values()->all();
    @endphp

    <div class="py-8">
        <div class="max-w-3xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div class="mb-6 flex items-center gap-1.5 text-sm text-gray-600">
                    <span>{{ __('Room :name', ['name' => $classroom->name]) }}</span>
                    <x-classroom-opening-status :classroom="$classroom" />
                </div>

                <form
                    method="POST"
                    action="{{ $action }}"
                    class="space-y-5"
                    x-data="{
                        selectedSubjectId: @js((string) old('subject_id', $supportRequest->subject_id)),
                        subjects: @js($subjectOptions),
                        requestFieldValues: @js((object) $requestFieldValues),
                        selectedSubject() {
                            return this.subjects.find((subject) => subject.id === String(this.selectedSubjectId));
                        },
                        selectedFields() {
                            return this.selectedSubject()?.fields || [];
                        },
                        inputType(field) {
                            return field.type === 'text' ? 'text' : 'number';
                        },
                        inputStep(field) {
                            return field.type === 'decimal' ? '0.01' : '1';
                        },
                    }"
                >
                    @csrf
                    @if ($method !== 'POST')
                        @method($method)
                    @endif

                    <div>
                        <x-input-label for="subject_id" :value="__('Subject')" />
                        <select id="subject_id" name="subject_id" x-model="selectedSubjectId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            <option value="">{{ __('Choose') }}</option>
                            @foreach ($subjects as $subject)
                                <option value="{{ $subject->id }}" @selected((int) old('subject_id', $supportRequest->subject_id) === $subject->id)>
                                    {{ $subject->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('subject_id')" class="mt-2" />
                    </div>

                    <div x-show="selectedFields().length > 0" class="rounded-md border border-gray-100 bg-gray-50 p-4">
                        <div class="space-y-4">
                            <template x-for="field in selectedFields()" :key="field.id">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700" x-bind:for="`request-field-${field.id}`">
                                        <span x-text="field.name"></span>
                                    </label>
                                    <input
                                        x-bind:id="`request-field-${field.id}`"
                                        x-bind:name="`request_fields[${field.id}]`"
                                        x-bind:type="inputType(field)"
                                        x-bind:step="inputStep(field)"
                                        x-bind:required="field.is_required"
                                        x-model="requestFieldValues[field.id]"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                </div>
                            </template>
                        </div>

                        <x-input-error :messages="$errors->get('request_fields')" class="mt-2" />
                        <x-input-error :messages="$errors->get('request_fields.*')" class="mt-2" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="table_number" :value="__('Table number')" />
                            <x-text-input id="table_number" name="table_number" value="{{ old('table_number', $supportRequest->table_number) }}" class="mt-1 block w-full" required />
                            <x-input-error :messages="$errors->get('table_number')" class="mt-2" />
                        </div>
                    </div>

                    @if ($requestTypes->isNotEmpty())
                        @php
                            $selectedRequestTypeId = old('request_type_id');

                            if ($selectedRequestTypeId === null && $supportRequest->request_type) {
                                $selectedRequestTypeId = $requestTypes->firstWhere('name', $supportRequest->request_type)?->id;
                            }
                        @endphp

                        <div>
                            <x-input-label for="request_type_id" :value="__('Request type')" />
                            <select id="request_type_id" name="request_type_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @required($requestTypeRequired)>
                                <option value="">{{ __('Choose') }}</option>
                                @foreach ($requestTypes as $requestType)
                                    <option value="{{ $requestType->id }}" @selected((string) $selectedRequestTypeId === (string) $requestType->id)>
                                        {{ $requestType->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('request_type_id')" class="mt-2" />
                        </div>
                    @endif

                    <div>
                        <x-input-label for="comment" :value="__('Comment')" />
                        <textarea id="comment" name="comment" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('comment', $supportRequest->comment) }}</textarea>
                        <x-input-error :messages="$errors->get('comment')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <a href="{{ route('student.dashboard') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">
                            {{ __('Back') }}
                        </a>

                        <x-primary-button>
                            {{ $supportRequest->exists ? __('Save') : __('Create') }}
                        </x-primary-button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
