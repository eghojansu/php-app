<?php

declare(strict_types=1);

namespace Ekok\App;

class HttpException extends \RuntimeException
{
    /** @var int */
    public $statusCode;

    /** @var array|null */
    public $payload;

    /** @var array|null */
    public $headers;

    public function __construct(
        int $statusCode = 500,
        string $message = null,
        array $payload = null,
        array $headers = null,
        int $code = 0,
        \Throwable $previous = null,
    ) {
        parent::__construct($message ?? '', $code, $previous);

        $this->statusCode = $statusCode;
        $this->payload = $payload;
        $this->headers = $headers;
    }
}
