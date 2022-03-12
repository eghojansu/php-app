<?php

namespace Ekok\App\Event;

use Ekok\EventDispatcher\Event;

class Controller extends Event
{
    private $controller;

    public function __construct(string|callable $controller)
    {
        $this->setController($controller);
    }

    public function getController(): string|callable
    {
        return $this->controller;
    }

    public function setController(string|callable $controller): static
    {
        $this->controller = $controller;

        return $this;
    }
}
