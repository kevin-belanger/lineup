<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Choisir un local') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('student.classroom.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="classroom_id" :value="__('Local')" />
                        <select id="classroom_id" name="classroom_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            <option value="">{{ __('Choisir') }}</option>
                            @foreach ($classrooms as $classroom)
                                <option value="{{ $classroom->id }}" @selected((int) old('classroom_id', $currentClassroomId) === $classroom->id)>
                                    {{ $classroom->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('classroom_id')" class="mt-2" />
                    </div>

                    @if ($activeRequests->isNotEmpty())
                        <div class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                            <div class="font-medium">{{ __('Demande en cours') }}</div>
                            <div class="mt-1">
                                {{ __('Changer vers un autre local annulera tes demandes en cours.') }}
                            </div>
                            <ul class="mt-2 list-disc space-y-1 ps-5">
                                @foreach ($activeRequests as $supportRequest)
                                    <li>
                                        <span class="inline-flex items-center gap-1">
                                            <span>{{ $supportRequest->subject?->name ?? 'N/A' }}</span>
                                            <x-subject-request-link :support-request="$supportRequest" />
                                        </span>
                                        -
                                        {{ $supportRequest->classroom?->name ?? 'N/A' }}
                                    </li>
                                @endforeach
                            </ul>
                            <label class="mt-3 flex items-center gap-2">
                                <input type="checkbox" name="confirm_cancel_active_requests" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <span>{{ __('Je confirme que mes demandes en cours seront annulees si je change de local.') }}</span>
                            </label>
                            <x-input-error :messages="$errors->get('confirm_cancel_active_requests')" class="mt-2" />
                        </div>
                    @endif

                    <x-primary-button>
                        {{ __('Utiliser ce local') }}
                    </x-primary-button>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
