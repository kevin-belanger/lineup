<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubjectController extends Controller
{
    public function index(): View
    {
        return view('admin.subjects.index', [
            'subjects' => Subject::query()
                ->with('classroom:id,name,is_active')
                ->orderByDesc('is_active')
                ->orderBy('classroom_id')
                ->orderBy('name')
                ->paginate(20),
            'classrooms' => Classroom::query()
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(['id', 'name', 'is_active']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Subject::query()->create($this->validatedData($request));

        return back()->with('status', 'Matiere creee.');
    }

    public function update(Request $request, Subject $subject): RedirectResponse
    {
        $subject->update($this->validatedData($request, $subject));

        return back()->with('status', 'Matiere mise a jour.');
    }

    public function toggleActive(Subject $subject): RedirectResponse
    {
        $subject->update([
            'is_active' => ! $subject->is_active,
        ]);

        return back()->with('status', $subject->is_active ? 'Matiere activee.' : 'Matiere desactivee.');
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        $subject->delete();

        return back()->with('status', 'Matiere supprimee. Les demandes associees conservent leur historique.');
    }

    /**
     * @return array{classroom_id: int, name: string, description: ?string, is_active: bool}
     */
    private function validatedData(Request $request, ?Subject $subject = null): array
    {
        $validated = $request->validate([
            'classroom_id' => ['required', 'integer', Rule::exists('classrooms', 'id')->where('is_active', true)],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('subjects', 'name')
                    ->where('classroom_id', $request->integer('classroom_id'))
                    ->ignore($subject),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'classroom_id' => (int) $validated['classroom_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];
    }
}
