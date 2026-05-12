<x-app-layout>
    <x-slot name="header">
        <x-student-breadcrumb history />
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Request') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Status') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Date') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($requests as $supportRequest)
                                <tr>
                                    <td class="px-4 py-4 align-top">
                                        <div class="font-medium text-gray-900">
                                            <span class="inline-flex items-center gap-1">
                                                <span>{{ $supportRequest->subject?->name ?? 'N/A' }} - {{ $typeLabels[$supportRequest->type] ?? $supportRequest->type }}</span>
                                                <x-subject-request-link :support-request="$supportRequest" />
                                            </span>
                                        </div>
                                        <div class="mt-1 text-sm text-gray-600">
                                            {{ $supportRequest->classroom?->name ?? 'N/A' }}
                                            ·
                                            {{ __('Moodle tile :tile', ['tile' => $supportRequest->moodle_tile_number]) }}
                                            ·
                                            {{ __('Table :table', ['table' => $supportRequest->table_number]) }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 align-top text-sm text-gray-700">
                                        <x-student-request-badges :support-request="$supportRequest" />
                                    </td>
                                    <td class="px-4 py-4 align-top text-sm text-gray-600">
                                        {{ $supportRequest->completed_at?->format('Y-m-d H:i') ?? $supportRequest->updated_at->format('Y-m-d H:i') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500">
                                        {{ __('No requests in history.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-gray-200 px-4 py-3">
                    {{ $requests->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
