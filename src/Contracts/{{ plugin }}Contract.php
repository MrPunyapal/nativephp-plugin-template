<?php

declare(strict_types=1);

namespace {{ namespace }}\Contracts;

interface {{ plugin }}Contract
{
    /**
     * Invoke the template bridge function and return the normalized native response.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function example(array $payload = []): array;

    /**
     * Return the plugin manifest loaded from nativephp.json.
     *
     * @return array<string, mixed>
     */
    public function manifest(): array;

    /**
     * Determine whether the NativePHP mobile bridge is available in the container.
     */
    public function isAvailable(): bool;
}
