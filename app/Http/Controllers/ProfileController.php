<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\LocaleManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request, LocaleManager $localeManager): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'availableLocales' => $localeManager->availableLocales(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Update the user's display language preference.
     */
    public function updateLanguage(Request $request, LocaleManager $localeManager): RedirectResponse
    {
        $validated = $request->validate([
            'preferred_locale' => ['nullable', 'string', Rule::in($localeManager->availableLocales())],
        ]);

        $request->user()->forceFill([
            'preferred_locale' => $validated['preferred_locale'] ?: null,
        ])->save();

        return Redirect::route('profile.edit')->with('toast', [
            'type' => 'success',
            'message' => __('Language preference updated.'),
        ]);
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
