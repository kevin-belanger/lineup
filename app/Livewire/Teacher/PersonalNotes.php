<?php

namespace App\Livewire\Teacher;

use App\Models\PersonalNote;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class PersonalNotes extends Component
{
    public string $body = '';

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

        $this->dispatch('toast', type: 'success', message: __('Personal note archived.'));
    }

    public function render(): View
    {
        $noteRelations = [
            'supportRequest.classroom:id,name',
            'supportRequest.subject:id,name,url',
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
        ]);
    }
}
