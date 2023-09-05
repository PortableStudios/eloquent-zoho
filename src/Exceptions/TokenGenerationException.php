<?php

namespace Portable\EloquentZoho\Exceptions;

class TokenGenerationException extends \RuntimeException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(
            $message ?: 'Cannot create token for Zoho Analytics with current configuration. Check URL and credentials.',
            $code,
            $previous,
        );
    }
}
