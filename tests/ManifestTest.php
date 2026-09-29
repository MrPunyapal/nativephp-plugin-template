<?php

declare(strict_types=1);

use {{ namespace }}\Support\Manifest;

it('ships a valid NativePHP manifest', function (): void {
    $path = dirname(__DIR__).'/nativephp.json';

    expect($path)->toBeFile();

    /** @var array<string, mixed> $manifest */
    $manifest = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    $typedManifest = new Manifest($manifest);

    expect($typedManifest->namespace())->toBe('{{ plugin }}')
        ->and($typedManifest->bridgeFunctions())->toHaveCount(1)
        ->and($typedManifest->bridgeFunctions()[0]['name'])->toBe('{{ plugin }}.Example')
        ->and($typedManifest->bridgeFunctions()[0]['android'])->toContain('{{ plugin }}Functions.Example')
        ->and($typedManifest->bridgeFunctions()[0]['ios'])->toBe('{{ plugin }}Functions.Example');
});

it('documents every required replacement placeholder', function (): void {
    $files = [
        dirname(__DIR__).'/composer.json',
        dirname(__DIR__).'/nativephp.json',
        dirname(__DIR__).'/docs/manifest-fields.md',
    ];

    foreach (['{{ vendor }}', '{{ package }}', '{{ plugin }}', '{{ namespace }}', '{{ description }}'] as $placeholder) {
        expect(implode("\n", array_map(fn (string $file): string => (string) file_get_contents($file), $files)))
            ->toContain($placeholder);
    }
});
