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
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
        ]);

        $filters = [
            'search' => trim($validated['search'] ?? ''),
            'status' => $validated['status'] ?? 'all',
        ];

        return view('admin.classrooms.index', [
            'classrooms' => Classroom::query()
                ->when($filters['search'] !== '', function ($query) use ($filters): void {
                    $query->where(function ($query) use ($filters): void {
                        $query
                            ->where('name', 'like', "%{$filters['search']}%")
                            ->orWhere('description', 'like', "%{$filters['search']}%");
                    });
                })
                ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
                ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'filters' => $filters,
            'statusOptions' => [
                'all' => __('All statuses'),
                'active' => __('Active'),
                'inactive' => __('Inactive'),
            ],
            'classroomValidationOptions' => Classroom::query()
                ->pluck('name')
                ->map(fn (string $name): string => mb_strtolower(trim($name)))
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Classroom::query()->create($this->validatedData($request));

        return back()->with('status', __('Room created.'));
    }

    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        $classroom->update($this->validatedData($request, $classroom));

        return back()->with('status', __('Room updated.'));
    }

    public function toggleActive(Classroom $classroom): RedirectResponse
    {
        $classroom->update([
            'is_active' => ! $classroom->is_active,
        ]);

        return back()->with('status', $classroom->is_active ? __('Room activated.') : __('Room deactivated.'));
    }

    public function destroy(Classroom $classroom): RedirectResponse
    {
        $classroom->delete();

        return back()->with('status', __('Room deleted. Associated requests keep their history.'));
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
