<?php

namespace Ekok\App\Event;

class ControllerArguments extends Controller
{
    private $arguments;

    public function __construct(string|callable $controller, array $arguments = null)
    {
        parent::__construct($controller);

        $this->setArguments($arguments ?? array());
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function setArguments(array $arguments): static
    {
        $this->arguments = $arguments;

        return $this;
    }
}
