<?php

use Ekok\Router\Attribute\Route;

class AController
{
    #[Route('/')]
    public function home()
    {
        return 'Welcome home';
    }
}
