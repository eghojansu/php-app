<?php

declare(strict_types=1);

namespace Ekok\App\Event;

use Ekok\App\Fw;
use Ekok\Utils\Arr;

class ErrorEvent extends RequestEvent
{
    private $error;
    private $message;
    private $mime;
    private $payload;

    public function __construct(int $code, string $message = null, array $headers = null, array $payload = null, \Throwable $error = null)
    {
        $this->error = $error;
        $this->message = $message;
        $this->payload = $payload;
        $this->setCode($code);
        $this->setHeaders($headers);
        $this->setName(Fw::EVENT_ERROR);
    }

    public function getError(): \Throwable|null
    {
        return $this->error;
    }

    public function getTrace(): array
    {
        return Arr::formatTrace($this->error ?? array());
    }

    public function getMessage(): string|null
    {
        return $this->message;
    }

    public function setMessage(string|null $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getMime(): string|null
    {
        return $this->mime;
    }

    public function setMime(string|null $mime): static
    {
        $this->mime = $mime;

        return $this;
    }

    public function getPayload(): array|null
    {
        return $this->payload;
    }

    public function setPayload(array|null $payload): static
    {
        $this->payload = $payload;

        return $this;
    }
}
