<?php

namespace Waspflannel\ClaudeOAuth\Tests;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Waspflannel\ClaudeOAuth\ClaudeCode;
use Waspflannel\ClaudeOAuth\InvalidOutput;
use Waspflannel\ClaudeOAuth\NotConfigured;
use Waspflannel\ClaudeOAuth\RateLimited;
use Waspflannel\ClaudeOAuth\Unavailable;

final class ClaudeCodeTest extends TestCase
{
    private const SCHEMA = ['type' => 'object', 'properties' => ['answer' => ['type' => 'string']]];

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/claude-oauth-'.bin2hex(random_bytes(6));
        mkdir($this->dir, 0700, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->dir);
    }

    public function test_structured_prompt_runs_the_isolated_cli(): void
    {
        $result = $this->claude()->prompt('Be brief.', ['text' => 'héllo'], self::SCHEMA);

        $this->assertSame(['answer' => 'ok'], $result);
        $call = $this->invocation();
        foreach (['-p', '--strict-mcp-config', '--disable-slash-commands', '--no-session-persistence'] as $flag) {
            $this->assertContains($flag, $call['argv']);
        }
        $this->assertSame('json', $this->value($call['argv'], '--output-format'));
        $this->assertSame('sonnet', $this->value($call['argv'], '--model'));
        $this->assertSame('', $this->value($call['argv'], '--tools'));
        $this->assertSame('', $this->value($call['argv'], '--setting-sources'));
        $this->assertSame('none', $this->value($call['argv'], '--permission-prompts'));
        $this->assertNotContains('--effort', $call['argv']);
        $this->assertNotContains('--bare', $call['argv']);
        $this->assertStringStartsWith('Be brief.', $this->value($call['argv'], '--system-prompt'));
        $this->assertSame(self::SCHEMA, json_decode($this->value($call['argv'], '--json-schema'), true));
        $this->assertSame(['text' => 'héllo'], json_decode($call['stdin'], true));
        $this->assertSame(realpath($this->dir), realpath($call['cwd']));
    }

    public function test_string_input_model_override_and_effort(): void
    {
        $this->claude(options: ['effort' => 'high'])->prompt('Sys', 'plain {text}', self::SCHEMA, 'opus');

        $call = $this->invocation();
        $this->assertSame('plain {text}', $call['stdin']);
        $this->assertSame('opus', $this->value($call['argv'], '--model'));
        $this->assertSame('high', $this->value($call['argv'], '--effort'));
    }

    public function test_child_environment_is_isolated(): void
    {
        putenv('CLAUDE_OAUTH_TEST_SECRET=private');
        foreach (['ANTHROPIC_API_KEY', 'OPENAI_API_KEY', 'DB_PASSWORD', 'APP_KEY'] as $name) {
            $_ENV[$name] = 'secret';
        }

        try {
            $this->claude()->prompt('Sys', 'x', self::SCHEMA);
        } finally {
            putenv('CLAUDE_OAUTH_TEST_SECRET');
            foreach (['ANTHROPIC_API_KEY', 'OPENAI_API_KEY', 'DB_PASSWORD', 'APP_KEY'] as $name) {
                unset($_ENV[$name]);
            }
        }

        $env = $this->invocation()['env'];
        $this->assertSame('test-token', $env['CLAUDE_CODE_OAUTH_TOKEN']);
        $this->assertSame($this->dir, $env['CLAUDE_CONFIG_DIR']);
        $this->assertSame($this->dir, $env['HOME']);
        $this->assertSame($this->dir, $env['USERPROFILE']);
        $this->assertSame('1', $env['CLAUDE_CODE_DISABLE_FAST_MODE']);
        foreach (['ANTHROPIC_API_KEY', 'OPENAI_API_KEY', 'DB_PASSWORD', 'APP_KEY', 'CLAUDE_OAUTH_TEST_SECRET'] as $name) {
            $this->assertFalse($env[$name], $name);
        }
    }

    public function test_extra_env_cannot_override_the_token(): void
    {
        $this->claude(options: ['extraEnv' => ['CLAUDE_CODE_OAUTH_TOKEN' => 'other']])->prompt('Sys', 'x', self::SCHEMA);

        $this->assertSame('test-token', $this->invocation()['env']['CLAUDE_CODE_OAUTH_TOKEN']);
    }

    #[DataProvider('failures')]
    public function test_failures_map_to_fixed_exceptions(string $scenario, string $class): void
    {
        try {
            $this->claude($scenario)->prompt('Sys', 'x', self::SCHEMA);
            $this->fail('Expected an exception.');
        } catch (\Throwable $exception) {
            $this->assertSame($class, $exception::class);
            $this->assertStringNotContainsString('raw-provider-secret', $exception->getMessage());
        }
    }

    /** @return array<string, array{string, class-string}> */
    public static function failures(): array
    {
        return [
            'unauthorized' => ['unauthorized', NotConfigured::class],
            'rate limited' => ['rate-limited', RateLimited::class],
            'server error' => ['server-error', Unavailable::class],
            'crash' => ['crash', Unavailable::class],
            'nonzero exit' => ['nonzero-exit', Unavailable::class],
            'oversized' => ['oversized', Unavailable::class],
            'list output' => ['list-output', InvalidOutput::class],
            'missing output' => ['missing-output', InvalidOutput::class],
        ];
    }

    public function test_hang_times_out_as_unavailable(): void
    {
        $start = microtime(true);

        try {
            $this->claude('hang', ['timeoutSeconds' => 1])->prompt('Sys', 'x', self::SCHEMA);
            $this->fail('Expected an exception.');
        } catch (Unavailable) {
            $this->assertLessThan(5, microtime(true) - $start);
        }
    }

    #[TestWith([null])]
    #[TestWith(['  '])]
    public function test_missing_token_never_starts_the_cli(?string $token): void
    {
        try {
            $this->claude(options: ['token' => $token])->prompt('Sys', 'x', self::SCHEMA);
            $this->fail('Expected an exception.');
        } catch (NotConfigured) {
            $this->assertFileDoesNotExist($this->dir.'/invocation.json');
        }
    }

    public function test_missing_binary_is_not_configured(): void
    {
        $claude = new ClaudeCode(configDir: $this->dir, token: 'test-token', binary: '/nonexistent/claude');

        $this->assertNull($claude->version());
        $this->assertFalse($claude->available());
        $this->expectException(NotConfigured::class);
        $claude->prompt('Sys', 'x', self::SCHEMA);
    }

    public function test_version_check(): void
    {
        $current = $this->claude();
        $this->assertTrue($current->available());
        $this->assertSame('2.1.280', $current->version());

        $old = $this->claude('old-version');
        $this->assertFalse($old->available());
        $this->assertSame('2.1.200', $old->version());
    }

    /** @param array<string, mixed> $options */
    private function claude(string $scenario = 'success', array $options = []): ClaudeCode
    {
        return new ClaudeCode(...[
            'configDir' => $this->dir,
            'token' => 'test-token',
            'binary' => __DIR__.'/Fixtures/fake-claude.php',
            'extraEnv' => ['FAKE_CLAUDE_SCENARIO' => $scenario],
            ...$options,
        ]);
    }

    /** @return array{argv: list<string>, cwd: string, stdin: string, env: array<string, string|false>} */
    private function invocation(): array
    {
        return json_decode(file_get_contents($this->dir.'/invocation.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param list<string> $argv */
    private function value(array $argv, string $flag): string
    {
        return $argv[array_search($flag, $argv, true) + 1];
    }
}
