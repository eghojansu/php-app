<?php

use Ekok\App\Fw;
use Ekok\Logger\Log;
use Ekok\Cache\Cache;
use Ekok\Container\Di;
use Ekok\Container\Box;
use Ekok\App\Event\ErrorEvent;
use Ekok\App\Event\RequestEvent;
use Ekok\EventDispatcher\Dispatcher;

class FwTest extends \Codeception\Test\Unit
{
    /** @var Fw */
    private $fw;

    public function _before()
    {
        $this->fw = Fw::create(null, array('QUIET' => true));

        header_remove();
    }

    public function testDependencies()
    {
        $this->assertSame($this->fw->getDispatcher(), $this->fw->getContainer()->make(Dispatcher::class));
        $this->assertSame($this->fw->getDispatcher(), $this->fw->getContainer()->make('dispatcher'));
        $this->assertSame($this->fw->getCache(), $this->fw->getContainer()->make(Cache::class));
        $this->assertSame($this->fw->getCache(), $this->fw->getContainer()->make('cache'));
        $this->assertSame($this->fw->getLog(), $this->fw->getContainer()->make(Log::class));
        $this->assertSame($this->fw->getLog(), $this->fw->getContainer()->make('log'));
        $this->assertSame($this->fw->getBox(), $this->fw->getContainer()->make(Box::class));
        $this->assertSame($this->fw->getBox(), $this->fw->getContainer()->make('box'));
        $this->assertSame($this->fw, $this->fw->getContainer()->make(Fw::class));
        $this->assertSame($this->fw, $this->fw->getContainer()->make('fw'));
        $this->assertSame($this->fw->getContainer(), $this->fw->getContainer()->make(Di::class));
        $this->assertSame($this->fw->getContainer(), $this->fw->getContainer()->make('di'));
    }

    public function testInitializationAndState()
    {
        $this->assertSame('GET', $this->fw->getVerb());
        $this->assertSame('/', $this->fw->getPath());
        $this->assertSame('', $this->fw->getBasePath());
        $this->assertSame('', $this->fw->getEntry());
        $this->assertSame('HTTP/1.1', $this->fw->getProtocol());
        $this->assertSame('http', $this->fw->getScheme());
        $this->assertSame(false, $this->fw->isSecure());
        $this->assertSame('localhost', $this->fw->getHost());
        $this->assertSame(80, $this->fw->getPort());
        $this->assertSame(null, $this->fw->getMime());
        $this->assertSame('UTF-8', $this->fw->getCharset());
        $this->assertSame(false, $this->fw->isDebug());
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
        $this->assertSame('prod', $this->fw->getEnv());

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
        $this->assertSame(true, $this->fw->setSecure(true)->isSecure());
        $this->assertSame('foo', $this->fw->setHost('foo')->getHost());
        $this->assertSame(8080, $this->fw->setPort(8080)->getPort());
        $this->assertSame('text/html', $this->fw->setMime('text/html')->getMime());
        $this->assertSame('UTF-88', $this->fw->setCharset('UTF-88')->getCharset());
        $this->assertSame(true, $this->fw->setDebug(true)->isDebug());
        $this->assertSame(true, $this->fw->setBuiltin(true)->isBuiltin());
        $this->assertSame('http://localhost/foo', $this->fw->setBaseUrl('http://localhost/foo')->getBaseUrl());
        $this->assertSame('dev', $this->fw->setEnv('dev')->getEnv());
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
        $this->assertSame('text/html', $this->fw->getMime());

        $actual = $this->fw->setHeaders(array(
            'location' => 'url',
            'accept' => array('text/html', 'application/json'),
        ))->getHeaders();
        $expected = array(
            'location' => array('url'),
            'accept' => array('text/html', 'application/json'),
        );

        $this->assertSame($expected, $actual);
        $this->assertSame($expected['location'], $this->fw->getHeader('location'));
        $this->assertSame($expected['location'], $this->fw->getHeader('Location'));
        $this->assertSame(array(), $this->fw->getHeader('unknown'));

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
                'Content-Length: 3',
            );

