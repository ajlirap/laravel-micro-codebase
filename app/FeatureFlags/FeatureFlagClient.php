<?php

namespace App\FeatureFlags;

interface FeatureFlagClient
{
    public function enabled(string $flag, array $context = []): bool;
}

