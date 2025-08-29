<?php

namespace App\FeatureFlags;

class ArrayFeatureFlags implements FeatureFlagClient
{
    public function enabled(string $flag, array $context = []): bool
    {
        $flags = config('micro.feature_flags.drivers.array.flags', []);
        return (bool)($flags[$flag] ?? false);
    }
}

