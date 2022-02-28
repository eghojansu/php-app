<?php

namespace Ekok\App\Event;

use Ekok\App\Fw;

class ResponseEvent extends RequestEvent
{
    private $result;

    public function __construct($result, $output = null, int $code = null, array $headers = null)
    {
        parent::__construct($output, $code, $headers);

        $this->result = $result;
        $this->setName(Fw::EVENT_RESPONSE);
    }

    public function getResult()
    {
        return $this->result;
    }
}
