<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class ApplicationUpdateChecker
{
    private const VALID_TAG_PATTERN = '/^v[0-9]+\.[0-9]+\.[0-9]+.*$/';
    private const BRANCH_VERSION_PATTERN = '/^(.+) ([0-9a-f]{7,40})$/i';

    public function check(): ApplicationUpdateStatus
    {
        $installedVersion = (string) config('app.version', 'dev');
        $branchVersion = $this->branchVersion($installedVersion);

        if ($branchVersion !== null) {
            return new ApplicationUpdateStatus(
                installedVersion: $installedVersion,
                latestVersion: null,
                checked: true,
                comparisonAvailable: false,
                updateAvailable: false,
                installedBranch: $branchVersion['branch'],
                installedCommit: $branchVersion['commit'],
            );
        }

        $releaseUrl = $this->latestReleaseApiUrl((string) config('app.repository_url', ''));

        if ($releaseUrl === null) {
            return $this->unavailable($installedVersion);
        }

        try {
            $response = Http::timeout(3)
                ->acceptJson()
                ->withUserAgent('LineUp update checker')
                ->get($releaseUrl);

            if (! $response->successful()) {
                return $this->unavailable($installedVersion);
            }

            $latestVersion = $this->latestReleaseTag($response->json());

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

    private function latestReleaseApiUrl(string $repositoryUrl): ?string
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

        return sprintf('https://api.github.com/repos/%s/%s/releases/latest', $owner, $repository);
    }

    /**
     * @return array{branch: string, commit: string}|null
     */
    private function branchVersion(string $version): ?array
    {
        if (preg_match(self::BRANCH_VERSION_PATTERN, $version, $matches) !== 1) {
            return null;
        }

        return [
            'branch' => $matches[1],
            'commit' => $matches[2],
        ];
    }

    private function latestReleaseTag(mixed $release): ?string
    {
        if (! is_array($release)) {
            return null;
        }

        $tag = $release['tag_name'] ?? null;

        if (! is_string($tag) || ! $this->isValidVersionTag($tag)) {
            return null;
        }

        return $tag;
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
