<?php

declare(strict_types=1);

namespace Ekok\App\Event;

use Ekok\Utils\Http;

class Request extends Event
{
    /** @var int */
    private $code;

    /** @var int */
    private $kbps;

    /** @var string */
    private $mime;

    /** @var mixed */
    private $output;

    /** @var array|null */
    private $headers;

    public function __construct($output = null, int $code = null, array $headers = null)
    {
        $this->setCode($code ?? 200);
        $this->setOutput($output);
        $this->setHeaders($headers);
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function setCode(int $code): static
    {
        $this->code = $code;
        $this->text = Http::statusText($code);

        return $this;
    }

    public function getText(): string
    {
        return $this->text;
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

    public function getKbps(): int|null
    {
        return $this->kbps;
    }

    public function setKbps(int|null $kbps): static
    {
        $this->kbps = $kbps;

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
}
