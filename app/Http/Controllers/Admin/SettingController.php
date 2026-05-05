<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ApplicationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(ApplicationSettings $settings): View
    {
        return view('admin.settings.edit', [
            'displayName' => $settings->displayName(),
            'autoCancelRequestsEnabled' => $settings->autoCancelRequestsEnabled(),
            'autoCancelRequestsTime' => $settings->autoCancelRequestsTime(),
        ]);
    }

    public function update(Request $request, ApplicationSettings $settings): RedirectResponse
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:100'],
            'auto_cancel_requests_enabled' => ['nullable', 'boolean'],
            'auto_cancel_requests_time' => [
                Rule::requiredIf($request->boolean('auto_cancel_requests_enabled')),
                'nullable',
                'date_format:H:i',
            ],
        ]);

        $settings->updateDisplayName($validated['display_name']);
        $settings->updateAutoCancelRequests(
            $request->boolean('auto_cancel_requests_enabled'),
            $validated['auto_cancel_requests_time'] ?? $settings->autoCancelRequestsTime(),
        );

        return redirect()
            ->route('admin.settings.edit')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Parametres sauvegardes.',
            ]);
    }
}
