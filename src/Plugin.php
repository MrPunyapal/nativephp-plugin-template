<?php

declare(strict_types=1);

namespace {{ namespace }};

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;
use {{ namespace }}\Contracts\{{ plugin }}Contract;

final class Plugin implements {{ plugin }}Contract
{
    public function __construct(
        private readonly Application $app,
    ) {
    }

    public function example(array $payload = []): array
    {
        return $this->callBridge('{{ plugin }}.Example', $payload);
    }

    public function manifest(): array
    {
        $path = dirname(__DIR__).DIRECTORY_SEPARATOR.'nativephp.json';

        if (! File::exists($path)) {
            throw new InvalidArgumentException('The {{ vendor }}/{{ package }} NativePHP manifest is missing.');
        }

        /** @var array<string, mixed> $manifest */
        $manifest = json_decode((string) File::get($path), true, flags: JSON_THROW_ON_ERROR);

        return $manifest;
    }

    public function isAvailable(): bool
    {
        if ($this->app->bound('nativephp.mobile.bridge')) {
            $bridge = $this->app->make('nativephp.mobile.bridge');

            return is_object($bridge) && is_callable([$bridge, 'call']);
        }

        return function_exists('nativephp_call');
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function callBridge(string $function, array $payload): array
    {
        if ($this->app->bound('nativephp.mobile.bridge')) {
            $bridge = $this->app->make('nativephp.mobile.bridge');

            if (is_object($bridge) && is_callable([$bridge, 'call'])) {
                /** @var mixed $response */
                $response = $bridge->call($function, $payload);

                return is_array($response) ? $response : ['value' => $response];
            }
        }

        if (! function_exists('nativephp_call')) {
            throw new RuntimeException('The NativePHP mobile bridge is not available for {{ vendor }}/{{ package }}.');
        }

        $response = nativephp_call($function, json_encode($payload, JSON_THROW_ON_ERROR));

        /** @var mixed $decoded */
        $decoded = json_decode((string) $response, true);

        if (! is_array($decoded)) {
            return ['value' => $decoded];
        }

        if (array_key_exists('data', $decoded)) {
            /** @var mixed $data */
            $data = $decoded['data'];

            return is_array($data) ? $data : ['value' => $data];
        }

        return $decoded;
    }
}
