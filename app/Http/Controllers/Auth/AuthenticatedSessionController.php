<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\SupportRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        if (! $request->user()->is_approved) {
            return redirect()->intended(route('approval.pending', absolute: false));
        }

        $activeRequest = null;

        if ($request->user()->is_teacher) {
            $activeRequest = SupportRequest::query()
                ->where('assigned_teacher_id', $request->user()->id)
                ->whereIn('status', SupportRequest::teacherActiveStatuses())
                ->latest()
                ->first(['classroom_id']);
        }

        if ($activeRequest === null && $request->user()->is_student) {
            $activeRequest = SupportRequest::query()
                ->where('student_id', $request->user()->id)
                ->whereIn('status', SupportRequest::activeStatuses())
                ->latest()
                ->first(['classroom_id']);
        }

        if ($activeRequest?->classroom_id !== null) {
            $request->session()->put('current_classroom_id', $activeRequest->classroom_id);
        }

        return redirect()->intended(route($request->user()->homeRouteName(), absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
