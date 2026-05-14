<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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
                ->get(['id', 'name'])
                ->map(fn (Classroom $classroom): array => [
                    'id' => $classroom->id,
                    'name' => mb_strtolower(trim($classroom->name)),
                ])
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            Classroom::query()->create($this->validatedData($request));
        } catch (UniqueConstraintViolationException) {
            $this->validationToastResponse($request, __('A room with this name already exists.'));
        }

        return back()
            ->with('status', __('Room created.'))
            ->with('open_create_panel', 'classrooms');
    }

    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        try {
            $classroom->update($this->validatedData($request, $classroom));
        } catch (UniqueConstraintViolationException) {
            $this->validationToastResponse($request, __('A room with this name already exists.'));
        }

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
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', Rule::unique('classrooms', 'name')->ignore($classroom)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => __('A room with this name already exists.'),
        ]);

        if ($validator->fails()) {
            $this->validationToastResponse($request, $validator->errors()->first());
        }

        $validated = $validator->validated();

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];
    }

    private function validationToastResponse(Request $request, string $message): void
    {
        $redirect = back()->with('toast', [
            'type' => 'error',
            'message' => $message,
        ]);

        if ($request->input('create_panel') === 'create-classroom') {
            $redirect
                ->withInput()
                ->with('open_create_panel', 'classrooms')
                ->with('classroom_create_validation_failed', true);
        }

        $redirect->throwResponse();
    }
}
