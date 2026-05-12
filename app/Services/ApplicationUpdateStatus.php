<?php

namespace App\Services;

class ApplicationUpdateStatus
{
    public function __construct(
        public readonly string $installedVersion,
        public readonly ?string $latestVersion,
        public readonly bool $checked,
        public readonly bool $comparisonAvailable,
        public readonly bool $updateAvailable,
    ) {}

    public function isUpToDate(): bool
    {
        return $this->checked
            && $this->comparisonAvailable
            && $this->latestVersion !== null
            && ! $this->updateAvailable;
    }
}
