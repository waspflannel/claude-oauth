<?php

return [
    'binary' => env('CLAUDE_BINARY', 'claude'),

    // Token from `claude setup-token`. Keep it in .env or a secret store, never commit it.
    'token' => env('CLAUDE_CODE_OAUTH_TOKEN'),

    'model' => env('CLAUDE_MODEL', 'sonnet'),

    // null sends no --effort flag.
    'effort' => env('CLAUDE_EFFORT'),

    // Private, writable. Used as the CLI's cwd, HOME and CLAUDE_CONFIG_DIR.
    'config_dir' => env('CLAUDE_OAUTH_CONFIG_DIR',storage_path('app/private/claude-code')),

    'timeout' => (int) env('CLAUDE_TIMEOUT', 120),
];
