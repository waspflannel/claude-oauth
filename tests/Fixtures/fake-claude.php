#!/usr/bin/env php
<?php

// A local subprocess fixture: never calls a provider or reads real Claude credentials.
$scenario = getenv('FAKE_CLAUDE_SCENARIO') ?: 'success';
if (in_array('--version', $argv, true)) {
    echo $scenario === 'old-version' ? "2.1.200 (Claude Code)\n" : "2.1.280 (Claude Code)\n";
    exit(0);
}
$stdin = stream_get_contents(STDIN);
$env = [];
foreach (['HOME', 'USERPROFILE', 'CLAUDE_CONFIG_DIR', 'CLAUDE_CODE_OAUTH_TOKEN', 'CLAUDE_CODE_DISABLE_FAST_MODE', 'ANTHROPIC_API_KEY', 'APP_KEY', 'DB_PASSWORD', 'OPENAI_API_KEY', 'CLAUDE_OAUTH_TEST_SECRET'] as $name) {
    $env[$name] = getenv($name);
}
file_put_contents(getenv('CLAUDE_CONFIG_DIR').'/invocation.json', json_encode([
    'argv' => array_slice($argv, 1),
    'cwd' => getcwd(),
    'stdin' => $stdin,
    'env' => $env,
]));

$error = fn (int $status): string => json_encode(['is_error' => true, 'api_error_status' => $status, 'result' => 'raw-provider-secret', 'terminal_reason' => 'error']);
$success = fn (array $extra): string => json_encode(['type' => 'result', 'subtype' => 'success', 'is_error' => false, 'result' => '', ...$extra]);

switch ($scenario) {
    case 'unauthorized':
        echo $error(401);
        exit(1);
    case 'rate-limited':
        echo $error(429);
        exit(1);
    case 'server-error':
        echo $error(500);
        exit(1);
    case 'list-output':
        echo $success(['structured_output' => ['a']]);
        break;
    case 'missing-output':
        echo $success([]);
        break;
    case 'oversized':
        // Valid success JSON, so only the output byte cap can reject it.
        echo $success(['structured_output' => ['answer' => str_repeat('x', 9 * 1024 * 1024)]]);
        break;
    case 'hang':
        sleep(10);
        break;
    case 'crash':
        fwrite(STDERR, 'raw-provider-secret');
        exit(3);
    case 'nonzero-exit':
        echo $success(['structured_output' => ['answer' => 'ok']]);
        exit(2);
    default:
        echo $success(['structured_output' => ['answer' => 'ok']]);
}
