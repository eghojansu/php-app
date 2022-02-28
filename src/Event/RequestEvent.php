<?php

namespace Ekok\App\Event;

use Ekok\App\Fw;
use Ekok\EventDispatcher\Event;

class RequestEvent extends Event
{
    /** @var int */
    private $code;

    /** @var mixed */
    private $output;

    /** @var array|null */
    private $headers;

    public function __construct($output = null, int $code = null, array $headers = null)
    {
        $this->code = $code ?? 200;
        $this->output = $output;
        $this->headers = $headers;
        $this->setName(Fw::EVENT_REQUEST);
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function setCode(int $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getOutput()
    {
        return $this->output;
    }

    public function setOutput($output): static
    {
        $this->output = $output;

        return $this;
    }

    public function getHeaders(): array|null
    {
        return $this->headers;
    }

    public function setHeaders(array|null $headers): static
    {
        $this->headers = $headers;

        return $this;
    }
}
