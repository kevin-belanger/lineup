<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ApplicationSettings;
use App\Services\LocaleManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(ApplicationSettings $settings, LocaleManager $localeManager): View
    {
        return view('admin.settings.edit', [
            'displayName' => $settings->displayName(),
            'defaultLocale' => $settings->defaultLocale(),
            'availableLocales' => $localeManager->availableLocales(),
            'timezone' => $settings->timezone(),
            'timezones' => ApplicationSettings::AVAILABLE_TIMEZONES,
            'autoCancelRequestsEnabled' => $settings->autoCancelRequestsEnabled(),
            'autoCancelRequestsTime' => $settings->autoCancelRequestsTime(),
        ]);
    }

    public function update(Request $request, ApplicationSettings $settings, LocaleManager $localeManager): RedirectResponse
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:100'],
            'default_locale' => ['required', 'string', Rule::in($localeManager->availableLocales())],
            'timezone' => ['required', 'timezone'],
            'auto_cancel_requests_enabled' => ['nullable', 'boolean'],
            'auto_cancel_requests_time' => [
                Rule::requiredIf($request->boolean('auto_cancel_requests_enabled')),
                'nullable',
                'date_format:H:i',
            ],
        ]);

        $settings->updateDisplayName($validated['display_name']);
        $settings->updateDefaultLocale($validated['default_locale']);
        $settings->updateTimezone($validated['timezone']);
        $settings->updateAutoCancelRequests(
            $request->boolean('auto_cancel_requests_enabled'),
            $validated['auto_cancel_requests_time'] ?? $settings->autoCancelRequestsTime(),
        );

        return redirect()
            ->route('admin.settings.edit')
            ->with('toast', [
                'type' => 'success',
                'message' => __('Settings saved.'),
            ]);
    }
}
