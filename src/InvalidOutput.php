<?php

namespace Waspflannel\ClaudeOAuth;

/** Thrown when the CLI succeeds but its structured output is not a JSON object. */
final class InvalidOutput extends ClaudeCodeException
{
    public function __construct()
    {
        parent::__construct('Claude Code returned output that does not match the requested schema.');
    }
}
