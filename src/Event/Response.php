<?php

declare(strict_types=1);

namespace Ekok\App\Event;

class Response extends Request
{
    private $result;

    public function __construct($result, $output = null, int $code = null, array $headers = null)
    {
        parent::__construct($output, $code, $headers);

        $this->result = $result;
    }

    public function getResult()
    {
        return $this->result;
    }
}
