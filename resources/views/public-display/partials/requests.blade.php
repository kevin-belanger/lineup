<div class="public-display__list">
    @forelse ($requests as $supportRequest)
        <article class="public-display__request">
            <div class="public-display__student">
                {{ $supportRequest->student?->fullName() ?? 'N/A' }}
            </div>

            <div class="public-display__table">
                {{ $supportRequest->table_number }}
            </div>
        </article>
    @empty
        <div class="public-display__empty">
            {{ __('No waiting requests.') }}
        </div>
    @endforelse
</div>
