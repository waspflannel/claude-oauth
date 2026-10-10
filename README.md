# claude-oauth

Run the local Claude Code CLI headless (`claude -p`) from PHP, using a subscription token from `claude setup-token`. Each call starts one process and returns validated structured JSON.

It needs PHP 8.2+ and `symfony/process`. It has an optional Laravel service provider.

```
composer require waspflannel/claude-oauth
```

## Setup

1. Install the Claude Code CLI, version 2.1.280 or newer: https://code.claude.com/docs/en/setup
2. Run `claude setup-token` and copy the token.
3. Store the token as a secret (an environment variable or a secret manager). Never commit it.

The CLI must be runnable by the PHP process, including queue workers. If it is not on `PATH`, set the `binary` option.

## Plain PHP

```php
use Waspflannel\ClaudeOAuth\ClaudeCode;
use Waspflannel\ClaudeOAuth\ClaudeCodeException;
use Waspflannel\ClaudeOAuth\RateLimited;

$claude = new ClaudeCode(
    configDir: '/var/lib/myapp/claude-code', // private, writable directory
    token: getenv('CLAUDE_CODE_OAUTH_TOKEN') ?: null,
);

$schema = [
    'type' => 'object',
    'properties' => [
        'sentiment' => ['type' => 'string', 'enum' => ['positive', 'neutral', 'negative']],
    ],
    'required' => ['sentiment'],
    'additionalProperties' => false,
];

try {
    $result = $claude->prompt(
        'Classify the sentiment of the text.',
        ['text' => 'I love this package.'],
        $schema,
    );
    echo $result['sentiment'];
} catch (RateLimited $e) {
    // Retry later.
} catch (ClaudeCodeException $e) {
    error_log($e->getMessage());
}
```

`prompt()` returns the decoded `structured_output` object as an array. An array input is JSON-encoded and sent on stdin. A string input is sent unchanged.

You can check the CLI first with `$claude->available()` (bool) and `$claude->version()` (`?string`). Both run `claude --version` and compare it with `ClaudeCode::MINIMUM_VERSION` (2.1.280). A found version is cached per instance. A missing or broken CLI is checked again on the next call.

## Laravel

The `ClaudeOAuthServiceProvider` is auto-discovered. It binds a `ClaudeCode` singleton.

```
CLAUDE_CODE_OAUTH_TOKEN=...
CLAUDE_BINARY=claude
CLAUDE_MODEL=sonnet
CLAUDE_EFFORT=low
CLAUDE_CONFIG_DIR=/path/to/private/dir
CLAUDE_TIMEOUT=120
```

`CLAUDE_CONFIG_DIR` defaults to `storage_path('app/private/claude-code')`. `CLAUDE_EFFORT` is optional.

```php
use Waspflannel\ClaudeOAuth\ClaudeCode;

$result = app(ClaudeCode::class)->prompt($systemPrompt, $input, $schema);
```

You can also type-hint `ClaudeCode` in a constructor or controller method.

To publish the config file to `config/claude-oauth.php`:

```
php artisan vendor:publish --tag=claude-oauth-config
```

## Options

| Option | Default | Meaning |
| --- | --- | --- |
| `configDir` | required | Private, writable directory. It is the CLI's working directory, `HOME` and `CLAUDE_CONFIG_DIR`. |
| `token` | none | Token from `claude setup-token`. Required for `prompt()`. |
| `binary` | `'claude'` | Path or name of the CLI. |
| `model` | `'sonnet'` | Model alias or full model ID. Can be overridden per call. |
| `effort` | `null` | Value for `--effort`. `null` sends no `--effort` flag. |
| `timeoutSeconds` | `120` | Time limit for one call. |
| `maxOutputBytes` | 8 MiB | Output larger than this is rejected. |
| `extraEnv` | `[]` | Extra environment variables for the CLI. They cannot override the token, config dir, `HOME`/`USERPROFILE` or the fast-mode variable. |

## Errors

All exceptions extend `ClaudeCodeException`. Messages are fixed. Raw CLI output never appears in them.

| Exception | When |
| --- | --- |
| `NotConfigured` | The token is missing, the CLI is missing or too old, or the CLI reports 401. |
| `RateLimited` | The CLI reports 429. |
| `Unavailable` | Timeout, crash, non-zero exit, or output over `maxOutputBytes`. |
| `InvalidOutput` | `structured_output` is missing or is not an object. |

## What each call does

- Tools, MCP servers, settings files, slash commands and session saving are turned off.
- The environment is an allowlist: platform basics, plus `CLAUDE_CONFIG_DIR`, `HOME`/`USERPROFILE` (set to `configDir`), `CLAUDE_CODE_OAUTH_TOKEN` and `CLAUDE_CODE_DISABLE_FAST_MODE=1`.
- It never uses `--bare`, which needs an API key (paid API billing).
- It never falls back to `ANTHROPIC_API_KEY`.

## Fast mode

Fast mode is turned off on purpose with `CLAUDE_CODE_DISABLE_FAST_MODE=1`. On subscriptions, fast mode bills usage credits.

## Terms of use

Read Anthropic's Claude Code legal page yourself: https://code.claude.com/docs/en/legal-and-compliance

As that page describes it, OAuth authentication with a subscription is meant for ordinary use of Claude Code and native Anthropic apps. Developers building third-party products or services should use API keys.

This package runs the unmodified CLI with your own token, on your own machine or your own self-hosted instance. Do not ship it in a product that uses other people's subscriptions unless Anthropic approves. You are responsible for following their terms.

## Testing

```
composer install && vendor/bin/phpunit
```

The tests use a fake CLI. They need no network and no token.

## License

MIT. See [LICENSE](LICENSE).
