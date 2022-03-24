<?php

use Ekok\App\Env;

class MyEnv extends Env
{
    public function isCustom(): bool
    {
        return true;
    }
}
