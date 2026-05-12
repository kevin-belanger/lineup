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
                ->with('classroom:id,name,is_active')
                ->when($filters['search'] !== '', function ($query) use ($filters): void {
                    $query->where(function ($query) use ($filters): void {
                        $query
                            ->where('name', 'like', "%{$filters['search']}%")
                            ->orWhere('description', 'like', "%{$filters['search']}%");
                    });
                })
                ->when($filters['classroom'] === 'none', fn ($query) => $query->whereNull('classroom_id'))
                ->when(! in_array($filters['classroom'], ['all', 'none'], true), fn ($query) => $query->where('classroom_id', (int) $filters['classroom']))
                ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
                ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
                ->orderByDesc('is_active')
                ->orderBy('classroom_id')
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'classrooms' => $classrooms,
            'filters' => $filters,
            'statusOptions' => [
                'all' => 'All statuses',
                'active' => 'Active',
                'inactive' => 'Inactive',
            ],
            'subjectValidationOptions' => Subject::query()
                ->get(['classroom_id', 'name'])
                ->map(fn (Subject $subject): array => [
                    'classroom_id' => $subject->classroom_id,
                    'name' => mb_strtolower(trim($subject->name)),
                ])
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Subject::query()->create($this->validatedData($request));

        return back()->with('status', 'Subject created.');
    }

    public function update(Request $request, Subject $subject): RedirectResponse
    {
        $subject->update($this->validatedData($request, $subject));

        return back()->with('status', 'Subject updated.');
    }

    public function toggleActive(Subject $subject): RedirectResponse
    {
        $subject->update([
            'is_active' => ! $subject->is_active,
        ]);

        return back()->with('status', $subject->is_active ? 'Subject activated.' : 'Subject deactivated.');
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        $subject->delete();

        return back()->with('status', 'Subject deleted. Associated requests keep their history.');
    }

    /**
     * @return array{classroom_id: int, name: string, description: ?string, url: ?string, is_active: bool}
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
            'url' => ['nullable', 'string', 'max:2000', function (string $attribute, mixed $value, \Closure $fail): void {
                $candidate = str_replace(['[table]', '[section]'], ['1', '1'], (string) $value);

                if (filter_var($candidate, FILTER_VALIDATE_URL) === false) {
                    $fail('The URL must be valid.');
                }
            }],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'classroom_id' => (int) $validated['classroom_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'url' => $validated['url'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];
    }
}
