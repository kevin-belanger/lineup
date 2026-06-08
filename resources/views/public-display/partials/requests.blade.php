<div class="public-display__list">
    @forelse ($requests as $supportRequest)
        <article class="public-display__request @if ($supportRequest->is_priority) public-display__request--priority @endif">
            <div class="public-display__student">
                {{ $supportRequest->is_priority ? $supportRequest->priorityRequesterDisplayName() : $supportRequest->studentDisplayName() }}
            </div>

            @unless ($supportRequest->is_priority)
                <div class="public-display__table">
                    {{ $supportRequest->table_number }}
                </div>
            @endunless
        </article>
    @empty
        <div class="public-display__empty">
            {{ __('No waiting requests.') }}
        </div>
    @endforelse
</div>
