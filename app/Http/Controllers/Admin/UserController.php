<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive', 'approved', 'pending'])],
            'role' => ['nullable', Rule::in(['all', 'student', 'teacher', 'admin'])],
        ]);

        $filters = [
            'search' => trim($filters['search'] ?? ''),
            'status' => $filters['status'] ?? 'all',
            'role' => $filters['role'] ?? 'all',
        ];

        $users = User::query()
            ->with('approver:id,first_name,last_name')
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $terms = preg_split('/\s+/', $filters['search'], -1, PREG_SPLIT_NO_EMPTY) ?: [];

                $query->where(function ($query) use ($filters, $terms): void {
                    $query
                        ->where('first_name', 'like', "%{$filters['search']}%")
                        ->orWhere('last_name', 'like', "%{$filters['search']}%")
                        ->orWhere('email', 'like', "%{$filters['search']}%");

                    foreach ($terms as $term) {
                        $query->orWhere(function ($query) use ($term): void {
                            $query
                                ->where('first_name', 'like', "%{$term}%")
                                ->orWhere('last_name', 'like', "%{$term}%");
                        });
                    }
                });
            })
            ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($filters['status'] === 'approved', fn ($query) => $query->where('is_approved', true))
            ->when($filters['status'] === 'pending', fn ($query) => $query->where('is_approved', false))
            ->when($filters['role'] === 'student', fn ($query) => $query->where('is_student', true))
            ->when($filters['role'] === 'teacher', fn ($query) => $query->where('is_teacher', true))
            ->when($filters['role'] === 'admin', fn ($query) => $query->where('is_admin', true))
            ->orderByDesc('is_active')
            ->orderBy('is_approved')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(100)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $filters,
            'statusOptions' => [
                'all' => __('All statuses'),
                'active' => __('Active'),
                'inactive' => __('Inactive'),
                'approved' => __('Approved'),
                'pending' => __('Pending approval'),
            ],
            'roleOptions' => [
                'all' => __('All roles'),
                'student' => __('Students'),
                'teacher' => __('Teachers'),
                'admin' => __('Admins'),
            ],
            'emailValidationOptions' => User::query()
                ->get(['id', 'email'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'email' => mb_strtolower(trim($user->email)),
                ])
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedData($request);
        $this->ensureCanChangeAdminRole($request);
        $roles = [
            'is_student' => (bool) ($validated['is_student'] ?? false),
            'is_teacher' => (bool) ($validated['is_teacher'] ?? false),
            'is_admin' => $this->adminRoleFromRequest($request),
        ];
        $isApproved = (bool) ($validated['is_approved'] ?? false);

        $this->ensureRoleApprovalConsistency($request, $roles, $isApproved);

        try {
            User::query()->create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'] ?? null,
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'is_student' => $roles['is_student'],
                'is_teacher' => $roles['is_teacher'],
                'is_admin' => $roles['is_admin'],
                'is_active' => (bool) ($validated['is_active'] ?? false),
                'is_approved' => $isApproved,
                'approved_at' => $isApproved ? now() : null,
                'approved_by' => $isApproved ? $request->user()->id : null,
            ]);
        } catch (UniqueConstraintViolationException) {
            $this->validationToastResponse($request, __('This email address is already in use.'));
        }

        return back()
            ->with('status', __('User created.'))
            ->with('open_create_panel', 'users');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $this->validatedData($request, $user);
        $this->ensureCanChangeAdminRole($request, $user);
        $roles = [
            'is_student' => (bool) ($validated['is_student'] ?? false),
            'is_teacher' => (bool) ($validated['is_teacher'] ?? false),
            'is_admin' => $this->adminRoleFromRequest($request, $user),
        ];
        $isActive = (bool) ($validated['is_active'] ?? false);

        $this->ensureCanKeepOwnHighestRole($request, $user, $roles);

        $this->ensureCanDeactivateUser($request, $user, $isActive);

        $wasApproved = $user->is_approved;
        $isApproved = (bool) ($validated['is_approved'] ?? false);
        $this->ensureRoleApprovalConsistency($request, $roles, $isApproved);

        try {
            $user->forceFill([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'] ?? null,
                'email' => $validated['email'],
                'is_student' => $roles['is_student'],
                'is_teacher' => $roles['is_teacher'],
                'is_admin' => $roles['is_admin'],
                'is_active' => $isActive,
                'is_approved' => $isApproved,
                'approved_at' => $isApproved ? ($wasApproved ? $user->approved_at : now()) : null,
                'approved_by' => $isApproved ? ($wasApproved ? $user->approved_by : $request->user()->id) : null,
            ])->save();
        } catch (UniqueConstraintViolationException) {
            $this->validationToastResponse($request, __('This email address is already in use.'));
        }

        return back()->with('status', __('User updated.'));
    }

    public function updatePassword(Request $request, User $user): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'password' => ['required', Password::defaults()],
        ], [
            'password.required' => __('The password field is required.'),
        ]);

        if ($validator->fails()) {
            $this->validationToastResponse($request, $validator->errors()->first());
        }

        $validated = $validator->validated();

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        return back()->with('status', __('Password updated.'));
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        $user->forceFill([
            'is_approved' => true,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ])->save();

        return back()->with('status', __('Account approved.'));
    }

    public function updateRoles(Request $request, User $user): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'is_student' => ['nullable', 'boolean'],
            'is_teacher' => ['nullable', 'boolean'],
            'is_admin' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            $this->validationToastResponse($request, $validator->errors()->first());
        }

        $validated = $validator->validated();
        $this->ensureCanChangeAdminRole($request, $user);

        $roles = [
            'is_student' => (bool) ($validated['is_student'] ?? false),
            'is_teacher' => (bool) ($validated['is_teacher'] ?? false),
            'is_admin' => $this->adminRoleFromRequest($request, $user),
        ];

        if (! in_array(true, $roles, true)) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => __('A user must have at least one role.'),
            ]);
        }

        $this->ensureCanKeepOwnHighestRole($request, $user, $roles);

        $this->ensureRoleApprovalConsistency($request, $roles, $user->is_approved);

        $user->forceFill([
            'is_student' => $roles['is_student'],
            'is_teacher' => $roles['is_teacher'],
            'is_admin' => $roles['is_admin'],
        ])->save();

        return back()->with('status', __('Roles updated.'));
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => __('You cannot deactivate your own account.'),
            ]);
        }

        if (! $request->user()->is_admin && $user->is_admin && $user->is_active) {
            abort(403, __('Only administrators can deactivate administrator accounts.'));
        }

        $user->forceFill([
            'is_active' => ! $user->is_active,
        ])->save();

        return back()->with('status', $user->is_active ? __('Account activated.') : __('Account deactivated.'));
    }

    /**
     * @return array{first_name: string, last_name?: ?string, email: string, password?: string, is_student?: bool, is_teacher?: bool, is_admin?: bool, is_active?: bool, is_approved?: bool}
     */
    private function validatedData(Request $request, ?User $user = null): array
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'is_student' => ['nullable', 'boolean'],
            'is_teacher' => ['nullable', 'boolean'],
            'is_admin' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'is_approved' => ['nullable', 'boolean'],
        ];

        if ($user === null) {
            $rules['password'] = ['required', Password::defaults()];
        }

        $validator = Validator::make($request->all(), $rules, [
            'email.unique' => __('This email address is already in use.'),
            'password.required' => __('The password field is required.'),
        ]);

        if ($validator->fails()) {
            $this->validationToastResponse($request, $validator->errors()->first());
        }

        $validated = $validator->validated();

        $roles = [
            'is_student' => (bool) ($validated['is_student'] ?? false),
            'is_teacher' => (bool) ($validated['is_teacher'] ?? false),
            'is_admin' => $this->adminRoleFromRequest($request, $user),
        ];

        if (! in_array(true, $roles, true)) {
            $this->validationToastResponse($request, __('A user must have at least one role.'));
        }

        return $validated;
    }

    private function ensureCanChangeAdminRole(Request $request, ?User $user = null): void
    {
        if ($request->user()->is_admin) {
            return;
        }

        $currentAdminRole = (bool) ($user?->is_admin ?? false);

        if ($request->has('is_admin') && $request->boolean('is_admin') !== $currentAdminRole) {
            abort(403, __('Only administrators can change the admin role.'));
        }
    }

    private function adminRoleFromRequest(Request $request, ?User $user = null): bool
    {
        if (! $request->user()->is_admin && $user !== null) {
            return (bool) $user->is_admin;
        }

        return $request->boolean('is_admin');
    }

    private function ensureCanDeactivateUser(Request $request, User $user, bool $isActive): void
    {
        if ($isActive) {
            return;
        }

        if ($request->user()->is($user)) {
            back()->with('toast', [
                'type' => 'error',
                'message' => __('You cannot deactivate your own account.'),
            ])->throwResponse();
        }

        if (! $request->user()->is_admin && $user->is_admin) {
            abort(403, __('Only administrators can deactivate administrator accounts.'));
        }
    }

    /**
     * @param  array{is_student: bool, is_teacher: bool, is_admin: bool}  $roles
     */
    private function ensureCanKeepOwnHighestRole(Request $request, User $user, array $roles): void
    {
        if (! $request->user()->is($user)) {
            return;
        }

        $message = null;

        if ($user->is_admin) {
            $message = $roles['is_admin'] ? null : __('You cannot remove your own admin role.');
        } elseif ($user->is_teacher) {
            $message = $roles['is_teacher'] ? null : __('You cannot remove your own teacher role.');
        } elseif ($user->is_student) {
            $message = $roles['is_student'] ? null : __('You cannot remove your own student role.');
        }

        if ($message === null) {
            return;
        }

        back()->with('toast', [
            'type' => 'error',
            'message' => $message,
        ])->throwResponse();
    }

    /**
     * @param  array{is_student: bool, is_teacher: bool, is_admin: bool}  $roles
     */
    private function ensureRoleApprovalConsistency(Request $request, array $roles, bool $isApproved): void
    {
        if ($isApproved || (! $roles['is_teacher'] && ! $roles['is_admin'])) {
            return;
        }

        $this->validationToastResponse($request, __('A user must be approved before receiving the teacher or administrator role.'));
    }

    private function validationToastResponse(Request $request, string $message): void
    {
        $redirect = back()->with('toast', [
            'type' => 'error',
            'message' => $message,
        ]);

        if ($request->input('create_panel') === 'create-user') {
            $redirect
                ->withInput()
                ->with('open_create_panel', 'users')
                ->with('user_create_validation_failed', true);
        }

        $redirect->throwResponse();
    }
}
