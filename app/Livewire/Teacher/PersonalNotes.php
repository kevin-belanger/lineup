<?php

namespace App\Livewire\Teacher;

use App\Models\PersonalNote;
use App\Services\ApplicationSettings;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class PersonalNotes extends Component
{
    public string $body = '';

    public ?int $archivedNotePendingDeletionId = null;

    public function create(): void
    {
        $this->body = trim($this->body);

        $validated = $this->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        PersonalNote::query()->create([
            'teacher_id' => auth()->id(),
            'body' => $validated['body'],
        ]);

        $this->body = '';
        $this->dispatch('close-modal', 'create-personal-note');
        $this->dispatchPersonalNotesCountUpdated();
        $this->dispatch('toast', type: 'success', message: __('Personal note saved.'));
    }

    public function archive(int $personalNoteId): void
    {
        $updated = PersonalNote::query()
            ->whereKey($personalNoteId)
            ->where('teacher_id', auth()->id())
            ->whereNull('archived_at')
            ->update([
                'archived_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            $this->dispatch('toast', type: 'error', message: __('This note cannot be changed.'));

            return;
        }

        $this->dispatchPersonalNotesCountUpdated();
        $this->dispatch('toast', type: 'success', message: __('Personal note archived.'));
    }

    public function confirmDeleteArchived(int $personalNoteId): void
    {
        $noteExists = PersonalNote::query()
            ->whereKey($personalNoteId)
            ->where('teacher_id', auth()->id())
            ->whereNotNull('archived_at')
            ->exists();

        if (! $noteExists) {
            $this->dispatch('toast', type: 'error', message: __('This note cannot be changed.'));

            return;
        }

        $this->archivedNotePendingDeletionId = $personalNoteId;
        $this->dispatch('open-modal', 'delete-archived-personal-note');
    }

    public function deleteArchived(?int $personalNoteId = null): void
    {
        $personalNoteId ??= $this->archivedNotePendingDeletionId;

        if ($personalNoteId === null) {
            $this->dispatch('toast', type: 'error', message: __('This note cannot be changed.'));

            return;
        }

        $deleted = PersonalNote::query()
            ->whereKey($personalNoteId)
            ->where('teacher_id', auth()->id())
            ->whereNotNull('archived_at')
            ->delete();

        if ($deleted === 0) {
            $this->dispatch('toast', type: 'error', message: __('This note cannot be changed.'));

            return;
        }

        $this->archivedNotePendingDeletionId = null;
        $this->dispatch('close-modal', 'delete-archived-personal-note');
        $this->dispatch('toast', type: 'success', message: __('Personal note deleted.'));
    }

    public function deleteAllArchived(): void
    {
        $deleted = PersonalNote::query()
            ->where('teacher_id', auth()->id())
            ->whereNotNull('archived_at')
            ->delete();

        $this->dispatch('close-modal', 'delete-all-archived-personal-notes');

        if ($deleted === 0) {
            $this->dispatch('toast', type: 'error', message: __('No archived notes to delete.'));

            return;
        }

        $this->dispatch('toast', type: 'success', message: __('Archived notes deleted.'));
    }

    private function dispatchPersonalNotesCountUpdated(): void
    {
        $this->dispatch(
            'personal-notes-count-updated',
            count: auth()->user()->personalNotes()->whereNull('archived_at')->count(),
        );
    }

    public function render(): View
    {
        $timezone = app(ApplicationSettings::class)->timezone();
        $noteRelations = [
            'supportRequest.classroom:id,name,requires_table_number',
            'supportRequest.subject:id,name,url',
            'supportRequest.fieldAnswers',
            'supportRequest.student:id,first_name,last_name,deleted_at',
            'supportRequest.priorityRequester:id,first_name,last_name,deleted_at',
        ];

        return view('livewire.teacher.personal-notes', [
            'notes' => PersonalNote::query()
                ->with($noteRelations)
                ->where('teacher_id', auth()->id())
                ->whereNull('archived_at')
                ->latest()
                ->get(),
            'archivedNotes' => PersonalNote::query()
                ->with($noteRelations)
                ->where('teacher_id', auth()->id())
                ->whereNotNull('archived_at')
                ->latest('archived_at')
                ->get(),
            'timezone' => $timezone,
        ]);
    }
}
