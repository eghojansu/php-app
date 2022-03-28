<?php

declare(strict_types=1);

namespace Ekok\App\Event;

use Ekok\App\Http;

class Request extends Event
{
    /** @var int */
    private $code;

    /** @var string */
    private $text;

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
        $this->setCode($code);
        $this->setOutput($output, false);
        $this->setHeaders($headers);
    }

    public function getCode(): int
    {
        return $this->code ?? 200;
    }

    public function setCode(int|null $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getText(): string
    {
        return $this->text ?? ($this->text = Http::statusText($this->getCode()));
    }

    public function getOutput()
    {
        return $this->output;
    }

    public function setOutput($output, bool $stopPropagation = true): static
    {
        $this->output = $output;

        if ($stopPropagation) {
            $this->stopPropagation();
        }

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
