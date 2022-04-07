<?php

use Ekok\Config\Attribute\Subscribe;
use Ekok\EventDispatcher\Event;

class FooSubscriber
{
    #[Subscribe()]
    public function onFoo(Event $event)
    {
        $event->stopPropagation();
    }
}
