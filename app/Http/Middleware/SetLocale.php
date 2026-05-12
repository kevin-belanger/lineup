<?php

namespace App\Http\Middleware;

use App\Services\ApplicationSettings;
use App\Services\LocaleManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $localeManager = app(LocaleManager::class);
        $locale = app(ApplicationSettings::class)->defaultLocale();
        $user = $request->user();

        if ($user?->preferred_locale !== null) {
            if ($localeManager->isValid($user->preferred_locale)) {
                $locale = $user->preferred_locale;
            } else {
                $user->forceFill(['preferred_locale' => null])->save();
                app()->setLocale($localeManager->normalize($locale));
                $request->session()->flash('toast', [
                    'type' => 'warning',
                    'message' => __('Your selected language is no longer available. The application default language is now used.'),
                ]);
            }
        }

        app()->setLocale($localeManager->normalize($locale));

        return $next($request);
    }
}
