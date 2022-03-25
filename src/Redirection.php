<?php

declare(strict_types=1);

namespace Ekok\App;

class Redirection extends \RuntimeException
{
    public $args;
    public $isRoute = false;
    public $isBack = false;

    public function __construct(
        public string|null $url = null,
        public bool|null $permanent = null,
        public int|null $statusCode = null,
    ) {
        parent::__construct(sprintf('Redirecting to: "%s"', $url));
    }

    public static function url(string $url, bool $permanent = null, int $code = null): static
    {
        return new static($url, $permanent, $code);
    }

    public static function to(
        string $path,
        array $args = null,
        bool $permanent = null,
        int $code = null,
    ): static {
        $self = new static($path, $permanent, $code);
        $self->args = $args;
        $self->isRoute = true;

        return $self;
    }

    public static function back(string $url = null): static
    {
        $self = new static($url);
        $self->isBack = true;

        return $self;
    }
}
