<?php

use Ekok\Container\Box;

return array(
    'foo' => 'bar',
    'is_cli' => $fw->isCli(),
    '@routeAll' => array(
        array(
            'GET /' => static fn() => 'home',
            'GET /view/@id' => static fn($id) => 'viewing ' . $id,
        ),
    ),
    'di@addRule # std class object' => array('foo', 'stdClass'),
    static fn(Box $box) => $box->set('from_callable', true),
);
