<?php

namespace Ekok\App\Event;

class Redirect extends Request
{
    private $permanent;

    public function __construct(int $code, string $url, bool $permanent = null)
    {
        $this->permanent = $permanent;

        $this->setUrl($url);
        $this->setCode($code);
    }

    public function isPermanent(): bool|null
    {
        return $this->permanent;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }
}
