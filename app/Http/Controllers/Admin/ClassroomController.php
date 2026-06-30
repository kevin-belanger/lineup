<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ClassroomOpeningHour;
use App\Models\PublicDisplaySlug;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                ->with('openingHours')
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
            DB::transaction(function () use ($request, $classroom): void {
                $classroom->update($this->validatedData($request, $classroom));
                $this->syncOpeningHours($request, $classroom);
            });
        } catch (UniqueConstraintViolationException) {
            $this->validationToastResponse($request, __('A room with this name already exists.'));
        }

        return back()->with('status', __('Room updated.'));
    }

    public function reservePublicSlug(): JsonResponse
    {
        $reservedSlug = PublicDisplaySlug::reserveUnique();

        return response()->json([
            'slug' => $reservedSlug->slug,
            'url' => route('public-display.show', $reservedSlug->slug),
        ]);
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
     * @return array{name: string, description: ?string, is_active: bool, requires_table_number: bool, public_enabled: bool, public_slug: ?string}
     */
    private function validatedData(Request $request, ?Classroom $classroom = null): array
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', Rule::unique('classrooms', 'name')->ignore($classroom)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'requires_table_number' => ['nullable', 'boolean'],
            'public_enabled' => ['nullable', 'boolean'],
            'public_slug' => [
                'exclude_unless:public_enabled,1',
                'required_if:public_enabled,1',
                'string',
                'regex:/^[a-z0-9]{5}$/',
                Rule::exists('public_display_slugs', 'slug'),
                Rule::unique('classrooms', 'public_slug')->ignore($classroom),
            ],
        ], [
            'name.unique' => __('A room with this name already exists.'),
            'public_slug.exists' => __('The public URL must be generated before saving.'),
            'public_slug.regex' => __('The public URL must be generated before saving.'),
            'public_slug.required_if' => __('The public URL must be generated before saving.'),
            'public_slug.unique' => __('This public URL is already used by another room.'),
        ]);

        if ($validator->fails()) {
            $this->validationToastResponse($request, $validator->errors()->first());
        }

        $validated = $validator->validated();
        $publicEnabled = (bool) ($validated['public_enabled'] ?? false);

        if ($publicEnabled && empty($validated['public_slug'])) {
            $this->validationToastResponse($request, __('The public URL must be generated before saving.'));
        }

        $requiresTableNumber = array_key_exists('requires_table_number', $validated)
            ? (bool) $validated['requires_table_number']
            : ($classroom?->requires_table_number ?? true);

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'requires_table_number' => $requiresTableNumber,
            'public_enabled' => $publicEnabled,
            'public_slug' => $publicEnabled ? $validated['public_slug'] : null,
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

    private function syncOpeningHours(Request $request, Classroom $classroom): void
    {
        if (! $request->boolean('opening_hours_present')) {
            return;
        }

        $validator = Validator::make($request->all(), [
            'opening_hours' => ['nullable', 'array'],
            'opening_hours.*.days' => ['required', 'array', 'min:1'],
            'opening_hours.*.days.*' => ['integer', Rule::in(array_keys(ClassroomOpeningHour::DAYS))],
            'opening_hours.*.opens_at' => ['required', 'date_format:H:i'],
            'opening_hours.*.closes_at' => ['required', 'date_format:H:i'],
        ], [
            'opening_hours.*.days.required' => __('Choose at least one day for each opening period.'),
            'opening_hours.*.days.min' => __('Choose at least one day for each opening period.'),
            'opening_hours.*.opens_at.required' => __('Choose an opening time for each opening period.'),
            'opening_hours.*.closes_at.required' => __('Choose a closing time for each opening period.'),
        ]);

        $validator->after(function ($validator) use ($request): void {
            foreach ($request->input('opening_hours', []) as $index => $openingHour) {
                $opensAt = $openingHour['opens_at'] ?? null;
                $closesAt = $openingHour['closes_at'] ?? null;

                if (is_string($opensAt) && is_string($closesAt) && $opensAt >= $closesAt) {
                    $validator->errors()->add(
                        "opening_hours.$index.closes_at",
                        __('The closing time must be after the opening time.'),
                    );
                }
            }
        });

        if ($validator->fails()) {
            $this->validationToastResponse($request, $validator->errors()->first());
        }

        $classroom->openingHours()->delete();

        foreach (array_values($validator->validated()['opening_hours'] ?? []) as $index => $openingHour) {
            $classroom->openingHours()->create([
                'days' => collect($openingHour['days'])
                    ->map(fn ($day): int => (int) $day)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all(),
                'opens_at' => $openingHour['opens_at'],
                'closes_at' => $openingHour['closes_at'],
                'sort_order' => $index,
            ]);
        }
    }
}
