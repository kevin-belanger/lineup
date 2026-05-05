<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class ApplicationSettings
{
    public const APP_NAME_KEY = 'app.display_name';

    public const AUTO_CANCEL_REQUESTS_ENABLED_KEY = 'requests.auto_cancel_enabled';

    public const AUTO_CANCEL_REQUESTS_TIME_KEY = 'requests.auto_cancel_time';

    public const DEFAULT_APP_NAME = 'LineUp';

    public const DEFAULT_AUTO_CANCEL_REQUESTS_TIME = '16:30';

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
}
