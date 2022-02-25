<?php

namespace Ekok\App;

use Ekok\Container\Di;
use Ekok\Container\Box;

class Fw
{
    /** @var Di */
    public $di;

    private $routes = array();
    private $aliases = array();

    public function __construct(array $data = null, array $rules = null)
    {
        $this->di = (new Di($rules))
            ->inject(new Box($data))
            ->inject($this)
        ;
    }
}
