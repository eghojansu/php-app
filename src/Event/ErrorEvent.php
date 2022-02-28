<?php

namespace Ekok\App\Event;

use Ekok\App\Fw;
use Ekok\App\HttpException;
use Ekok\Utils\Arr;

class ErrorEvent extends RequestEvent
{
    private $error;
    private $message;
    private $payload;

    public function __construct(\Throwable|int $code, string $message = null, array $headers = null, array $payload = null)
    {
        $error_ = null;
        $code_ = $code;
        $headers_ = $headers;
        $message_ = $message;
        $payload_ = $payload;

        if ($code instanceof \Throwable) {
            $code_ = 500;
            $error_ = $code;
            $message_ = $message ?? ($code->getMessage() ?: null);
        }

        if ($code instanceof HttpException) {
            $code_ = $code->statusCode;
            $headers_ = $code->headers;
            $payload_ = $code->payload;
        }

        $this->error = $error_;
        $this->message = $message_;
        $this->payload = $payload_;
        $this->setCode($code_);
        $this->setHeaders($headers_);
        $this->setName(Fw::EVENT_ERROR);
    }

    public function getError(): \Throwable|null
    {
        return $this->error;
    }

    public function getTrace(): array
    {
        return Arr::formatTrace($this->error);
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
