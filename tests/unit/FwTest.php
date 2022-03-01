<?php

use Ekok\App\Event\ErrorEvent;
use Ekok\App\Event\RequestEvent;
use Ekok\App\Fw;
use Ekok\Logger\Log;
use Ekok\Cache\Cache;
use Ekok\Container\Di;
use Ekok\EventDispatcher\Dispatcher;

class FwTest extends \Codeception\Test\Unit
{
    /** @var Fw */
    private $fw;

    public function _before()
    {
        $this->fw = Fw::create(array('quiet' => true));
    }

    public function testDependencies()
    {
        $this->assertSame($this->fw->di->make('dispatcher'), $this->fw->di->make(Dispatcher::class));
        $this->assertSame($this->fw->di->make('cache'), $this->fw->di->make(Cache::class));
        $this->assertSame($this->fw->di->make('fw'), $this->fw->di->make(Fw::class));
        $this->assertSame($this->fw->di->make('log'), $this->fw->di->make(Log::class));
        $this->assertSame($this->fw, $this->fw->di->make('fw'));
        $this->assertSame($this->fw->di, $this->fw->di->make(Di::class));
        $this->assertSame($this->fw->di, $this->fw->di->make('_di_'));
    }

    public function testInitializationAndState()
    {
        $this->assertSame('GET', $this->fw->getVerb());
        $this->assertSame('/', $this->fw->getPath());
        $this->assertSame('', $this->fw->getBasePath());
        $this->assertSame('', $this->fw->getEntry());
        $this->assertSame('HTTP/1.1', $this->fw->getProtocol());
        $this->assertSame('http', $this->fw->getScheme());
        $this->assertSame('localhost', $this->fw->getHost());
        $this->assertSame(80, $this->fw->getPort());
        $this->assertSame(null, $this->fw->getMime());
        $this->assertSame('UTF-8', $this->fw->getCharset());
        $this->assertSame(false, $this->fw->isDev());
        $this->assertSame(false, $this->fw->isBuiltin());
        $this->assertSame(true, $this->fw->isCli());
        $this->assertSame(false, $this->fw->isContentType('json'));
        $this->assertSame(false, $this->fw->isJson());
        $this->assertSame(false, $this->fw->wantsJson());
        $this->assertSame(false, $this->fw->isMultipart());
        $this->assertSame(false, $this->fw->accept('json'));
        $this->assertSame('*/*', $this->fw->acceptBest());
        $this->assertSame('', $this->fw->getContentType());
        $this->assertSame('http://localhost', $this->fw->getBaseUrl());

        // mutate
        $this->assertSame('post', $this->fw->setVerb('post')->getVerb());
        $this->assertSame('/foo', $this->fw->setPath('/foo')->getPath());
        $this->assertSame('/foo', $this->fw->setPath('foo')->getPath());
        $this->assertSame('/foo', $this->fw->setBasePath('/foo')->getBasePath());
        $this->assertSame('/foo', $this->fw->setBasePath('foo')->getBasePath());
        $this->assertSame('', $this->fw->setBasePath('')->getBasePath());
        $this->assertSame('foo.php', $this->fw->setEntry('foo.php')->getEntry());
        $this->assertSame('HTTP/1.0', $this->fw->setProtocol('HTTP/1.0')->getProtocol());
        $this->assertSame('https', $this->fw->setScheme('https')->getScheme());
        $this->assertSame('foo', $this->fw->setHost('foo')->getHost());
        $this->assertSame(8080, $this->fw->setPort(8080)->getPort());
        $this->assertSame('text/html', $this->fw->setMime('text/html')->getMime());
        $this->assertSame('UTF-88', $this->fw->setCharset('UTF-88')->getCharset());
        $this->assertSame(true, $this->fw->setDev(true)->isDev());
        $this->assertSame(true, $this->fw->setBuiltin(true)->isBuiltin());
        $this->assertSame('http://localhost/foo', $this->fw->setBaseUrl('http://localhost/foo')->getBaseUrl());
    }

