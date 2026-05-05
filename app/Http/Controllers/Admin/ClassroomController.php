<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClassroomController extends Controller
{
    public function index(): View
    {
        return view('admin.classrooms.index', [
            'classrooms' => Classroom::query()
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Classroom::query()->create($this->validatedData($request));

        return back()->with('status', 'Local cree.');
    }

    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        $classroom->update($this->validatedData($request, $classroom));

        return back()->with('status', 'Local mis a jour.');
    }

    public function toggleActive(Classroom $classroom): RedirectResponse
    {
        $classroom->update([
            'is_active' => ! $classroom->is_active,
        ]);

        return back()->with('status', $classroom->is_active ? 'Local active.' : 'Local desactive.');
    }

    public function destroy(Classroom $classroom): RedirectResponse
    {
        $classroom->delete();

        return back()->with('status', 'Local supprime. Les demandes associees conservent leur historique.');
    }

    /**
     * @return array{name: string, description: ?string, is_active: bool}
     */
    private function validatedData(Request $request, ?Classroom $classroom = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('classrooms', 'name')->ignore($classroom)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];
    }
}
