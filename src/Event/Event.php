<?php

declare(strict_types=1);

namespace Ekok\App\Event;

use Ekok\Utils\Str;
use Ekok\EventDispatcher\Event as EventDispatcherEvent;

abstract class Event extends EventDispatcherEvent
{
    public function getName(): ?string
    {
        return 'on' . Str::className(static::class);
    }
}
