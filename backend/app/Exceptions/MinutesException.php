<?php

namespace App\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

class MinutesException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 502,
    ) {
        parent::__construct($message);
    }

    public static function fromResponse(Response $response): self
    {
        $error = $response->json('error');

        return new self(
            is_string($error) && $error !== '' ? $error : 'request failed',
            $response->status(),
        );
    }
}
