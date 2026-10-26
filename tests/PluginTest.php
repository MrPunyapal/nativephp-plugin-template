<?php

declare(strict_types=1);

use {{ namespace }}\Contracts\{{ plugin }}Contract;
use {{ namespace }}\Facades\{{ plugin }};

it('registers the plugin contract and facade accessor', function (): void {
    $bridge = new class
    {
        /**
         * @param array<string, mixed> $payload
         * @return array<string, mixed>
         */
        public function call(string $function, array $payload): array
        {
            return [
                'function' => $function,
                'payload' => $payload,
                'platform' => 'test',
            ];
        }
    };

    app()->instance('nativephp.mobile.bridge', $bridge);

    expect(app({{ plugin }}Contract::class))->toBe(app('{{ package }}'))
        ->and({{ plugin }}::example(['message' => '{{ description }}']))->toBe([
            'function' => '{{ plugin }}.Example',
            'payload' => ['message' => '{{ description }}'],
            'platform' => 'test',
        ]);
});
