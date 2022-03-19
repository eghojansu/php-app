<?php

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
    static fn() => $fw->set('from_callable', true),
);