    public function testResponse()
    {
        $this->assertSame(true, $this->fw->isQuiet());
        $this->assertSame(false, $this->fw->sent());
        $this->assertSame(null, $this->fw->code());
        $this->assertSame(null, $this->fw->text());
        $this->assertSame(null, $this->fw->getOutput());
        $this->assertSame(array(), $this->fw->getHeaders());

        // mutate
        $this->assertSame(false, $this->fw->setQuiet(false)->isQuiet());
        $this->assertSame(200, $this->fw->status(200)->code());
        $this->assertSame('OK', $this->fw->text());
        $this->assertSame('foo', $this->fw->setOutput('foo')->getOutput());
        $this->assertSame('["foo"]', $this->fw->setOutput(array('foo'))->getOutput());
        $this->assertSame('application/json', $this->fw->getMime());

        $actual = $this->fw->setHeaders(array(
            'location' => 'url',
            'accept' => array('text/html', 'application/json'),
        ))->getHeaders();
        $expected = array(
            'location' => array('url'),
            'accept' => array('text/html', 'application/json'),
        );

        $this->assertSame($expected, $actual);

        // status exception
        $this->expectException('LogicException');
        $this->expectExceptionMessage('Unsupported HTTP code: 999');

        $this->fw->status(999);
    }

    public function testSendResponse()
    {
        $this->expectOutputString('foo');

        $this->fw->setQuiet(false);
        $this->fw->send('foo', array('custom' => 'header'));

        if (function_exists('xdebug_get_headers')) {
            $headers = xdebug_get_headers();
            $expected = array(
                'Custom: header',
                'Content-Type: text/html;charset=UTF-8',
            );

            $this->assertSame($expected, $headers);
        }
    }

    public function testThrottle()
    {
        $longText = str_repeat('foo', 1000);

        $this->expectOutputString($longText);

        $start = microtime(true);
        $this->fw->throttle($longText, 2);
        $end = microtime(true) - $start;

        $this->assertGreaterThan(1, $end);
    }

    public function testHttpAcceptParse()
    {
        $accept = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
        $actual = Fw::httpAcceptParse($accept);
        $expected = array(
            array('content' => 'text/html'),
            array('content' => 'application/xhtml+xml'),
            array('content' => 'application/xml', 'q' => 0.9),
            array('content' => '*/*', 'q' => 0.8),
        );

        $this->assertSame($expected, $actual);
    }

    /** @dataProvider runProvider */
    public function testRun($expected, array $env = null)
    {
        $fw = Fw::create(array('quiet' => true) + ($env['env'] ?? array()), null, $env);
        $fw->errorTemplate(null, 'cli', '[CLI] [{code} - {text}] {message}');
        $fw->errorTemplate(null, 'html', '[HTML] [{code} - {text}] {message}');

        $fw->route('GET /', static fn() => 'home');
        $fw->route('GET /is-dev', static fn(Fw $fw) => array('dev' => $fw->isDev()));
        $fw->route('GET /foo/@bar/@baz', static fn($bar, $baz) => array($bar, $baz));
        $fw->route('POST /drink/@any*?', static fn($any = 'not thristy') => $any);
        $fw->route('GET @eater /eat/@foods* [name=eater,eat,drinks]', static function(Fw $fw, $foods) {
            $match = $fw->getMatch();
            $aliases = $fw->getAliases();

            return isset($aliases['eater']) ? $foods . '-' . $match['name'] . '-' . implode(':', $match['tags']) : null;
        });
        $fw->route('GET /result', static fn() => static fn (Fw $fw) => $fw->send('result'));
        $fw->run();

        $this->assertSame($expected, $fw->getOutput());
    }