            $this->assertSame($expected, $headers);
        }

        $this->assertSame(200, $this->fw->code());
        $this->assertSame('text/html', $this->fw->getMime());
        $this->assertSame('foo', $this->fw->getOutput());
    }

    public function testSendCallableResponse()
    {
        $this->expectOutputString('foo');

        $this->fw->setBuffering(false);
        $this->fw->send(static fn() => print('foo'), array('custom' => 'header'), 404, 'foo');

        if (function_exists('xdebug_get_headers')) {
            $headers = xdebug_get_headers();
            $expected = array(
                'Custom: header',
                'Content-Type: foo;charset=UTF-8',
            );

            $this->assertSame($expected, $headers);
        }

        $this->assertSame(404, $this->fw->code());
        $this->assertSame('foo', $this->fw->getMime());
        $this->assertSame(null, $this->fw->getOutput());
    }

    public function testSendTwice()
    {
        $this->fw->send('foo');
        $this->fw->send('bar');

        $this->assertSame('foo', $this->fw->getOutput());
    }

    /** @dataProvider sendFileProvider */
    public function testSendFile(array|string $expected, array $env = null, ...$args)
    {
        $fw = Fw::create(null, $env);

        list($output, $code) = ((array) $expected) + array('', 200);

        $this->expectOutputString($output);

        $fw->sendFile(...$args);

        $this->assertSame($code, $fw->code());
    }

    public function sendFileProvider()
    {
        $file = TEST_DATA . '/files/foo.txt';
        $lastModified = gmdate('D, d M Y H:i:s', filemtime($file)) . ' GMT';

        return array(
            'normal' => array(
                'foobar',
                null,
                $file,
            ),
            'download' => array(
                'foobar',
                null,
                $file,
                null,
                true,
            ),
            'not modified' => array(
                array('', 304),
                array(
                    'SERVER' => array(
                        'HTTP_IF_MODIFIED_SINCE' => $lastModified,
                    ),
                ),
                $file,
            ),
            'buffer' => array(
                array('foobar', 200),
                null,
                $file,
                null,
                null,
                false,
                null,
                100,
            ),
            'with range' => array(
                array('oob', 206),
                array(
                    'SERVER' => array(
                        'HTTP_RANGE' => 'bytes=1-3',
                    ),
                ),
                $file,
                null,
                null,
                true,
            ),
            'with range prefix' => array(
                array('obar', 206),
                array(
                    'SERVER' => array(
                        'HTTP_RANGE' => 'bytes=2-',
                    ),
                ),
                $file,
                null,
                null,
                true,
            ),
            'with range suffix' => array(
                array('bar', 206),
                array(
                    'SERVER' => array(
                        'HTTP_RANGE' => 'bytes=-3',
                    ),
                ),
                $file,
                null,
                null,
                true,
            ),
            'invalid range' => array(
                array('', 416),
                array(
                    'SERVER' => array(
                        'HTTP_RANGE' => 'bytes=1',
                    ),
                ),
                $file,
                null,
                null,
                true,
            ),
        );
    }

    public function testSendFileTwice()
    {
        $this->expectOutputString('foobar');

        $this->fw->sendFile(TEST_DATA . '/files/foo.txt');
        $this->fw->sendFile(TEST_DATA . '/files/none.txt');

        $this->assertSame(200, $this->fw->code());
    }

    public function testHeader()
    {
        $this->assertFalse($this->fw->hasHeader('Content-Type'));

        $this->fw->setHeaders(array('Content-Type' => 'foo'), true);

        $this->assertTrue($this->fw->hasHeader('Content-Type'));
        $this->assertTrue($this->fw->hasHeader('content-type'));

        $this->fw->removeHeaders('content-type');

        $this->assertFalse($this->fw->hasHeader('Content-Type'));
    }

    /** @dataProvider runProvider */
    public function testRun($expected, array $env = null)
    {
        $fw = Fw::create(null, ($env ?? array()) + array('QUIET' => true));
        $fw->setErrorTemplate('cli', '[CLI] {message}');
        $fw->setErrorTemplate('html', '[HTML] {message}');

        $fw->route('GET /', static fn() => 'home');
        $fw->route('GET /is-debug', static fn(Fw $fw) => array('debug' => $fw->isDebug()));
        $fw->route('GET /foo/@bar/@baz', static fn($bar, $baz) => array($bar, $baz));
        $fw->route('POST /drink/@any*?', static fn($any = 'not thristy') => $any);
        $fw->route('GET @eater /eat/@foods* [name=eater,eat,drinks]', static function(Fw $fw, $foods) {
            $match = $fw->getMatch();
            $aliases = $fw->getAliases();

            return isset($aliases['eater']) ? $foods . '-' . $match['name'] . '-' . implode(':', $match['tags']) : null;
        });
        $fw->route('GET /result', static fn() => static fn (Fw $fw) => $fw->send('result'));
        $fw->route('GET /send-twice', static function(Fw $fw) {
            echo 'line 1';

            return $fw->send('line 2');
        });
        $fw->route('GET /limited [kbps=100]', static fn() => 'it is actually limited by 100 kbps');
        $fw->run();

        $this->assertSame($expected, $fw->getOutput());
    }

    public function runProvider()
    {
        return array(
            'home' => array('home'),
            'named' => array('{"debug":false}', array(
                'SERVER' => array(
                    'REQUEST_URI' => '/is-debug',
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
            'not found' => array('[CLI] [404 - Not Found] GET /eat', array(
                'SERVER' => array(
                    'REQUEST_URI' => '/eat',
                ),
            )),
            'not found html' => array('[HTML] [404 - Not Found] GET /eat', array(
                'DEBUG' => true,
                'CLI' => false,
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
            'not found by verb' => array('[CLI] [404 - Not Found] GET /drink', array(
                'SERVER' => array(
                    'REQUEST_URI' => '/drink',
                ),
            )),
            'send twice' => array('line 2', array(
                'SERVER' => array(
                    'REQUEST_URI' => '/send-twice',
                ),
            )),
            'with limiter' => array('it is actually limited by 100 kbps', array(
                'QUIET' => false,
                'SERVER' => array(
                    'REQUEST_URI' => '/limited',
                ),
            )),
        );
    }

    public function testRunNoRoutes()
    {
        $this->fw->setErrorTemplate('cli', '[CLI] [{code} - {text}] {message}');
        $this->fw->run();

        $this->assertSame('[CLI] [500 - Internal Server Error] No route defined', $this->fw->getOutput());
    }

    public function testRunErrorJson()
    {
        $fw = Fw::create(null, array(
            'QUIET' => true,
            'DEBUG' => true,
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
                $event->setOutput('foo')->setKbps(0)->stopPropagation();
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

    public function testRouteAll()
    {
        $this->fw->routeAll(array(
            'GET /' => static fn() => 'foo',
        ));
        $this->fw->run();

        $this->assertSame('foo', $this->fw->getOutput());
    }

    public function testErrorListener()
    {
        $called = false;
        $this->fw->setErrorTemplate('cli', '[CLI] [{code} - {text}] {message}');
        $this->fw->chain(static function (Dispatcher $dispatcher) use (&$called) {
            $dispatcher->on(Fw::EVENT_ERROR, static function (ErrorEvent $event) use (&$called) {
                $called = $event->setPayload(null)->setMessage('Update ' . $event->getMessage())->getError() instanceof \LogicException;
                $event->setMime('set');

                throw new \RuntimeException($event->getMessage() . ' and Error after error');
            });
        })->error(new \LogicException('First error'));

        $this->assertTrue($called);
        $this->assertSame('[CLI] [500 - Internal Server Error] Update First error and Error after error', $this->fw->getOutput());
    }

    public function testUrl()
    {
        $fw = Fw::create(null, array(
            'CLI' => false,
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

    public function testRender()
    {
        $this->fw->setRenderSetup(TEST_DATA . '/files', 'php');

        $this->assertSame('foo: none', $this->fw->render('foo'));
        $this->assertSame('foo: bar', $this->fw->render('foo', array('foo' => 'bar')));
        $this->assertSame(null, $this->fw->render('none', null, true));
    }

    public function testRenderError()
    {
        $this->expectException('LogicException');
        $this->expectExceptionMessageMatches('/Error while loading: .+\/error.php \(Error from template\)/');

        $this->fw->setRenderSetup(TEST_DATA . '/files');
        $this->fw->render('error.php');
    }

    public function testRenderNotFound()
    {
        $this->expectException('LogicException');
        $this->expectExceptionMessage('File not found: "none"');

        $this->fw->setRenderSetup(TEST_DATA . '/files');
        $this->fw->render('none');
    }

    public function testHeaders()
    {
        $fw = Fw::create(null, array(
            'SERVER' => array(
                'CONTENT_TYPE' => 'text/plain',
                'HTTP_ACCEPT' => 'json',
            ),
        ));

        $this->assertSame('text/plain', $fw->headers('Content-Type'));
        $this->assertSame(array('Content-Type' => 'text/plain', 'Content-Length' => null, 'Accept' => 'json'), $fw->headers());
    }

    public function testMimeList()
    {
        $mimeList = array(
            'json' => 'application/json',
            'mul' => array('mul1', 'mul2'),
        );

        $this->fw->setMimeList($mimeList);

        $this->assertSame($mimeList, $this->fw->getMimeList());
        $this->assertSame('application/json', $this->fw->getMimeFile('foo.json'));
        $this->assertSame('mul1', $this->fw->getMimeFile('foo.mul'));
        $this->assertSame('application/octet-stream', $this->fw->getMimeFile('foo.txt'));
    }

    public function testBoxListener()
    {
        $box = $this->fw->getBox();

        $this->assertSame(array(), $box['COOKIE']);
        $this->assertSame(array(), $box['SESSION']);

        $box['SESSION.foo'] = 'bar';

        $this->assertSame('bar', $box['SESSION.foo']);
        $this->assertSame($_SESSION['foo'], $box['SESSION.foo']);
        $this->assertSame('bar', $box->cut('SESSION.foo'));
        $this->assertSame(array(), $box['SESSION']);
        $this->assertSame($_SESSION, $box['SESSION']);

        $box->remove('SESSION');

        $this->assertSame(array(), $box['SESSION']);
        $this->assertSame($_SESSION, $box['SESSION']);
    }

    public function testLogged()
    {
        $fw = Fw::create(null, array(
            'QUIET' => true,
        ), array(
            Log::class => array(
                'params' => array(
                    'options' => array(
                        'directory' => TEST_TMP . '/logs',
                        'filename' => 'log',
                        'enabled' => true,
                    ),
                ),
            ),
        ));
        $file = TEST_TMP . '/logs/log.txt';

        !is_file($file) || unlink($file);

        $this->assertFileDoesNotExist($file);

        $fw->error();

        $this->assertFileExists($file);
        $this->assertStringContainsString('[info] [500 - Internal Server Error] GET /', file_get_contents($file));
    }

    public function testBody()
    {
        $this->assertFalse($this->fw->isRaw());
        $this->assertTrue($this->fw->setRaw(true)->isRaw());
        $this->assertFalse($this->fw->setRaw(false)->isRaw());

        $this->assertSame(array('foo' => 'bar'), $this->fw->setBody('{"foo":"bar"}')->getJson());
    }

    public function testGmDate()
    {
        $tz = new \DateTimeZone('GMT');
        $fmt = \DateTimeInterface::RFC7231;
        $now = new \DateTime('now', $tz);
        $yes = new \DateTime('yesterday', $tz);
        $tom = new \DateTime('tomorrow', $tz);
        $t1 = new \DateTime('+2 hours', $tz);
        $t2 = new \DateTime('-2 hours', $tz);

        $this->assertSame($now->format($fmt), Fw::gmDate($now));
        $this->assertSame($yes->format($fmt), Fw::gmDate($yes));
        $this->assertSame($tom->format($fmt), Fw::gmDate($tom));
        $this->assertSame($tom->format($fmt), Fw::gmDate('tomorrow'));

        $this->assertSame($t1->format($fmt), Fw::gmDate($t1->getTimestamp(), $diff));
        $this->assertSame(7200, $diff);

        $this->assertSame($t2->format($fmt), Fw::gmDate($t2->getTimestamp(), $diff));
        $this->assertSame(-7200, $diff);

        $this->assertSame($t2->format($fmt), Fw::gmDate(-7200, $diff));
        $this->assertSame(-7200, $diff);
    }

    public function testRemoveCookie()
    {
        $this->assertEmpty($this->fw->getCookie());

        $this->fw->setCookie('foo', 'bar');

        $this->assertSame('bar', $this->fw->getCookie('foo'));
        $this->assertRegExp('/^foo=bar; Domain=localhost; HttpOnly; SameSite=Lax$/', $this->fw->getHeader('set-cookie')[0]);

        // remove
        $this->fw->removeCookie('foo');

        $this->assertEmpty($this->fw->getCookie());
        $this->assertRegExp('/^foo=deleted; Expires=.+ GMT; Max-Age=-2592000; Domain=localhost; HttpOnly; SameSite=Lax$/', $this->fw->getHeader('set-cookie')[1]);
    }

    /** @dataProvider cookieProvider */
    public function testCookie(string $expected, string $name, string $value = null, array $jar = null)
    {
        $this->fw->setCookieJar($jar ?? array());

        $this->assertEmpty($this->fw->getCookie());

        $this->fw->setCookie($name, $value);

        $this->assertSame($value, $this->fw->getCookie($name));
        $this->assertRegExp($expected, $this->fw->getHeader('set-cookie')[0]);
    }

    public function cookieProvider()
    {
        return array(
            'normal' => array(
                '/^foo=bar; Domain=localhost; HttpOnly; SameSite=Lax$/',
                'foo',
                'bar',
            ),
            'exp in 2 hour' => array(
                '/^foo=bar; Expires=.+ GMT; Max-Age=7200; Domain=localhost; HttpOnly; SameSite=Lax$/',
                'foo',
                'bar',
                array('expires' => '+2 hours'),
            ),
            'with secure path and custom samesite' => array(
                '/^foo=bar; Domain=localhost; Path=\/foo; Secure; HttpOnly; SameSite=None$/',
                'foo',
                'bar',
                array('path' => '/foo', 'secure' => true, 'samesite' => 'None'),
            ),
        );
    }

    /** @dataProvider cookieExceptionProvider */
    public function testCookieException(string $expected, array $jar)
    {
        $this->expectException('LogicException');
        $this->expectExceptionMessage($expected);

        $this->fw->setCookieJar($jar);
        $this->fw->setCookie('foo');
    }

    public function cookieExceptionProvider()
    {
        return array(
            'invalid samesite' => array(
                'Invalid samesite value: foo',
                array(
                    'samesite' => 'foo',
                ),
            ),
            'samesite none but not secured' => array(
                'Samesite None require a secure context',
                array(
                    'samesite' => 'none',
                ),
            ),
        );
    }

    public function testSession()
    {
        $this->assertEmpty($this->fw->getSession());

        $this->fw->setSession('foo', 'bar');

        $this->assertSame('bar', $this->fw->getSession('foo'));
        $this->assertSame(array('foo' => 'bar'), $this->fw->getSession());
        $this->assertSame($_SESSION, $this->fw->getSession());
        $this->assertSame($GLOBALS['_SESSION'], $this->fw->getSession());

        $this->assertSame('bar', $this->fw->flashSession('foo'));
        $this->assertEmpty($this->fw->getSession());
        $this->assertSame($_SESSION, $this->fw->getSession());
        $this->assertSame($GLOBALS['_SESSION'], $this->fw->getSession());
    }

    public function testLoadConfig()
    {
        $this->fw->load(TEST_DATA . '/files/config.php')->run();

        $box = $this->fw->getBox();
        $di = $this->fw->getContainer();

        $this->assertSame('home', $this->fw->getOutput());
        $this->assertSame('bar', $box['foo']);
        $this->assertSame(true, $box['from_callable']);
        $this->assertSame(true, $box['is_cli']);
        $this->assertInstanceOf('stdClass', $std = $di->make('foo'));
        $this->assertNotSame($std, $di->make('foo'));
        $this->assertNotSame($std, $di->make('stdClass'));
    }

    public function testExtending()
    {
        $fw = FwExt::create();
        $di = $fw->getContainer();

        $this->assertSame($fw, $di->make(Fw::class));
        $this->assertSame($di->make(FwExt::class), $di->make(Fw::class));
    }
}
