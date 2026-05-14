<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Subject;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubjectController extends Controller
{
    public function index(Request $request): View
    {
        $classrooms = Classroom::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'classroom' => ['nullable', Rule::in($classrooms->pluck('id')->map(fn (int $id): string => (string) $id)->push('all')->push('none')->all())],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
        ]);

        $filters = [
            'search' => trim($validated['search'] ?? ''),
            'classroom' => $validated['classroom'] ?? 'all',
            'status' => $validated['status'] ?? 'all',
        ];

        return view('admin.subjects.index', [
            'subjects' => Subject::query()
                ->with('locals:id,name,is_active')
                ->when($filters['search'] !== '', function ($query) use ($filters): void {
                    $query->where(function ($query) use ($filters): void {
                        $query
                            ->where('name', 'like', "%{$filters['search']}%")
                            ->orWhere('description', 'like', "%{$filters['search']}%");
                    });
                })
                ->when($filters['classroom'] === 'none', fn ($query) => $query->whereDoesntHave('locals'))
                ->when(! in_array($filters['classroom'], ['all', 'none'], true), fn ($query) => $query->whereHas('locals', fn ($query) => $query->whereKey((int) $filters['classroom'])))
                ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
                ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'classrooms' => $classrooms,
            'filters' => $filters,
            'statusOptions' => [
                'all' => __('All statuses'),
                'active' => __('Active'),
                'inactive' => __('Inactive'),
            ],
            'subjectValidationOptions' => Subject::query()
                ->get(['id', 'name'])
                ->map(fn (Subject $subject): array => [
                    'id' => $subject->id,
                    'name' => mb_strtolower(trim($subject->name)),
                ])
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $localIds] = $this->validatedData($request);

        try {
            $subject = Subject::query()->create($data);
            $subject->locals()->sync($localIds);
        } catch (UniqueConstraintViolationException) {
            $this->duplicateNameResponse($request);
        }

        return back()
            ->with('status', __('Subject created.'))
            ->with('open_create_panel', 'subjects');
    }

    public function update(Request $request, Subject $subject): RedirectResponse
    {
        [$data, $localIds] = $this->validatedData($request, $subject);

        try {
            $subject->update($data);
            $subject->locals()->sync($localIds);
        } catch (UniqueConstraintViolationException) {
            $this->duplicateNameResponse($request);
        }

        return back()->with('status', __('Subject updated.'));
    }

    public function toggleActive(Subject $subject): RedirectResponse
    {
        $subject->update([
            'is_active' => ! $subject->is_active,
        ]);

        return back()->with('status', $subject->is_active ? __('Subject activated.') : __('Subject deactivated.'));
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        $subject->delete();

        return back()->with('status', __('Subject deleted. Associated requests keep their history.'));
    }

    /**
     * @return array{0: array{classroom_id: ?int, name: string, description: ?string, url: ?string, is_active: bool}, 1: array<int, int>}
     */
    private function validatedData(Request $request, ?Subject $subject = null): array
    {
        $validator = Validator::make($request->all(), [
            'local_ids' => ['nullable', 'array'],
            'local_ids.*' => ['integer', Rule::exists('classrooms', 'id')],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'url' => ['nullable', 'string', 'max:2000', function (string $attribute, mixed $value, \Closure $fail): void {
                $candidate = str_replace(['[table]', '[section]'], ['1', '1'], (string) $value);

                if (filter_var($candidate, FILTER_VALIDATE_URL) === false) {
                    $fail(__('The URL must be valid.'));
                }
            }],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            $this->validationToastResponse($request, $validator->errors()->first());
        }

        $validated = $validator->validated();

        $localIds = $this->normalizedLocalIds($validated['local_ids'] ?? []);
        $this->ensureNameIsGloballyAvailable($request, $validated['name'], $subject);

        return [[
            'classroom_id' => $localIds[0] ?? null,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'url' => $validated['url'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ], $localIds];
    }

    /**
     * @param  array<int, mixed>  $localIds
     * @return array<int, int>
     */
    private function normalizedLocalIds(array $localIds): array
    {
        return collect($localIds)
            ->map(fn (mixed $localId): int => (int) $localId)
            ->unique()
            ->values()
            ->all();
    }

    private function ensureNameIsGloballyAvailable(Request $request, string $name, ?Subject $subject): void
    {
        $exists = Subject::query()
            ->where('name', $name)
            ->when($subject !== null, fn ($query) => $query->whereKeyNot($subject->id))
            ->exists();

        if ($exists) {
            $this->duplicateNameResponse($request);
        }
    }

    private function duplicateNameResponse(Request $request): void
    {
        $this->validationToastResponse($request, __('A subject with this name already exists.'), 'subject_duplicate_name');
    }

    private function validationToastResponse(Request $request, string $message, ?string $createFailureFlag = null): void
    {
        $redirect = back()->with('toast', [
            'type' => 'error',
            'message' => $message,
        ]);

        if ($request->input('create_panel') === 'create-subject') {
            $redirect
                ->withInput()
                ->with('open_create_panel', 'subjects')
                ->with($createFailureFlag ?? 'subject_create_validation_failed', true);
        }

        $redirect->throwResponse();
    }
}
