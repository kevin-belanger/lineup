<?php

namespace App\Services;

class LocaleManager
{
    public const SOURCE_LOCALE = 'en';

    /**
     * @return list<string>
     */
    public function availableLocales(): array
    {
        $this->ensureSourceLocaleFileExists();

        $locales = collect(glob(lang_path('*.json')) ?: [])
            ->map(fn (string $path): string => pathinfo($path, PATHINFO_FILENAME))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $locales !== [] ? $locales : [self::SOURCE_LOCALE];
    }

    public function isValid(?string $locale): bool
    {
        return in_array($locale, $this->availableLocales(), true);
    }

    public function normalize(?string $locale): string
    {
        $locale = trim((string) $locale);

        return $this->isValid($locale) ? $locale : self::SOURCE_LOCALE;
    }

    private function ensureSourceLocaleFileExists(): void
    {
        $path = lang_path(self::SOURCE_LOCALE.'.json');

        if (! file_exists($path)) {
            file_put_contents($path, "{}\n");
        }
    }
}
