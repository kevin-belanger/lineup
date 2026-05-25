<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RequestType;
use App\Services\ApplicationSettings;
use App\Services\ApplicationUpdateChecker;
use App\Services\LocaleManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(
        ApplicationSettings $settings,
        LocaleManager $localeManager,
        ApplicationUpdateChecker $updateChecker,
    ): View {
        return view('admin.settings.edit', [
            'displayName' => $settings->displayName(),
            'defaultLocale' => $settings->defaultLocale(),
            'availableLocales' => $localeManager->availableLocales(),
            'updateStatus' => $updateChecker->check(),
            'timezone' => $settings->timezone(),
            'timezones' => $settings->availableTimezones(),
            'autoCancelRequestsEnabled' => $settings->autoCancelRequestsEnabled(),
            'autoCancelRequestsTime' => $settings->autoCancelRequestsTime(),
            'priorityRequestDefaultMessage' => $settings->priorityRequestDefaultMessage(),
            'reuseCourseUrlTab' => $settings->reuseCourseUrlTab(),
            'requestTypes' => RequestType::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, ApplicationSettings $settings, LocaleManager $localeManager): RedirectResponse
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:100'],
            'default_locale' => ['required', 'string', Rule::in($localeManager->availableLocales())],
            'timezone' => ['required', 'string', Rule::in($settings->availableTimezones())],
            'auto_cancel_requests_enabled' => ['nullable', 'boolean'],
            'auto_cancel_requests_time' => [
                Rule::requiredIf($request->boolean('auto_cancel_requests_enabled')),
                'nullable',
                'date_format:H:i',
            ],
            'priority_request_default_message' => ['nullable', 'string', 'max:500'],
            'reuse_course_url_tab' => ['nullable', 'boolean'],
        ]);

        $settings->updateDisplayName($validated['display_name']);
        $settings->updateDefaultLocale($validated['default_locale']);
        $settings->updateTimezone($validated['timezone']);
        $settings->updateAutoCancelRequests(
            $request->boolean('auto_cancel_requests_enabled'),
            $validated['auto_cancel_requests_time'] ?? $settings->autoCancelRequestsTime(),
        );
        $settings->updatePriorityRequestDefaultMessage($validated['priority_request_default_message'] ?? '');
        $settings->updateReuseCourseUrlTab($request->boolean('reuse_course_url_tab'));

        return redirect()
            ->route('admin.settings.edit')
            ->with('toast', [
                'type' => 'success',
                'message' => __('Settings saved.'),
            ]);
    }

    public function storeRequestType(Request $request): RedirectResponse
    {
        $request->merge([
            'name' => trim((string) $request->input('name', '')),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('request_types', 'name')],
        ]);

        RequestType::query()->create([
            'name' => $validated['name'],
            'sort_order' => ((int) RequestType::query()->max('sort_order')) + 1,
        ]);

        return redirect()
            ->route('admin.settings.edit')
            ->with('toast', [
                'type' => 'success',
                'message' => __('Request type created.'),
            ]);
    }

    public function destroyRequestType(RequestType $requestType): RedirectResponse
    {
        $requestType->delete();

        return redirect()
            ->route('admin.settings.edit')
            ->with('toast', [
                'type' => 'success',
                'message' => __('Request type deleted.'),
            ]);
    }
}
