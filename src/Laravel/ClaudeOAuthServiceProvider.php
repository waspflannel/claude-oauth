<?php

namespace Waspflannel\ClaudeOAuth\Laravel;

use Illuminate\Support\ServiceProvider;
use Waspflannel\ClaudeOAuth\ClaudeCode;

final class ClaudeOAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/claude-oauth.php', 'claude-oauth');

        $this->app->singleton(ClaudeCode::class, fn ($app) => new ClaudeCode(
            configDir: $app['config']['claude-oauth.config_dir'],
            token: $app['config']['claude-oauth.token'] ?: null,
            binary: $app['config']['claude-oauth.binary'],
            model: $app['config']['claude-oauth.model'],
            effort: $app['config']['claude-oauth.effort'] ?: null,
            timeoutSeconds: (int) $app['config']['claude-oauth.timeout'],
        ));
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/claude-oauth.php' => config_path('claude-oauth.php'),
        ], 'claude-oauth-config');
    }
}
