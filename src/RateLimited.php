<?php

namespace Waspflannel\ClaudeOAuth;

/** Thrown when Claude reports a rate limit (HTTP 429). */
final class RateLimited extends ClaudeCodeException
{
    public function __construct()
    {
        parent::__construct('Claude is temporarily rate limited. Try again later.');
    }
}