    public function runProvider()
    {
        return array(
            'home' => array('home'),
            'named' => array('{"dev":false}', array(
                'SERVER' => array(
                    'REQUEST_URI' => '/is-dev',
                ),
            )),
            'args' => array('["bar","baz"]', array(
                'SERVER' => array(
                    'REQUEST_URI' => '/foo/bar/baz',
                ),
            )),
            'param-eater' => array('food/and/drinks-eater-eat:drinks', array(
                'SERVER' => array(
                    'REQUEST_URI' => '/eat/food/and/drinks',
                ),
            )),
            'result' => array('result', array(
                'SERVER' => array(
                    'REQUEST_URI' => '/result',
                ),
            )),
            'not found' => array('[CLI] [404 - Not Found] [404] GET /eat', array(
                'SERVER' => array(
                    'REQUEST_URI' => '/eat',
                ),
            )),
            'not found html' => array('[HTML] [404 - Not Found] [404] GET /eat', array(
                'env' => array('cli' => false),
                'SERVER' => array(
                    'REQUEST_URI' => '/eat',
                ),
            )),
            'optional any parameter' => array('honey/lemon', array(
                'SERVER' => array(
                    'REQUEST_METHOD' => 'POST',
                    'REQUEST_URI' => '/drink/honey/lemon',
                ),
            )),
            'optional any parameter (not found)' => array('not thristy', array(
                'SERVER' => array(
                    'REQUEST_URI' => '/drink',
                    'REQUEST_METHOD' => 'POST',
                ),
            )),
            'not found by verb' => array('[CLI] [404 - Not Found] [404] GET /drink', array(
                'SERVER' => array(
                    'REQUEST_URI' => '/drink',
                ),
            )),
        );
    }

    public function testRunNoRoutes()
    {
        $this->fw->errorTemplate(null, 'cli', '[CLI] [{code} - {text}] {message}');
        $this->fw->run();

        $this->assertSame('[CLI] [500 - Internal Server Error] No route defined', $this->fw->getOutput());
    }

    public function testRunErrorJson()
    {
        $fw = Fw::create(array('quiet' => true, 'dev' => true), null, array(
            'SERVER' => array(
                'REQUEST_URI' => '/none',
                'HTTP_ACCEPT' => 'json',
            ),
        ));

        $fw->route('GET /', static fn () => null);
        $fw->run();

        $this->assertStringStartsWith('{"code":404,"text":"Not Found"', $fw->getOutput());
        $this->assertStringContainsString('"trace":', $fw->getOutput());
    }

    public function testRunInteruption()
    {
        $this->fw->chain(static function (Dispatcher $dispatcher) {
            $dispatcher->on(Fw::EVENT_REQUEST, static function (RequestEvent $event) {
                $event->setOutput('foo')->stopPropagation();
            });
        });
        $this->fw->run();

        $this->assertSame('foo', $this->fw->getOutput());
    }

    public function testRouteInvalid()
    {
        $this->expectException('LogicException');
        $this->expectExceptionMessage('Invalid route: "@"');

        $this->fw->route('@');
    }

    public function testRouteNoPath()
    {
        $this->expectException('LogicException');
        $this->expectExceptionMessage('No path defined in route: "GET"');

        $this->fw->route('GET');
    }

    public function testRouteNoPathAlias()
    {
        $this->expectException('LogicException');
        $this->expectExceptionMessage('Route not exists: home');

        $this->fw->route('GET @home');
    }

    public function testErrorListener()
    {
        $called = false;
        $this->fw->errorTemplate(null, 'cli', '[CLI] [{code} - {text}] {message}');
        $this->fw->chain(static function (Dispatcher $dispatcher) use (&$called) {
            $dispatcher->on(Fw::EVENT_ERROR, static function (ErrorEvent $event) use (&$called) {
                $called = $event->setPayload(null)->setMessage('Update ' . $event->getMessage())->getError() instanceof \LogicException;

                throw new \RuntimeException($event->getMessage() . ' and Error after error');
            });
        })->error(new \LogicException('First error'));

        $this->assertTrue($called);
        $this->assertSame('[CLI] [500 - Internal Server Error] Update First error and Error after error', $this->fw->getOutput());
    }

