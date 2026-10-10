<?php

namespace Waspflannel\ClaudeOAuth;

/** Thrown when the token is missing or rejected, or the CLI is missing or too old. */
final class NotConfigured extends ClaudeCodeException
{
    public function __construct()
    {
        parent::__construct('Claude Code is not configured. Set a valid token and install a supported Claude Code CLI.');
    }
}
