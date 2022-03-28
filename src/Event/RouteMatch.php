<?php

namespace Ekok\App\Event;

class RouteMatch extends Request
{
    public function __construct(private array $match)
    {}

    public function getMatch(): array
    {
        return $this->match;
    }

    public function getController(): string|callable
    {
        return $this->match['handler'];
    }

    public function setController(string|callable $controller): static
    {
        $this->match['handler'] = $controller;

        return $this;
    }

    public function getArguments(): array
    {
        return $this->match['args'] ?? array();
    }

    public function setArguments(array $arguments): static
    {
        $this->match['args'] = $arguments;

        return $this;
    }

    public function getTags(): array|null
    {
        return $this->match['tags'] ?? null;
    }

    public function setTags(array|null $tags): static
    {
        $this->match['tags'] = $tags;

        return $this;
    }

    public function getKbps(): int
    {
        return $this->match['kbps'] ?? 0;
    }

    public function setKbps(int|null $kbps): static
    {
        $this->match['kbps'] = $kbps;

        return $this;
    }

    public function getAttribute(string $name)
    {
        return $this->match[$name] ?? null;
    }

    public function setAttribute(string $name, $value): static
    {
        $this->match[$name] = $value;

        return $this;
    }
}
