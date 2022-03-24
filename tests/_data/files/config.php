<?php

return array(
    'foo' => 'bar',
    'is_cli' => $env->isCli(),
    '@routeAll' => array(
        array(
            'GET /' => static fn() => 'home',
            'GET /view/@id' => static fn($id) => 'viewing ' . $id,
        ),
    ),
    'di@addRule # std class object' => array('foo', 'stdClass'),
    static fn() => $env->set('from_callable', true),
);
