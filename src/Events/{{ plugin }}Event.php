<?php

declare(strict_types=1);

namespace {{ namespace }}\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class {{ plugin }}Event
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $name,
        public readonly array $payload = [],
    ) {
    }
}
