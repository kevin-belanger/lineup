<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->with('approver:id,name')
            ->orderBy('is_approved')
            ->orderBy('name')
            ->paginate(25);

        return view('admin.users.index', [
            'users' => $users,
        ]);
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
            return back()->withErrors([
                'roles' => 'Un utilisateur doit avoir au moins un role.',
            ]);
        }

        if ($request->user()->is($user) && ! $roles['is_admin']) {
            return back()->withErrors([
                'roles' => 'Tu ne peux pas retirer ton propre role admin.',
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
            return back()->withErrors([
                'user' => 'Tu ne peux pas desactiver ton propre compte.',
            ]);
        }

        $user->forceFill([
            'is_active' => ! $user->is_active,
        ])->save();

        return back()->with('status', $user->is_active ? 'Compte active.' : 'Compte desactive.');
    }
}
