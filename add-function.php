#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Scaffold a new NativePHP bridge function into an already configured plugin.
 *
 * This file ships inside the template but runs AFTER configure.php, inside
 * the developer's already-configured plugin repository, and is meant to be
 * run repeatedly (once per new bridge function). Everything it needs — the
 * bridge namespace, the PHP namespace, the contract/facade/plugin paths, the
 * native file locations — is discovered at runtime from nativephp.json,
 * composer.json, and the src/ and resources/ trees, so this file carries no
 * baked-in coupling to any one plugin and configure.php never has to touch
 * it. See CONTRIBUTING.md for the design notes on why.
 */
final class FunctionScaffolder
{
    /**
     * PHP keywords that cannot safely become a method name.
     *
     * @see https://www.php.net/manual/en/reserved.keywords.php (pragmatic subset)
     */
    private const PHP_RESERVED = [
        'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch',
        'class', 'clone', 'const', 'continue', 'declare', 'default', 'do',
        'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach',
        'endif', 'endswitch', 'endwhile', 'eval', 'exit', 'extends', 'final',
        'finally', 'fn', 'for', 'foreach', 'function', 'global', 'goto', 'if',
        'implements', 'include', 'include_once', 'instanceof', 'insteadof',
        'interface', 'isset', 'list', 'match', 'namespace', 'new', 'or', 'print',
        'private', 'protected', 'public', 'readonly', 'require', 'require_once',
        'return', 'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use',
        'var', 'while', 'xor', 'yield',
    ];

    /**
     * Kotlin hard keywords, since the function name also becomes a nested
     * class name in the Android bridge file.
     */
    private const KOTLIN_RESERVED = [
        'as', 'break', 'class', 'continue', 'do', 'else', 'false', 'for',
        'fun', 'if', 'in', 'interface', 'is', 'null', 'object', 'package',
        'return', 'super', 'this', 'throw', 'true', 'try', 'typealias',
        'typeof', 'val', 'var', 'when', 'while',
    ];

    /**
     * Swift reserved words used in declarations, since the function name also
     * becomes a nested class name in the iOS bridge file.
     */
    private const SWIFT_RESERVED = [
        'associatedtype', 'class', 'deinit', 'enum', 'extension', 'fileprivate',
        'func', 'import', 'init', 'inout', 'internal', 'let', 'open', 'operator',
        'private', 'protocol', 'public', 'rethrows', 'static', 'struct',
        'subscript', 'typealias', 'var', 'self', 'Self', 'Type', 'Protocol',
    ];

    /**
     * @var list<string>
     */
    private array $warnings = [];

    /**
     * @var array<string, bool|string>
     */
    private array $summary = [];

    /**
     * @param array<string, string|bool> $options
     */
    public function __construct(
        private readonly array $options,
        private readonly string $root,
    ) {}

