<?php

declare(strict_types=1);

namespace Ekok\App\Event;

use Ekok\Utils\Arr;

class Error extends Request
{
    private $error;
    private $trace;
    private $message;
    private $payload;

    public function __construct(int $code, string $message = null, array $headers = null, array $payload = null, \Throwable|array $error = null)
    {
        $this->error = is_array($error) ? null : $error;
        $this->trace = $error;

        $this->setCode($code);
        $this->setMessage($message);
        $this->setPayload($payload);
        $this->setHeaders($headers);
    }

    public function getError(): \Throwable|null
    {
        return $this->error;
    }

    public function getTrace(): array
    {
        return Arr::formatTrace($this->trace ?? $this->error);
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
