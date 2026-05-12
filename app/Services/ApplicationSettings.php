<?php

namespace App\Services;

use App\Models\Setting;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;

class ApplicationSettings
{
    public const APP_NAME_KEY = 'app.display_name';

    public const DEFAULT_LOCALE_KEY = 'app.default_locale';

    public const AUTO_CANCEL_REQUESTS_ENABLED_KEY = 'requests.auto_cancel_enabled';

    public const AUTO_CANCEL_REQUESTS_TIME_KEY = 'requests.auto_cancel_time';

    public const TIMEZONE_KEY = 'app.timezone';

    public const DEFAULT_APP_NAME = 'LineUp';

    public const DEFAULT_AUTO_CANCEL_REQUESTS_TIME = '16:30';

    public const DEFAULT_TIMEZONE = 'America/Toronto';

    public const AVAILABLE_TIMEZONES = [
        'America/Toronto',
        'America/New_York',
        'America/Chicago',
        'America/Denver',
        'America/Vancouver',
        'UTC',
    ];

    public function displayName(): string
    {
        return Cache::rememberForever(self::APP_NAME_KEY, function (): string {
            $value = Setting::query()->where('key', self::APP_NAME_KEY)->value('value');

            return $this->normalizeDisplayName($value);
        });
    }

    public function updateDisplayName(string $displayName): void
    {
        Setting::query()->updateOrCreate(
            ['key' => self::APP_NAME_KEY],
            ['value' => $this->normalizeDisplayName($displayName)],
        );

        Cache::forget(self::APP_NAME_KEY);
    }

    public function defaultLocale(): string
    {
        return Cache::rememberForever(self::DEFAULT_LOCALE_KEY, function (): string {
            $value = Setting::query()->where('key', self::DEFAULT_LOCALE_KEY)->value('value');
            $locale = app(LocaleManager::class)->normalize($value);

            if ($value !== null && $value !== $locale) {
                Setting::query()->updateOrCreate(
                    ['key' => self::DEFAULT_LOCALE_KEY],
                    ['value' => $locale],
                );
            }

            return $locale;
        });
    }

    public function updateDefaultLocale(?string $locale): void
    {
        Setting::query()->updateOrCreate(
            ['key' => self::DEFAULT_LOCALE_KEY],
            ['value' => app(LocaleManager::class)->normalize($locale)],
        );

        Cache::forget(self::DEFAULT_LOCALE_KEY);
    }

    public function autoCancelRequestsEnabled(): bool
    {
        return Cache::rememberForever(self::AUTO_CANCEL_REQUESTS_ENABLED_KEY, function (): bool {
            return Setting::query()
                ->where('key', self::AUTO_CANCEL_REQUESTS_ENABLED_KEY)
                ->value('value') === '1';
        });
    }

    public function autoCancelRequestsTime(): string
    {
        return Cache::rememberForever(self::AUTO_CANCEL_REQUESTS_TIME_KEY, function (): string {
            $value = Setting::query()->where('key', self::AUTO_CANCEL_REQUESTS_TIME_KEY)->value('value');

            return $this->normalizeAutoCancelRequestsTime($value);
        });
    }

    public function updateAutoCancelRequests(bool $enabled, ?string $time): void
    {
        Setting::query()->updateOrCreate(
            ['key' => self::AUTO_CANCEL_REQUESTS_ENABLED_KEY],
            ['value' => $enabled ? '1' : '0'],
        );

        Setting::query()->updateOrCreate(
            ['key' => self::AUTO_CANCEL_REQUESTS_TIME_KEY],
            ['value' => $this->normalizeAutoCancelRequestsTime($time)],
        );

        Cache::forget(self::AUTO_CANCEL_REQUESTS_ENABLED_KEY);
        Cache::forget(self::AUTO_CANCEL_REQUESTS_TIME_KEY);
    }

    public function timezone(): string
    {
        return Cache::rememberForever(self::TIMEZONE_KEY, function (): string {
            $value = Setting::query()->where('key', self::TIMEZONE_KEY)->value('value');

            return $this->normalizeTimezone($value);
        });
    }

    public function updateTimezone(string $timezone): void
    {
        Setting::query()->updateOrCreate(
            ['key' => self::TIMEZONE_KEY],
            ['value' => $this->normalizeTimezone($timezone)],
        );

        Cache::forget(self::TIMEZONE_KEY);
    }

    public function logoPath(): string
    {
        return 'logo.png';
    }

    public function faviconPath(): string
    {
        return $this->logoPath();
    }

    private function normalizeDisplayName(?string $displayName): string
    {
        $displayName = trim((string) $displayName);

        return $displayName !== '' ? $displayName : self::DEFAULT_APP_NAME;
    }

    private function normalizeAutoCancelRequestsTime(?string $time): string
    {
        $time = trim((string) $time);

        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) === 1
            ? $time
            : self::DEFAULT_AUTO_CANCEL_REQUESTS_TIME;
    }

    private function normalizeTimezone(?string $timezone): string
    {
        $timezone = trim((string) $timezone);

        return in_array($timezone, DateTimeZone::listIdentifiers(), true)
            ? $timezone
            : self::DEFAULT_TIMEZONE;
    }
}