    public function run(): void
    {
        $this->writeln('');
        $this->writeln($this->bold('NativePHP Bridge Function Scaffolder'));
        $this->writeln('Add a new bridge function to an already configured plugin.');
        $this->writeln('');

        $nativephpPath = $this->root.'/nativephp.json';
        $composerPath = $this->root.'/composer.json';

        if (! file_exists($nativephpPath) || ! file_exists($composerPath)) {
            $this->fail('nativephp.json or composer.json is missing. Run this script from the plugin root.');
        }

        $nativephpRaw = (string) file_get_contents($nativephpPath);

        if (str_contains($nativephpRaw, '{{ ')) {
            $this->fail('This repository is still an unconfigured template. Run "php configure.php" first, then re-run add-function.php.');
        }

        /** @var array<string, mixed> $manifest */
        $manifest = json_decode($nativephpRaw, true) ?? [];
        $bridgeNamespace = is_string($manifest['namespace'] ?? null) ? $manifest['namespace'] : '';

        if ($bridgeNamespace === '') {
            $this->fail('nativephp.json is missing a "namespace" value.');
        }

        /** @var array<string, mixed> $composer */
        $composer = json_decode((string) file_get_contents($composerPath), true) ?? [];
        $phpNamespace = $this->discoverPhpNamespace($composer);

        if ($phpNamespace === null) {
            $this->fail('Could not find a "src/" entry under composer.json "autoload.psr-4".');
        }

        $contractPath = $this->findSingleFile('src/Contracts', 'Contract.php', 'Contracts');
        $facadePath = $this->findSingleFile('src/Facades', '.php', 'Facades');
        $pluginPath = $this->root.'/src/Plugin.php';

        if (! file_exists($pluginPath)) {
            $this->fail('Expected src/Plugin.php. Add the new method manually.');
        }

        $name = $this->option('name', $this->ask('Bridge function name (PascalCase, e.g. Level)', ''));
        $description = $this->option('description', $this->ask('Description', ''));
        $skipAndroid = $this->hasOption('skip-android');
        $skipIos = $this->hasOption('skip-ios');
        $dryRun = $this->hasOption('dry-run');

        $this->validateName($name);
        $methodName = lcfirst($name);
        $this->validateReservedWords($methodName, $name, $skipAndroid, $skipIos);

        $fqName = "{$bridgeNamespace}.{$name}";

        /** @var array<int, array<string, mixed>> $bridgeFunctions */
        $bridgeFunctions = is_array($manifest['bridge_functions'] ?? null) ? $manifest['bridge_functions'] : [];

        $this->rejectDuplicates($fqName, $bridgeFunctions, $contractPath, $methodName);

        $exampleEntry = $this->findExampleEntry($bridgeFunctions, $bridgeNamespace);

        $androidTarget = $skipAndroid ? null : $this->deriveTarget(
            is_string($exampleEntry['android'] ?? null) ? $exampleEntry['android'] : null,
            $name,
            fn (): string => $this->fallbackAndroidTarget($bridgeNamespace, $name),
        );

        $iosTarget = $skipIos ? null : $this->deriveTarget(
            is_string($exampleEntry['ios'] ?? null) ? $exampleEntry['ios'] : null,
            $name,
            fn (): string => "{$bridgeNamespace}Functions.{$name}",
        );

        $writes = [];

        $writes[$nativephpPath] = $this->buildManifest($manifest, $bridgeFunctions, $fqName, $androidTarget, $iosTarget, $description);
        $writes[$contractPath] = $this->addContractMethod((string) file_get_contents($contractPath), $fqName, $methodName);
        $writes[$pluginPath] = $this->addPluginMethod((string) file_get_contents($pluginPath), $fqName, $methodName);
        $writes[$facadePath] = $this->addFacadeMethod((string) file_get_contents($facadePath), $methodName);

        $this->summary['manifest'] = true;
        $this->summary['contract'] = true;
        $this->summary['plugin'] = true;
        $this->summary['facade'] = true;

        if ($skipAndroid) {
            $this->summary['kotlin'] = 'skipped';
        } else {
            $kotlinResult = $this->addKotlinFunction($name);

            if ($kotlinResult !== null) {
                [$kotlinPath, $kotlinContents] = $kotlinResult;
                $writes[$kotlinPath] = $kotlinContents;
                $this->summary['kotlin'] = true;
            } else {
                $this->summary['kotlin'] = 'manual';
            }
        }

        if ($skipIos) {
            $this->summary['swift'] = 'skipped';
        } else {
            $swiftResult = $this->addSwiftFunction($name);

            if ($swiftResult !== null) {
                [$swiftPath, $swiftContents] = $swiftResult;
                $writes[$swiftPath] = $swiftContents;
                $this->summary['swift'] = true;
            } else {
                $this->summary['swift'] = 'manual';
            }
        }

        $testPath = $this->root.'/tests/'.$name.'Test.php';
        $writes[$testPath] = $this->buildTest($phpNamespace, $bridgeNamespace, $name, $methodName);
        $this->summary['test'] = true;

        if ($dryRun) {
            $this->printDryRun($writes);

            return;
        }

        foreach ($writes as $path => $contents) {
            $directory = dirname($path);

            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            file_put_contents($path, $contents);
        }

        $this->printSummary($fqName, $writes, $androidTarget, $iosTarget, $skipAndroid, $skipIos);
    }

