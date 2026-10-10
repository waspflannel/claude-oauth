<?php

namespace Waspflannel\ClaudeOAuth\Tests\Laravel;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase;
use Waspflannel\ClaudeOAuth\ClaudeCode;
use Waspflannel\ClaudeOAuth\Laravel\ClaudeOAuthServiceProvider;

final class ClaudeOAuthServiceProviderTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/claude-oauth-'.bin2hex(random_bytes(6));
        mkdir($this->dir, 0700, true);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        (new Filesystem)->deleteDirectory($this->dir);
    }

    protected function getPackageProviders($app): array
    {
        return [ClaudeOAuthServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('claude-oauth.config_dir', $this->dir);
        $app['config']->set('claude-oauth.token', 'test-token');
        $app['config']->set('claude-oauth.binary', dirname(__DIR__).'/Fixtures/fake-claude.php');
        $app['config']->set('claude-oauth.model', 'haiku');
    }

    public function test_container_resolves_a_configured_singleton(): void
    {
        $claude = $this->app->make(ClaudeCode::class);

        $this->assertInstanceOf(ClaudeCode::class, $claude);
        $this->assertSame($claude, $this->app->make(ClaudeCode::class));
        $this->assertSame(['answer' => 'ok'], $claude->prompt('Sys', ['a' => 1], ['type' => 'object']));

        $call = json_decode(file_get_contents($this->dir.'/invocation.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('haiku', $call['argv'][array_search('--model', $call['argv'], true) + 1]);
        $this->assertSame('test-token', $call['env']['CLAUDE_CODE_OAUTH_TOKEN']);
        $this->assertSame($this->dir, $call['env']['CLAUDE_CONFIG_DIR']);
    }

    public function test_config_is_published_under_its_tag(): void
    {
        $paths = array_map('realpath', array_keys(
            ServiceProvider::pathsToPublish(ClaudeOAuthServiceProvider::class, 'claude-oauth-config')
        ));

        $this->assertContains(realpath(dirname(__DIR__, 2).'/config/claude-oauth.php'), $paths);
        $this->assertSame(120, config('claude-oauth.timeout'));
    }
}
