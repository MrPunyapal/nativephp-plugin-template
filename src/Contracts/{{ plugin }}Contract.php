<?php

declare(strict_types=1);

namespace {{ namespace }}\Contracts;

interface {{ plugin }}Contract
{
    /** @param array<string, mixed> $payload */
    public function example(array $payload = []): array;

    /** @return array<string, mixed> */
    public function manifest(): array;

    public function isAvailable(): bool;
}