    private function validateName(string $name): void
    {
        if ($name === '') {
            $this->fail('--name is required.');
        }

        if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $name) !== 1) {
            $this->fail("Invalid function name [{$name}]. Use PascalCase, starting with an uppercase letter (e.g. Level, ChargeState).");
        }
    }

    private function validateReservedWords(string $methodName, string $name, bool $skipAndroid, bool $skipIos): void
    {
        if (in_array(strtolower($methodName), self::PHP_RESERVED, true)) {
            $this->fail("[{$name}] camelCases to the PHP reserved word [{$methodName}] and cannot be used as a method name. Choose a different name.");
        }

        if (! $skipAndroid && in_array(strtolower($name), self::KOTLIN_RESERVED, true)) {
            $this->fail("[{$name}] collides with a Kotlin reserved word. Choose a different name or pass --skip-android.");
        }

        if (! $skipIos && in_array(strtolower($name), self::SWIFT_RESERVED, true)) {
            $this->fail("[{$name}] collides with a Swift reserved word. Choose a different name or pass --skip-ios.");
        }
    }

    /**
     * @param array<int, array<string, mixed>> $bridgeFunctions
     */
    private function rejectDuplicates(string $fqName, array $bridgeFunctions, string $contractPath, string $methodName): void
    {
        foreach ($bridgeFunctions as $entry) {
            if (($entry['name'] ?? null) === $fqName) {
                $this->fail("[{$fqName}] already exists in nativephp.json. Choose a different name.");
            }
        }

        $contractContents = (string) file_get_contents($contractPath);

        if (preg_match('/\bfunction\s+'.preg_quote($methodName, '/').'\s*\(/', $contractContents) === 1) {
            $this->fail("A method named [{$methodName}] already exists on the contract. Choose a different name.");
        }
    }

    /**
     * @param array<int, array<string, mixed>> $bridgeFunctions
     * @return array<string, mixed>
     */
    private function findExampleEntry(array $bridgeFunctions, string $bridgeNamespace): array
    {
        foreach ($bridgeFunctions as $entry) {
            if (($entry['name'] ?? null) === "{$bridgeNamespace}.Example") {
                return $entry;
            }
        }

        return $bridgeFunctions[0] ?? [];
    }

    private function deriveTarget(?string $exampleValue, string $name, Closure $fallback): string
    {
        if ($exampleValue !== null && str_ends_with($exampleValue, 'Example')) {
            return substr($exampleValue, 0, -strlen('Example')).$name;
        }

        return $fallback();
    }

    private function fallbackAndroidTarget(string $bridgeNamespace, string $name): string
    {
        $kotlinPath = $this->root.'/resources/android/'.$bridgeNamespace.'Functions.kt';
        $package = 'com.plugin';

        if (file_exists($kotlinPath)) {
            $contents = (string) file_get_contents($kotlinPath);

            if (preg_match('/^package\s+(.+)$/m', $contents, $matches) === 1) {
                $package = trim($matches[1]);
            }
        }

        return "{$package}.{$bridgeNamespace}Functions.{$name}";
    }

    /**
     * @param array<string, mixed> $manifest
     * @param array<int, array<string, mixed>> $bridgeFunctions
     */
    private function buildManifest(
        array $manifest,
        array $bridgeFunctions,
        string $fqName,
        ?string $androidTarget,
        ?string $iosTarget,
        string $description,
    ): string {
        $entry = ['name' => $fqName];

        if ($androidTarget !== null) {
            $entry['android'] = $androidTarget;
        }

        if ($iosTarget !== null) {
            $entry['ios'] = $iosTarget;
        }

        $entry['description'] = $description;

        $bridgeFunctions[] = $entry;
        $manifest['bridge_functions'] = $bridgeFunctions;

        return json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    }

    private function addContractMethod(string $contents, string $fqName, string $methodName): string
    {
        $member = <<<PHP
                /**
                 * Invoke the {$fqName} bridge function and return the normalized native response.
                 *
                 * @param array<string, mixed> \$payload
                 * @return array<string, mixed>
                 */
                public function {$methodName}(array \$payload = []): array;
            PHP;

        return $this->insertBeforeFinalBrace($contents, $member);
    }

    private function addPluginMethod(string $contents, string $fqName, string $methodName): string
    {
        $member = <<<PHP
                public function {$methodName}(array \$payload = []): array
                {
                    return \$this->callBridge('{$fqName}', \$payload);
                }
            PHP;

        return $this->insertBeforeFinalBrace($contents, $member);
    }

    private function addFacadeMethod(string $contents, string $methodName): string
    {
        $lines = explode("\n", $contents);
        $lastMethodLine = null;

        foreach ($lines as $index => $line) {
            if (str_contains($line, '@method')) {
                $lastMethodLine = $index;
            }
        }

        if ($lastMethodLine === null) {
            $this->fail('Could not find an existing "@method" line in the facade docblock.');
        }

        $newLine = " * @method static array<string, mixed> {$methodName}(array<string, mixed> \$payload = [])";
        array_splice($lines, $lastMethodLine + 1, 0, [$newLine]);

        return implode("\n", $lines);
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function addKotlinFunction(string $name): ?array
    {
        $matches = glob($this->root.'/resources/android/*.kt') ?: [];

        if (count($matches) !== 1) {
            $this->warnings[] = 'Android bridge file not found under resources/android — add the '.$name.' class to it manually.';

            return null;
        }

        $stubPath = $this->root.'/stubs/bridge-function-android.stub';

        if (! file_exists($stubPath)) {
            $this->warnings[] = 'stubs/bridge-function-android.stub is missing — add the '.$name.' class to '.$matches[0].' manually.';

            return null;
        }

        try {
            $block = $this->extractBracedBlock((string) file_get_contents($stubPath), 'class {{ function }}');
        } catch (RuntimeException $exception) {
            $this->warnings[] = 'Could not locate the Android bridge function anchor ('.$exception->getMessage().') — add the '.$name.' class to '.$matches[0].' manually.';

            return null;
        }

        $block = str_replace('{{ function }}', $name, $block);
        $contents = (string) file_get_contents($matches[0]);

        try {
            $updated = $this->insertBeforeFinalBrace($contents, $block);
        } catch (RuntimeException $exception) {
            $this->warnings[] = 'Could not find an insertion point in '.$matches[0].' ('.$exception->getMessage().') — add the '.$name.' class manually.';

            return null;
        }

        return [$matches[0], $updated];
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function addSwiftFunction(string $name): ?array
    {
        $matches = glob($this->root.'/resources/ios/*.swift') ?: [];

        if (count($matches) !== 1) {
            $this->warnings[] = 'iOS bridge file not found under resources/ios — add the '.$name.' class to it manually.';

            return null;
        }

        $stubPath = $this->root.'/stubs/bridge-function-ios.stub';

        if (! file_exists($stubPath)) {
            $this->warnings[] = 'stubs/bridge-function-ios.stub is missing — add the '.$name.' class to '.$matches[0].' manually.';

            return null;
        }

        try {
            $block = $this->extractBracedBlock((string) file_get_contents($stubPath), 'class {{ function }}');
        } catch (RuntimeException $exception) {
            $this->warnings[] = 'Could not locate the iOS bridge function anchor ('.$exception->getMessage().') — add the '.$name.' class to '.$matches[0].' manually.';

            return null;
        }

        $block = str_replace('{{ function }}', $name, $block);
        $contents = (string) file_get_contents($matches[0]);

        try {
            $updated = $this->insertBeforeFinalBrace($contents, $block);
        } catch (RuntimeException $exception) {
            $this->warnings[] = 'Could not find an insertion point in '.$matches[0].' ('.$exception->getMessage().') — add the '.$name.' class manually.';

            return null;
        }

        return [$matches[0], $updated];
    }

    private function buildTest(string $phpNamespace, string $bridgeNamespace, string $name, string $methodName): string
    {
        $stubPath = $this->root.'/stubs/bridge-function-test.stub';
        $stub = (string) file_get_contents($stubPath);

        return str_replace(
            ['{{ namespace }}', '{{ plugin }}', '{{ function_camel }}', '{{ function }}'],
            [$phpNamespace, $bridgeNamespace, $methodName, $name],
            $stub,
        );
    }

    /**
     * Insert a member before the file's final closing brace, preserving a
     * single blank line before it and the file's trailing newline. Only
     * safe for single-class/interface files with one top-level brace pair.
     */
    private function insertBeforeFinalBrace(string $contents, string $block): string
    {
        $body = rtrim($contents);
        $pos = strrpos($body, '}');

        if ($pos === false) {
            throw new RuntimeException('no closing brace found');
        }

        $head = rtrim(substr($body, 0, $pos), "\n");

        return $head."\n\n".rtrim($block)."\n".substr($body, $pos)."\n";
    }

    /**
     * Extract a balanced-brace block starting at the line containing
     * $anchor, inclusive of its closing brace.
     */
    private function extractBracedBlock(string $contents, string $anchor): string
    {
        $lines = explode("\n", $contents);
        $start = null;

        foreach ($lines as $index => $line) {
            if (str_contains($line, $anchor)) {
                $start = $index;

                break;
            }
        }

        if ($start === null) {
            throw new RuntimeException("anchor [{$anchor}] not found");
        }

        $depth = 0;
        $end = null;

        for ($i = $start; $i < count($lines); $i++) {
            $depth += substr_count($lines[$i], '{');
            $depth -= substr_count($lines[$i], '}');

            if ($depth === 0 && $i > $start) {
                $end = $i;

                break;
            }
        }

        if ($end === null) {
            throw new RuntimeException("unbalanced braces after anchor [{$anchor}]");
        }

        return implode("\n", array_slice($lines, $start, $end - $start + 1));
    }

    /**
     * @param array<string, mixed> $composer
     */
    private function discoverPhpNamespace(array $composer): ?string
    {
        $psr4 = $composer['autoload']['psr-4'] ?? null;

        if (! is_array($psr4)) {
            return null;
        }

        foreach ($psr4 as $namespace => $path) {
            if ($path === 'src/' && is_string($namespace)) {
                return rtrim($namespace, '\\');
            }
        }

        return null;
    }

    private function findSingleFile(string $relativeDirectory, string $suffix, string $label): string
    {
        $matches = glob($this->root.'/'.$relativeDirectory.'/*'.$suffix) ?: [];

        if (count($matches) !== 1) {
            $this->fail('Expected exactly one file under '.$relativeDirectory.' but found '.count($matches).'. ('.$label.')');
        }

        return $matches[0];
    }

    /**
     * @param array<string, string> $writes
     */
    private function printDryRun(array $writes): void
    {
        $this->writeln($this->bold('Dry run — no files were changed.'));
        $this->writeln('');

        foreach ($writes as $path => $contents) {
            $exists = file_exists($path);
            $this->writeln($this->bold(($exists ? 'Would modify: ' : 'Would create: ').$this->relative($path)));
        }

        if ($this->warnings !== []) {
            $this->writeln('');
            $this->writeln($this->bold('Warnings:'));

            foreach ($this->warnings as $warning) {
                $this->writeln('- '.$warning);
            }
        }
    }

    /**
     * @param array<string, string> $writes
     */
    private function printSummary(string $fqName, array $writes, ?string $androidTarget, ?string $iosTarget, bool $skipAndroid, bool $skipIos): void
    {
        $this->writeln('');
        $this->writeln($this->green($this->bold('Bridge function added: '.$fqName)));
        $this->writeln('');
        $this->writeln('Files changed:');

        foreach (array_keys($writes) as $path) {
            $this->writeln('- '.$this->relative($path));
        }

        if ($this->warnings !== []) {
            $this->writeln('');
            $this->writeln($this->bold('Warnings:'));

            foreach ($this->warnings as $warning) {
                $this->writeln('- '.$warning);
            }
        }

        $this->writeln('');
        $this->writeln($this->bold('Next steps:'));

        $steps = [];

        if (! $skipAndroid && ($this->summary['kotlin'] ?? null) === true) {
            $steps[] = 'Implement the native logic in resources/android/*.kt ('.$androidTarget.').';
        }

        if (! $skipIos && ($this->summary['swift'] ?? null) === true) {
            $steps[] = 'Implement the native logic in resources/ios/*.swift ('.$iosTarget.').';
        }

        $steps[] = 'Run composer test.';

        foreach ($steps as $index => $step) {
            $this->writeln(($index + 1).'. '.$step);
        }

        $this->writeln('');
    }

    private function relative(string $path): string
    {
        return str_replace($this->root.'/', '', $path);
    }

    private function option(string $name, string $default = ''): string
    {
        $value = $this->options[$name] ?? $default;

        return is_string($value) ? $value : $default;
    }

    private function hasOption(string $name): bool
    {
        return array_key_exists($name, $this->options) && $this->options[$name] !== false;
    }

    private function ask(string $question, string $default = ''): string
    {
        if ($this->hasOption('no-interaction')) {
            return $default;
        }

        $suffix = $default !== '' ? " [{$default}]" : '';
        $answer = readline("{$question}{$suffix}: ");
        $answer = trim((string) $answer);

        return $answer !== '' ? $answer : $default;
    }

    private function fail(string $message): never
    {
        fwrite(STDERR, $this->red($message).PHP_EOL);
        exit(1);
    }

    private function writeln(string $message): void
    {
        fwrite(STDOUT, $message.PHP_EOL);
    }

    private function bold(string $message): string
    {
        return $this->ansi('1', $message);
    }

    private function green(string $message): string
    {
        return $this->ansi('32', $message);
    }

    private function red(string $message): string
    {
        return $this->ansi('31', $message);
    }

    private function ansi(string $code, string $message): string
    {
        if (! function_exists('posix_isatty') || ! posix_isatty(STDOUT)) {
            return $message;
        }

        return "\033[{$code}m{$message}\033[0m";
    }
}

/**
 * @param list<string> $arguments
 * @return array<string, string|bool>
 */
function parseOptions(array $arguments): array
{
    $options = [];

    foreach (array_slice($arguments, 1) as $argument) {
        if (! str_starts_with($argument, '--')) {
            continue;
        }

        $argument = substr($argument, 2);

        if (! str_contains($argument, '=')) {
            $options[$argument] = true;

            continue;
        }

        [$key, $value] = explode('=', $argument, 2);
        $options[$key] = $value;
    }

    return $options;
}

(new FunctionScaffolder(parseOptions($argv), __DIR__))->run();