    public function testUrl()
    {
        $fw = Fw::create(array('cli' => false), null, array(
            'SERVER' => array(
                'REQUEST_URI' => '/basedir/front.php/path',
                'SCRIPT_NAME' => '/basedir/front.php',
            ),
        ));
        $fw->route('GET @home /', static fn() => null);
        $fw->route('GET @view /item/@id', static fn() => null);
        $fw->route('GET @optional /optional/@id?', static fn() => null);
        $fw->route('GET @all /all/@params*', static fn() => null);
        $fw->route('GET @delete /item/delete/@id/@type', static fn() => null);

        $this->assertSame('/', $fw->alias('home'));
        $this->assertSame('/?q=foo', $fw->alias('home', array('q' => 'foo')));
        $this->assertSame('/item/1?q=foo', $fw->alias('view', array('id' => '1', 'q' => 'foo')));
        $this->assertSame('/optional/1', $fw->alias('optional', array('id' => '1')));
        $this->assertSame('/optional', $fw->alias('optional'));
        $this->assertSame('/all/params/as/string', $fw->alias('all', array('params' => 'params/as/string')));
        $this->assertSame('/item/delete/1/foo', $fw->alias('delete', array('id' => '1', 'type' => 'foo')));
        $this->assertSame('/unnamed', $fw->alias('unnamed'));

        $this->assertSame('/basedir/front.php/', $fw->url('home'));
        $this->assertSame('http://localhost/basedir/front.php/', $fw->url('home', null, true));
        $this->assertSame('/basedir/assets/style.css', $fw->baseurl('assets/style.css'));
        $this->assertSame('http://localhost/basedir/assets/style.css', $fw->baseurl('assets/style.css', null, true));
        $this->assertSame('http://localhost/basedir/front.php/path', $fw->uri());
        $this->assertSame('/basedir/front.php/path', $fw->uri(false));

        $this->expectException('LogicException');
        $this->expectExceptionMessage('Route param required: id@view');
        $fw->alias('view');
    }

    public function testEnv()
    {
        $this->assertSame($_SERVER, $this->fw->env('SERVER'));
        $this->assertSame($_SESSION ?? array(), $this->fw->env('SESSION'));
        $this->assertSame(null, $this->fw->cookie('foo'));
        $this->assertSame(null, $this->fw->session('foo'));
        $this->assertSame('bar', $this->fw->session('foo', 'bar'));
        $this->assertSame('bar', $this->fw->session('foo'));
        $this->assertSame('bar', $this->fw->flash('foo'));
        $this->assertSame(null, $this->fw->session('foo'));
        $this->assertSame(array(), $this->fw->sessionEnd()->env('SESSION'));
    }

    public function testLoad()
    {
        $this->assertSame(array(), $this->fw->getLoadDirectories());
        $this->assertSame(array('.php'), $this->fw->getLoadExtensions());
        $this->assertSame(array(TEST_DATA . '/files/'), $this->fw->setLoadDirectories(TEST_DATA . '/files')->getLoadDirectories());
        $this->assertSame(array('.php'), $this->fw->setLoadExtensions('php')->getLoadExtensions());

        $this->assertSame('foo: none', $this->fw->load('foo'));
        $this->assertSame('foo: bar', $this->fw->load('foo', array('foo' => 'bar')));
        $this->assertSame(null, $this->fw->load('none', null, true));
    }

    public function testLoadError()
    {
        $this->expectException('LogicException');
        $this->expectExceptionMessage('Error in template: error.php (Error from template)');

        $this->fw->setLoadDirectories(TEST_DATA . '/files');
        $this->fw->load('error.php');
    }

    public function testLoadNotFound()
    {
        $this->expectException('LogicException');
        $this->expectExceptionMessage('File not found: "none"');

        $this->fw->setLoadDirectories(TEST_DATA . '/files');
        $this->fw->load('none');
    }
}
