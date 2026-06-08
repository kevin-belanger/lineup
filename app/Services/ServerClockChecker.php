<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Throwable;

class ServerClockChecker
{
    private const REFERENCE_URL = 'https://www.microsoft.com';

    private const MAX_ALLOWED_DRIFT_SECONDS = 90;

    public function hasWarning(): bool
    {

        try {
            $response = Http::timeout(2)
                ->withUserAgent('LineUp server clock checker')
                ->head(self::REFERENCE_URL);

            $dateHeader = $response->header('Date');

            if ($dateHeader === null || $dateHeader === '') {
                return false;
            }

            $referenceTimestamp = CarbonImmutable::parse($dateHeader)->timestamp;
        } catch (Throwable) {
            return false;
        }

        return abs(now()->timestamp - $referenceTimestamp) >= self::MAX_ALLOWED_DRIFT_SECONDS;
    }
}
