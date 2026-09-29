<?php

declare(strict_types=1);

namespace {{ namespace }}\Providers;

use Illuminate\Support\ServiceProvider;
use {{ namespace }}\Contracts\{{ plugin }}Contract;
use {{ namespace }}\Plugin;

final class {{ plugin }}ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('{{ package }}', fn ($app): Plugin => new Plugin($app));
        $this->app->alias('{{ package }}', {{ plugin }}Contract::class);
    }

    public function boot(): void
    {
        $this->publishes([
            dirname(__DIR__, 2).'/nativephp.json' => base_path('nativephp/{{ package }}.json'),
        ], '{{ package }}-nativephp-manifest');
    }
}
