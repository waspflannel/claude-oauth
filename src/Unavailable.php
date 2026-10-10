<?php

namespace Waspflannel\ClaudeOAuth;

/** Thrown when the CLI fails, times out, exceeds the output cap or returns unreadable output. */
final class Unavailable extends ClaudeCodeException
{
    public function __construct()
    {
        parent::__construct('Claude Code could not complete the request. Try again.');
    }
}
