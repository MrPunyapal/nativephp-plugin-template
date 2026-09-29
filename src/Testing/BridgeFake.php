<?php

declare(strict_types=1);

namespace {{ namespace }}\Testing;

use Closure;
use PHPUnit\Framework\Assert;
use RuntimeException;

final class BridgeFake
{
    private bool $preventStrayCalls = false;

    /**
     * @var list<array{function: string, payload: array<string, mixed>}>
     */
    private array $calls = [];

    /**
     * @param array<string, array<string, mixed>|Closure> $responses
     */
    public function __construct(private array $responses = [])
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function call(string $function, array $payload): array
    {
        $this->calls[] = [
            'function' => $function,
            'payload' => $payload,
        ];

        if (array_key_exists($function, $this->responses)) {
            $response = $this->responses[$function];

            return $response instanceof Closure ? $response($payload) : $response;
        }

        if ($this->preventStrayCalls) {
            throw new RuntimeException('The {{ vendor }}/{{ package }} bridge received an unexpected call to ['.$function.']. Stub it or allow stray calls.');
        }

        return [];
    }

    /**
     * @param array<string, mixed>|Closure $response
     */
    public function stub(string $function, array|Closure $response): self
    {
        $this->responses[$function] = $response;

        return $this;
    }

    public function preventStrayCalls(): self
    {
        $this->preventStrayCalls = true;

        return $this;
    }

    /**
     * @return list<array{function: string, payload: array<string, mixed>}>
     */
    public function recorded(?string $function = null): array
    {
        if ($function === null) {
            return $this->calls;
        }

        return array_values(array_filter(
            $this->calls,
            fn (array $call): bool => $call['function'] === $function,
        ));
    }

    public function assertCalled(string $function, ?Closure $callback = null): void
    {
        $calls = $this->recorded($function);

        Assert::assertTrue(
            $calls !== [] && (! $callback instanceof Closure || $this->matchesAny($calls, $callback)),
            "The bridge function [{$function}] was not called."
        );
    }

    public function assertNotCalled(string $function): void
    {
        Assert::assertTrue(
            $this->recorded($function) === [],
            "The bridge function [{$function}] was called unexpectedly."
        );
    }

    public function assertCalledTimes(string $function, int $times): void
    {
        $actual = count($this->recorded($function));

        Assert::assertSame(
            $times,
            $actual,
            "The bridge function [{$function}] was called {$actual} times instead of {$times} times."
        );
    }

    public function assertNothingCalled(): void
    {
        Assert::assertTrue(
            $this->calls === [],
            'The bridge received unexpected calls when none were expected.'
        );
    }

    /**
     * @param list<array{function: string, payload: array<string, mixed>}> $calls
     */
    private function matchesAny(array $calls, Closure $callback): bool
    {
        foreach ($calls as $call) {
            if ($callback($call['payload']) === true) {
                return true;
            }
        }

        return false;
    }
}
