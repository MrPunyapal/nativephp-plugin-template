<?php

declare(strict_types=1);

namespace {{ namespace }}\Support;

use InvalidArgumentException;

final class Manifest
{
    /**
     * @param array<string, mixed> $manifest
     */
    public function __construct(
        private readonly array $manifest,
    ) {
    }

    public function namespace(): string
    {
        $namespace = $this->manifest['namespace'] ?? null;

        if (! is_string($namespace) || $namespace === '') {
            throw new InvalidArgumentException('The {{ vendor }}/{{ package }} manifest must define a non-empty namespace.');
        }

        return $namespace;
    }

    /**
     * @return list<array{name: string, android?: string, ios?: string, description?: string}>
     */
    public function bridgeFunctions(): array
    {
        $functions = $this->manifest['bridge_functions'] ?? [];

        if (! is_array($functions)) {
            throw new InvalidArgumentException('The {{ vendor }}/{{ package }} manifest bridge_functions field must be an array.');
        }

        /** @var list<array{name: string, android?: string, ios?: string, description?: string}> $functions */
        return $functions;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->manifest;
    }
}
