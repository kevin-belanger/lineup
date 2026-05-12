<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class ApplicationUpdateChecker
{
    private const VALID_TAG_PATTERN = '/^v[0-9]+\.[0-9]+\.[0-9]+.*$/';

    public function check(): ApplicationUpdateStatus
    {
        $installedVersion = (string) config('app.version', 'dev');
        $tagsUrl = $this->tagsApiUrl((string) config('app.repository_url', ''));

        if ($tagsUrl === null) {
            return $this->unavailable($installedVersion);
        }

        try {
            $response = Http::timeout(3)
                ->acceptJson()
                ->withUserAgent('LineUp update checker')
                ->get($tagsUrl);

            if (! $response->successful()) {
                return $this->unavailable($installedVersion);
            }

            $latestVersion = $this->latestVersionTag($response->json());

            if ($latestVersion === null) {
                return $this->unavailable($installedVersion);
            }

            $comparisonAvailable = $this->isValidVersionTag($installedVersion);

            return new ApplicationUpdateStatus(
                installedVersion: $installedVersion,
                latestVersion: $latestVersion,
                checked: true,
                comparisonAvailable: $comparisonAvailable,
                updateAvailable: $comparisonAvailable && $this->isNewer($latestVersion, $installedVersion),
            );
        } catch (Throwable) {
            return $this->unavailable($installedVersion);
        }
    }

    private function unavailable(string $installedVersion): ApplicationUpdateStatus
    {
        return new ApplicationUpdateStatus(
            installedVersion: $installedVersion,
            latestVersion: null,
            checked: false,
            comparisonAvailable: false,
            updateAvailable: false,
        );
    }

    private function tagsApiUrl(string $repositoryUrl): ?string
    {
        $path = trim((string) parse_url($repositoryUrl, PHP_URL_PATH), '/');

        if ($path === '') {
            return null;
        }

        $path = preg_replace('/\.git$/', '', $path) ?? $path;
        [$owner, $repository] = array_pad(explode('/', $path, 3), 2, null);

        if ($owner === null || $repository === null) {
            return null;
        }

        return sprintf('https://api.github.com/repos/%s/%s/tags?per_page=100', $owner, $repository);
    }

    private function latestVersionTag(mixed $tags): ?string
    {
        if (! is_array($tags)) {
            return null;
        }

        $versionTags = collect($tags)
            ->pluck('name')
            ->filter(fn (mixed $tag): bool => is_string($tag) && $this->isValidVersionTag($tag))
            ->values()
            ->all();

        if ($versionTags === []) {
            return null;
        }

        usort($versionTags, fn (string $a, string $b): int => version_compare(
            $this->normalizeVersion($b),
            $this->normalizeVersion($a),
        ));

        return $versionTags[0];
    }

    private function isValidVersionTag(string $version): bool
    {
        return preg_match(self::VALID_TAG_PATTERN, $version) === 1;
    }

    private function isNewer(string $latestVersion, string $installedVersion): bool
    {
        return version_compare(
            $this->normalizeVersion($latestVersion),
            $this->normalizeVersion($installedVersion),
            '>',
        );
    }

    private function normalizeVersion(string $version): string
    {
        return preg_replace('/^v/', '', $version) ?? $version;
    }
}
