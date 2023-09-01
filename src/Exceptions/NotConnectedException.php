<?php

namespace Portable\EloquentZoho\Exceptions;

class NotConnectedException extends \RuntimeException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(
            $message ?: 'Cannot connect to Zoho Analytics with current configuration. Check URL and credentials.',
            $code,
            $previous,
        );
    }
}
