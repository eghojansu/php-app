<?php

use Ekok\Config\Attribute\Route;

class AController
{
    #[Route('/')]
    public function home()
    {
        return 'Welcome home';
    }
}
