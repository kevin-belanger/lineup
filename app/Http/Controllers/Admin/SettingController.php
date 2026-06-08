<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RequestType;
use App\Services\ApplicationSettings;
use App\Services\ApplicationUpdateChecker;
use App\Services\LocaleManager;
use App\Services\ServerClockChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(
        ApplicationSettings $settings,
        LocaleManager $localeManager,
        ApplicationUpdateChecker $updateChecker,
        ServerClockChecker $serverClockChecker,
    ): View {
        return view('admin.settings.edit', [
            'displayName' => $settings->displayName(),
            'defaultLocale' => $settings->defaultLocale(),
            'availableLocales' => $localeManager->availableLocales(),
            'updateStatus' => $updateChecker->check(),
            'serverClockWarning' => $serverClockChecker->hasWarning(),
            'timezone' => $settings->timezone(),
            'timezones' => $settings->availableTimezones(),
            'autoCancelRequestsEnabled' => $settings->autoCancelRequestsEnabled(),
            'autoCancelRequestsTime' => $settings->autoCancelRequestsTime(),
            'priorityRequestDefaultMessage' => $settings->priorityRequestDefaultMessage(),
            'reuseCourseUrlTab' => $settings->reuseCourseUrlTab(),
            'requestTypeRequired' => $settings->requestTypeRequired(),
            'requestTypes' => RequestType::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, ApplicationSettings $settings, LocaleManager $localeManager): RedirectResponse
    {
        $request->merge([
            'request_types' => collect($request->input('request_types', []))
                ->map(fn (mixed $name): string => trim((string) $name))
                ->all(),
        ]);

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
            'request_type_required' => ['nullable', 'boolean'],
            'request_types' => ['array'],
            'request_types.*' => ['required', 'string', 'max:100', 'distinct'],
        ]);

        DB::transaction(function () use ($settings, $validated, $request): void {
            $settings->updateDisplayName($validated['display_name']);
            $settings->updateDefaultLocale($validated['default_locale']);
            $settings->updateTimezone($validated['timezone']);
            $settings->updateAutoCancelRequests(
                $request->boolean('auto_cancel_requests_enabled'),
                $validated['auto_cancel_requests_time'] ?? $settings->autoCancelRequestsTime(),
            );
            $settings->updatePriorityRequestDefaultMessage($validated['priority_request_default_message'] ?? '');
            $settings->updateReuseCourseUrlTab($request->boolean('reuse_course_url_tab'));
            $settings->updateRequestTypeRequired($request->boolean('request_type_required') && ! empty($validated['request_types'] ?? []));
            $this->syncRequestTypes($validated['request_types'] ?? []);
        });

        return redirect()
            ->route('admin.settings.edit')
            ->with('toast', [
                'type' => 'success',
                'message' => __('Settings saved.'),
            ]);
    }

    /**
     * @param  array<int, string>  $names
     */
    private function syncRequestTypes(array $names): void
    {
        RequestType::query()->delete();

        foreach (array_values($names) as $index => $name) {
            RequestType::query()->create([
                'name' => $name,
                'sort_order' => $index + 1,
            ]);
        }
    }
}
