<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            ->with('approver:id,name')
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $query->where(function ($query) use ($filters): void {
                    $query
                        ->where('name', 'like', "%{$filters['search']}%")
                        ->orWhere('email', 'like', "%{$filters['search']}%");
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
            ->orderBy('name')
            ->paginate(100)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $filters,
            'statusOptions' => [
                'all' => 'Tous les statuts',
                'active' => 'Actifs',
                'inactive' => 'Inactifs',
                'approved' => 'Approuvés',
                'pending' => 'En attente d’approbation',
            ],
            'roleOptions' => [
                'all' => 'Tous les rôles',
                'student' => 'Étudiants',
                'teacher' => 'Enseignants',
                'admin' => 'Admins',
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

        User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_student' => (bool) ($validated['is_student'] ?? false),
            'is_teacher' => (bool) ($validated['is_teacher'] ?? false),
            'is_admin' => (bool) ($validated['is_admin'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'is_approved' => (bool) ($validated['is_approved'] ?? false),
            'approved_at' => ($validated['is_approved'] ?? false) ? now() : null,
            'approved_by' => ($validated['is_approved'] ?? false) ? $request->user()->id : null,
        ]);

        return back()->with('status', 'Utilisateur cree.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $this->validatedData($request, $user);

        if ($request->user()->is($user) && ! ($validated['is_admin'] ?? false)) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Vous ne pouvez pas retirer votre propre rôle admin.',
            ]);
        }

        if ($request->user()->is($user) && ! ($validated['is_active'] ?? false)) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Vous ne pouvez pas désactiver votre propre compte.',
            ]);
        }

        $wasApproved = $user->is_approved;
        $isApproved = (bool) ($validated['is_approved'] ?? false);

        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_student' => (bool) ($validated['is_student'] ?? false),
            'is_teacher' => (bool) ($validated['is_teacher'] ?? false),
            'is_admin' => (bool) ($validated['is_admin'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'is_approved' => $isApproved,
            'approved_at' => $isApproved ? ($wasApproved ? $user->approved_at : now()) : null,
            'approved_by' => $isApproved ? ($wasApproved ? $user->approved_by : $request->user()->id) : null,
        ])->save();

        return back()->with('status', 'Utilisateur mis a jour.');
    }

    public function updatePassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', Password::defaults()],
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        return back()->with('status', 'Mot de passe mis a jour.');
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        $user->forceFill([
            'is_approved' => true,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ])->save();

        return back()->with('status', 'Compte approuve.');
    }

    public function updateRoles(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'is_student' => ['nullable', 'boolean'],
            'is_teacher' => ['nullable', 'boolean'],
            'is_admin' => ['nullable', 'boolean'],
        ]);

        $roles = [
            'is_student' => (bool) ($validated['is_student'] ?? false),
            'is_teacher' => (bool) ($validated['is_teacher'] ?? false),
            'is_admin' => (bool) ($validated['is_admin'] ?? false),
        ];

        if (! in_array(true, $roles, true)) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Un utilisateur doit avoir au moins un role.',
            ]);
        }

        if ($request->user()->is($user) && ! $roles['is_admin']) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Vous ne pouvez pas retirer votre propre rôle admin.',
            ]);
        }

        $user->forceFill([
            'is_student' => $roles['is_student'],
            'is_teacher' => $roles['is_teacher'],
            'is_admin' => $roles['is_admin'],
        ])->save();

        return back()->with('status', 'Roles mis a jour.');
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Vous ne pouvez pas désactiver votre propre compte.',
            ]);
        }

        $user->forceFill([
            'is_active' => ! $user->is_active,
        ])->save();

        return back()->with('status', $user->is_active ? 'Compte active.' : 'Compte desactive.');
    }

    /**
     * @return array{name: string, email: string, password?: string, is_student?: bool, is_teacher?: bool, is_admin?: bool, is_active?: bool, is_approved?: bool}
     */
    private function validatedData(Request $request, ?User $user = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
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

        $validated = $request->validate($rules);

        $roles = [
            'is_student' => (bool) ($validated['is_student'] ?? false),
            'is_teacher' => (bool) ($validated['is_teacher'] ?? false),
            'is_admin' => (bool) ($validated['is_admin'] ?? false),
        ];

        if (! in_array(true, $roles, true)) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Un utilisateur doit avoir au moins un role.',
            ])->throwResponse();
        }

        return $validated;
    }
}
