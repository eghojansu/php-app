<?php

use Ekok\App\Env;
use Ekok\Logger\Log;
use Ekok\Cache\Cache;
use Ekok\Container\Di;
use Ekok\App\Event\Error as ErrorEvent;
use Ekok\App\Event\Redirect as RedirectEvent;
use Ekok\App\Event\Request as RequestEvent;
use Ekok\App\Event\RouteMatch as RouteMatchEvent;
use Ekok\App\Redirection;
use Ekok\EventDispatcher\Dispatcher;
use Ekok\EventDispatcher\Event;
use Ekok\EventDispatcher\EventSubscriberInterface;
use Ekok\Router\Router;

class EnvTest extends \Codeception\Test\Unit
{
    /** @var Env */
    private $env;

    public function _before()
    {
        $this->env = self::createEnv();

        header_remove();
    }

    private static function createEnv(array $data = null, array $rules = null, bool $quiet = true): Env
    {
        if (!isset($data['quiet'])) {
            $data['quiet'] = $quiet;
        }

        $env = Env::create('test', $data);

        if ($rules) {
            $env->getContainer()->register($rules);
        }

        return $env;
    }

    public function testDependencies()
    {
        $this->assertSame($this->env->getDispatcher(), $this->env->getContainer()->make(Dispatcher::class));
        $this->assertSame($this->env->getCache(), $this->env->getContainer()->make(Cache::class));
        $this->assertSame($this->env->getRouter(), $this->env->getContainer()->make(Router::class));
        $this->assertSame($this->env->getLog(), $this->env->getContainer()->make(Log::class));
        $this->assertSame($this->env, $this->env->getContainer()->make(Env::class));
        $this->assertSame($this->env->getContainer(), $this->env->getContainer()->make(Di::class));
    }

    public function testInitializationAndState()
    {
        $this->assertArrayHasKey('SERVER', $this->env->getData());
        $this->assertSame(array(), $this->env->getPost());
        $this->assertSame(array(), $this->env->getQuery());
        $this->assertSame(array(), $this->env->getFiles());
        $this->assertSame($_SERVER, $this->env->getServer());
        $this->assertSame($_ENV, $this->env->getEnv());
        $this->assertSame('GET', $this->env->getVerb());
        $this->assertSame(true, $this->env->isVerb('GET'));
        $this->assertSame(false, $this->env->isVerb('POST'));
        $this->assertSame('/', $this->env->getPath());
        $this->assertSame('', $this->env->getBasePath());
        $this->assertSame('', $this->env->getEntry());
        $this->assertSame('HTTP/1.1', $this->env->getProtocol());
        $this->assertSame('http', $this->env->getScheme());
        $this->assertSame(false, $this->env->isSecure());
        $this->assertSame('localhost', $this->env->getHost());
        $this->assertSame(80, $this->env->getPort());
        $this->assertSame(null, $this->env->getMime());
        $this->assertSame('UTF-8', $this->env->getCharset());
        $this->assertSame(false, $this->env->isDebug());
        $this->assertSame(false, $this->env->isBuiltin());
        $this->assertSame(true, $this->env->isCli());
        $this->assertSame(false, $this->env->isContentType('json'));
        $this->assertSame(false, $this->env->isJson());
        $this->assertSame(false, $this->env->wantsJson());
        $this->assertSame(false, $this->env->isMultipart());
        $this->assertSame(false, $this->env->accept('json'));
        $this->assertSame('*/*', $this->env->accept());
        $this->assertSame(array('*/*'), $this->env->accept(null, true));
        $this->assertSame(false, $this->env->acceptLanguage('json'));
        $this->assertSame('*', $this->env->acceptLanguage());
        $this->assertSame(array('*'), $this->env->acceptLanguage(null, true));
        $this->assertSame('', $this->env->getContentType());
        $this->assertSame('http://localhost', $this->env->getBaseUrl());
        $this->assertSame('test', $this->env->getName());
        $this->assertSame(true, $this->env->isName('test'));
        $this->assertSame(null, $this->env->getProjectDir());
        $this->assertSame('/var', $this->env->getTmpDir());
        $this->assertSame('3lxu8sqeg7sw8', $this->env->getSeed());
        $this->assertSame('lang', $this->env->getLanguageKey());
        $this->assertStringContainsString('{code} - {text}', $this->env->getErrorTemplate('CLI'));

        // mutate
        $this->assertSame('POST', $this->env->setVerb('post')->getVerb());
        $this->assertSame('/foo', $this->env->setPath('/foo')->getPath());
        $this->assertSame('/foo', $this->env->setPath('foo')->getPath());
        $this->assertSame('/foo', $this->env->setBasePath('/foo')->getBasePath());
        $this->assertSame('/foo', $this->env->setBasePath('foo')->getBasePath());
        $this->assertSame('', $this->env->setBasePath('')->getBasePath());
        $this->assertSame('foo.php', $this->env->setEntry('foo.php')->getEntry());
        $this->assertSame('HTTP/1.0', $this->env->setProtocol('HTTP/1.0')->getProtocol());
        $this->assertSame('https', $this->env->setScheme('https')->getScheme());
        $this->assertSame(true, $this->env->setSecure(true)->isSecure());
        $this->assertSame('foo', $this->env->setHost('foo')->getHost());
        $this->assertSame(8080, $this->env->setPort(8080)->getPort());
        $this->assertSame('text/html', $this->env->setMime('text/html')->getMime());
        $this->assertSame('UTF-88', $this->env->setCharset('UTF-88')->getCharset());
        $this->assertSame(true, $this->env->setDebug(true)->isDebug());
        $this->assertSame('http://localhost/foo', $this->env->setBaseUrl('http://localhost/foo')->getBaseUrl());
        $this->assertSame('dev', $this->env->setName('dev')->getName());
        $this->assertSame('dev', $this->env->setProjectDir('dev//')->getProjectDir());
        $this->assertSame('dev', $this->env->setTmpDir('dev//')->getTmpDir());
        $this->assertSame('dev', $this->env->setSeed('dev')->getSeed());
        $this->assertSame('_lang', $this->env->setLanguageKey('_lang')->getLanguageKey());
    }

    public function testResponse()
    {
        $this->assertSame(true, $this->env->isQuiet());
        $this->assertSame(false, $this->env->sent());
        $this->assertSame(null, $this->env->code());
        $this->assertSame(null, $this->env->text());
        $this->assertSame(null, $this->env->getOutput());
        $this->assertSame(array(), $this->env->getHeaders());

        // mutate
        $this->assertSame(false, $this->env->setQuiet(false)->isQuiet());
        $this->assertSame(200, $this->env->status(200)->code());
        $this->assertSame('OK', $this->env->text());
        $this->assertSame('foo', $this->env->setOutput('foo')->getOutput());
        $this->assertSame('["foo"]', $this->env->setOutput(array('foo'))->getOutput());
        $this->assertSame('text/html', $this->env->getMime());

        $actual = $this->env->setHeaders(array(
            'location' => 'url',
            'accept' => array('text/html', 'application/json'),
        ))->getHeaders();
        $expected = array(
            'location' => array('url'),
            'accept' => array('text/html', 'application/json'),
        );

        $this->assertSame($expected, $actual);
        $this->assertSame($expected['location'], $this->env->getHeader('location'));
        $this->assertSame($expected['location'], $this->env->getHeader('Location'));
        $this->assertSame(array(), $this->env->getHeader('unknown'));
    }

    public function testSendResponse()
    {
        $this->expectOutputString('foo');

        $this->env->setQuiet(false);
        $this->env->send('foo', array('custom' => 'header'));

        if (function_exists('xdebug_get_headers')) {
            $headers = xdebug_get_headers();
            $expected = array(
                'Custom: header',
                'Content-Type: text/html;charset=UTF-8',
                'Content-Length: 3',
            );

            $this->assertSame($expected, $headers);
        }

        $this->assertSame(200, $this->env->code());
        $this->assertSame('text/html', $this->env->getMime());
        $this->assertSame('foo', $this->env->getOutput());
    }

    public function testSendCallableResponse()
    {
        $this->expectOutputString('foo');

        $this->env->setBuffering(false);
        $this->env->send(static fn() => print('foo'), array('custom' => 'header'), 404, 'foo');

        if (function_exists('xdebug_get_headers')) {
            $headers = xdebug_get_headers();
            $expected = array(
                'Custom: header',
                'Content-Type: foo;charset=UTF-8',
            );

            $this->assertSame($expected, $headers);
        }

        $this->assertSame(404, $this->env->code());
        $this->assertSame('foo', $this->env->getMime());
        $this->assertSame(null, $this->env->getOutput());
    }

    public function testSendTwice()
    {
        $this->env->send('foo');
        $this->env->send('bar');

        $this->assertSame('foo', $this->env->getOutput());
    }

    /** @dataProvider sendFileProvider */
    public function testSendFile(array|string $expected, array $data = null, ...$args)
    {
        $env = self::createEnv($data);

        list($output, $code) = ((array) $expected) + array('', 200);

        $this->expectOutputString($output);

        $env->sendFile(...$args);

        $this->assertSame($code, $env->code());
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

        $this->env->sendFile(TEST_DATA . '/files/foo.txt');
        $this->env->sendFile(TEST_DATA . '/files/none.txt');

        $this->assertSame(200, $this->env->code());
    }

    public function testHeader()
    {
        $this->assertFalse($this->env->hasHeader('Content-Type'));

        $this->env->setHeaders(array('Content-Type' => 'foo'), true);

        $this->assertTrue($this->env->hasHeader('Content-Type'));
        $this->assertTrue($this->env->hasHeader('content-type'));

        $this->env->removeHeaders('content-type');

        $this->assertFalse($this->env->hasHeader('Content-Type'));
    }

    /** @dataProvider runProvider */
    public function testRun($expected, array $data = null)
    {
        $env = self::createEnv($data);
        $env->setErrorTemplate('cli', '[CLI] {message}');
        $env->setErrorTemplate('html', '[HTML] {message}');

        $env->route('GET /', static fn() => 'home');
        $env->route('GET /is-debug', static fn(Env $env) => array('debug' => $env->isDebug()));
        $env->route('GET /foo/@bar/@baz', static fn($bar, $baz) => array($bar, $baz));
        $env->route('POST /drink/@any*?', static fn($any = 'not thristy') => $any);
        $env->route('GET @eater /eat/@foods* [name=eater,eat,drinks]', static function(Env $env, Router $router, $foods) {
            $match = $env->getMatch();
            $aliases = $router->getAliases();

            return isset($aliases['eater']) ? $foods . '-' . $match['name'] . '-' . implode(':', $match['tags']) : null;
        });
        $env->route('GET /result', static fn() => static fn (Env $env) => $env->send('result'));
        $env->route('GET /send-twice', static function(Env $env) {
            echo 'line 1';

            return $env->send('line 2');
        });
        $env->route('GET /limited [kbps=100]', static fn() => 'it is actually limited by 100 kbps');
        $env->route('GET /rich-tags [foo,bar,param=foo;bar,param2=foo,param2=bar]', static function (Env $env) {
            return (
                'tags=' . implode(',', $env->getMatch('tags'))
                . '|param=' . implode(',', $env->getMatch('param'))
                . '|param2=' . implode(',', $env->getMatch('param2'))
            );
        });
        $env->run();

        $this->assertSame($expected, $env->getOutput());
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
            'not found' => array('[CLI] localhost [404 - Not Found] GET /eat', array(
                'SERVER' => array(
                    'REQUEST_URI' => '/eat',
                ),
            )),
            'not found html' => array('[HTML] localhost [404 - Not Found] GET /eat', array(
                'debug' => true,
                'cli' => false,
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
            'not found by verb' => array('[CLI] localhost [404 - Not Found] GET /drink', array(
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
                'quiet' => false,
                'SERVER' => array(
                    'REQUEST_URI' => '/limited',
                ),
            )),
            'tag function' => array('tags=foo,bar|param=foo,bar|param2=foo,bar', array(
                'SERVER' => array(
                    'REQUEST_URI' => '/rich-tags',
                ),
            )),
        );
    }

    public function testRunNoRoutes()
    {
        $this->env->setErrorTemplate('cli', '[CLI] [{code} - {text}] {message}');
        $this->env->run();

        $this->assertSame('[CLI] [404 - Not Found] localhost [404 - Not Found] GET /', $this->env->getOutput());
    }

    public function testRunErrorJson()
    {
        $env = self::createEnv(array(
            'quiet' => true,
            'debug' => true,
            'SERVER' => array(
                'REQUEST_URI' => '/none',
                'HTTP_ACCEPT' => 'json',
            ),
        ));

        $env->route('GET /', static fn () => null);
        $env->run();

        $this->assertStringStartsWith('{"code":404,"text":"Not Found"', $env->getOutput());
        $this->assertStringContainsString('"trace":', $env->getOutput());
    }

    public function testRunInteruption()
    {
        $this->env->listen(RequestEvent::class, static function (RequestEvent $event) {
            $event->setOutput('foo')->setKbps(0);
        });
        $this->env->run();

        $this->assertSame('foo', $this->env->getOutput());
    }

    public function testRunDirectInteruption()
    {
        $this->env->listen(RequestEvent::class, static function (Env $env) {
            $env->send('my data');
        });
        $this->env->run();

        $this->assertSame('my data', $this->env->getOutput());
    }

    public function testRouteInvalid()
    {
        $this->expectException('LogicException');
        $this->expectExceptionMessage('Invalid route: "@"');

        $this->env->route('@');
    }

    public function testRouteNoPath()
    {
        $this->expectException('LogicException');
        $this->expectExceptionMessage('No path defined in route: "GET"');

        $this->env->route('GET');
    }

    public function testRouteNoPathAlias()
    {
        $this->expectException('LogicException');
        $this->expectExceptionMessage('Route not exists: home');

        $this->env->route('GET @home');
    }

    public function testRouteAll()
    {
        $this->env->routeAll(array(
            'GET /' => static fn() => 'foo',
        ));
        $this->env->run();

        $this->assertSame('foo', $this->env->getOutput());
    }

    public function testErrorListener()
    {
        $called = false;
        $this->env->setErrorTemplate('cli', '[CLI] [{code} - {text}] {message}');
        $this->env->listen(ErrorEvent::class, static function (ErrorEvent $event) use (&$called) {
            $called = $event->setPayload(null)->setMessage('Update ' . $event->getMessage())->getError() instanceof \LogicException;
            $event->setMime('set');

            throw new \RuntimeException($event->getMessage() . ' and Error after error');
        });
        $this->env->error(new \LogicException('First error'));

        $this->assertTrue($called);
        $this->assertSame('[CLI] [500 - Internal Server Error] Update First error and Error after error', $this->env->getOutput());
    }

    public function testUrl()
    {
        $env = self::createEnv(array(
            'cli' => false,
            'SERVER' => array(
                'REQUEST_URI' => '/basedir/front.php/path',
                'SCRIPT_NAME' => '/basedir/front.php',
            ),
        ));
        $env->route('GET @home /', static fn() => null);
        $env->route('GET @view /item/@id', static fn() => null);
        $env->route('GET @optional /optional/@id?', static fn() => null);
        $env->route('GET @all /all/@params*', static fn() => null);
        $env->route('GET @delete /item/delete/@id/@type', static fn() => null);

        $this->assertSame('/', $env->alias('home'));
        $this->assertSame('/?q=foo', $env->alias('home', array('q' => 'foo')));
        $this->assertSame('/item/1?q=foo', $env->alias('view', array('id' => '1', 'q' => 'foo')));
        $this->assertSame('/optional/1', $env->alias('optional', array('id' => '1')));
        $this->assertSame('/optional', $env->alias('optional'));
        $this->assertSame('/all/params/as/string', $env->alias('all', array('params' => 'params/as/string')));
        $this->assertSame('/item/delete/1/foo', $env->alias('delete', array('id' => '1', 'type' => 'foo')));
        $this->assertSame('/unnamed', $env->alias('unnamed'));

        $this->assertSame('/basedir/front.php/', $env->url('home'));
        $this->assertSame('http://localhost/basedir/front.php/', $env->url('home', null, true));
        $this->assertSame('/basedir/assets/style.css', $env->baseurl('assets/style.css'));
        $this->assertSame('http://localhost/basedir/assets/style.css', $env->baseurl('assets/style.css', null, true));
        $this->assertSame('http://localhost/basedir/front.php/path', $env->uri());
        $this->assertSame('/basedir/front.php/path', $env->uri(false));

        $this->expectException('LogicException');
        $this->expectExceptionMessage('Route param required: id@view');
        $env->alias('view');
    }

    public function testRender()
    {
        $this->env->setRenderSetup(TEST_DATA . '/files', 'php');

        $this->assertSame('foo: none', $this->env->render('foo'));
        $this->assertSame('foo: bar', $this->env->render('foo', array('foo' => 'bar')));
        $this->assertSame(null, $this->env->render('none', null, true));
    }

    public function testRenderError()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessageMatches('/Error in file: .+\/error.php \(Error from template\)/');

        $this->env->setRenderSetup(TEST_DATA . '/files');
        $this->env->render('error.php');
    }

    public function testRenderNotFound()
    {
        $this->expectException('LogicException');
        $this->expectExceptionMessage('File not found: "none"');

        $this->env->setRenderSetup(TEST_DATA . '/files');
        $this->env->render('none');
    }

    public function testHeaders()
    {
        $env = self::createEnv(array(
            'SERVER' => array(
                'CONTENT_TYPE' => 'text/plain',
                'HTTP_ACCEPT' => 'json',
            ),
        ));

        $this->assertSame('text/plain', $env->headers('Content-Type'));
        $this->assertSame(array('Content-Type' => 'text/plain', 'Content-Length' => null, 'Accept' => 'json'), $env->headers());
    }

    public function testMimeList()
    {
        $mimeList = array(
            'json' => 'application/json',
            'mul' => array('mul1', 'mul2'),
        );

        $this->env->setMimeList($mimeList);

        $this->assertSame($mimeList, $this->env->getMimeList());
        $this->assertSame('application/json', $this->env->getMimeFile('foo.json'));
        $this->assertSame('mul1', $this->env->getMimeFile('foo.mul'));
        $this->assertSame('application/octet-stream', $this->env->getMimeFile('foo.txt'));
    }

    public function testLogged()
    {
        $env = self::createEnv(null, array(
            Log::class => array(
                'params' => array(
                    'options' => array(
                        'directory' => TEST_TMP . '/logs',
                        'filename' => 'log',
                        'enable' => true,
                    ),
                ),
            ),
        ));
        $file = TEST_TMP . '/logs/log.txt';

        !is_file($file) || unlink($file);

        $this->assertFileDoesNotExist($file);

        $env->error();

        $this->assertFileExists($file);
        $this->assertStringContainsString('[info] localhost [500 - Internal Server Error] GET /', file_get_contents($file));
    }

    public function testBody()
    {
        $this->assertFalse($this->env->isRaw());
        $this->assertTrue($this->env->setRaw(true)->isRaw());
        $this->assertFalse($this->env->setRaw(false)->isRaw());

        $this->assertSame(array('foo' => 'bar'), $this->env->setBody('{"foo":"bar"}')->getJson());
    }

    public function testRemoveCookie()
    {
        $this->assertEmpty($this->env->getCookie());

        $this->env->setCookie('foo', 'bar');

        $this->assertSame('bar', $this->env->getCookie('foo'));
        $this->assertRegExp('/^foo=bar; Domain=localhost; HttpOnly; SameSite=Lax$/', $this->env->getHeader('set-cookie')[0]);

        // remove
        $this->env->removeCookie('foo');

        $this->assertEmpty($this->env->getCookie());
        $this->assertRegExp('/^foo=deleted; Expires=.+ GMT; Max-Age=-2592000; Domain=localhost; HttpOnly; SameSite=Lax$/', $this->env->getHeader('set-cookie')[1]);
    }

    /** @dataProvider cookieProvider */
    public function testCookie(string $expected, string $name, string $value = null, array $jar = null)
    {
        $this->env->setCookieJar($jar ?? array());

        $this->assertEmpty($this->env->getCookie());

        $this->env->setCookie($name, $value);

        $this->assertSame($value, $this->env->getCookie($name));
        $this->assertRegExp($expected, $this->env->getHeader('set-cookie')[0]);
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

        $this->env->setCookieJar($jar);
        $this->env->setCookie('foo');
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
        $this->assertEmpty($this->env->getSession());

        $this->env->setSession('foo', 'bar');
        $this->env->setSession('bar', 'baz');
        $this->env->setSession('baz', 'qux');

        $this->assertSame('bar', $this->env->getSession('foo'));
        $this->assertSame(array('foo' => 'bar', 'bar' => 'baz', 'baz' => 'qux'), $this->env->getSession());
        $this->assertSame($_SESSION, $this->env->getSession());
        $this->assertSame($GLOBALS['_SESSION'], $this->env->getSession());

        $this->assertSame('bar', $this->env->flashSession('foo'));
        $this->assertNull($this->env->getSession('foo'));
        $this->assertNull($this->env->removeSession('bar')->getSession('bar'));
        $this->assertSame(array('baz' => 'qux'), $this->env->getSession());

        $this->env->removeSession();

        $this->assertEmpty($this->env->getSession());
        $this->assertSame($_SESSION, $this->env->getSession());
        $this->assertSame($GLOBALS['_SESSION'], $this->env->getSession());
    }

    public function testLoadConfig()
    {
        $this->env->load(TEST_DATA . '/files/config.php')->run();

        $di = $this->env->getContainer();

        $this->assertSame('home', $this->env->getOutput());
        $this->assertSame('bar', $this->env->foo);
        $this->assertSame(true, $this->env->from_callable);
        $this->assertSame(true, $this->env->is_cli);
        $this->assertInstanceOf('stdClass', $std = $di->make('foo'));
        $this->assertNotSame($std, $di->make('foo'));
        $this->assertNotSame($std, $di->make('stdClass'));
    }

    public function testExtending()
    {
        $env = MyEnv::create();
        $di = $env->getContainer();

        $this->assertSame($env, $di->make(Env::class));
        $this->assertSame($di->make(MyEnv::class), $di->make(Env::class));
    }

    public function testDataGetter()
    {
        $env = self::createEnv(array(
            'foo' => 'bar',
            'GET' => array('int' => '100'),
            'POST' => array('int' => '100'),
            'FILES' => array('foo' => 'bar'),
            'ENV' => array('foo' => 'bar'),
        ));

        $this->assertSame(true, $env->has('foo'));
        $this->assertSame('bar', $env->get('foo'));
        $this->assertSame('bar', $env->getFiles('foo'));
        $this->assertSame(null, $env->getServer('REQUEST_METHOD'));
        $this->assertSame('bar', $env->getEnv('foo'));
        $this->assertSame('100', $env->getQuery('int'));
        $this->assertSame(100, $env->getQueryInt('int'));
        $this->assertSame('100', $env->getPost('int'));
        $this->assertSame(100, $env->getPostInt('int'));
        $this->assertSame($env->path, $env->getPath());
    }

    /** @dataProvider backUrlProvider */
    public function testSetBackUrl(string|null $prevUrl, string|null $backUrl, array $data = null)
    {
        $env = self::createEnv($data);

        if (isset($data['mode'])) {
            $env->setNavigationMode($data['mode']);
        }

        if (isset($data['key'])) {
            $env->setNavigationKey($data['key']);
        }

        if (isset($data['burl'])) {
            $env->setBackUrl($data['burl']);
        }

        if (isset($data['purl'])) {
            $env->setPreviousUrl($data['purl']);
        }

        $this->assertSame($prevUrl, $env->getPreviousUrl());

        $env->send();

        $this->assertSame($backUrl, $env->getBackUrl());
    }

    public function backUrlProvider()
    {
        return array(
            'from header (default)' => array(
                'foo',
                'http://localhost/',
                array(
                    'SERVER' => array(
                        'HTTP_REFERER' => 'foo',
                    ),
                ),
            ),
            'from cookie' => array(
                'foo',
                'http://localhost/',
                array(
                    'mode' => 'cookie',
                    'COOKIE' => array('referer' => 'foo'),
                ),
            ),
            'from session' => array(
                null,
                'http://localhost/',
                array(
                    'mode' => 'session',
                ),
            ),
            'from query' => array(
                'foo',
                'http://localhost/?referer=foo',
                array(
                    'mode' => 'query',
                    'GET' => array(
                        'referer' => 'foo',
                    ),
                ),
            ),
            'from none' => array(
                null,
                'http://localhost/',
                array(
                    'mode' => 'none',
                ),
            ),
            'custom set' => array(
                'prev',
                'back',
                array(
                    'mode' => 'cookie',
                    'key' => 'custom',
                    'burl' => 'back',
                    'purl' => 'prev',
                ),
            ),
        );
    }

    public function testEventDispatcher()
    {
        $this->env->listen('foo', static function (Event $event) {
            $event->stopPropagation();
        });

        $this->env->dispatch($event = Event::named('foo'));
        $this->assertTrue($event->isPropagationStopped());

        // remove
        $this->env->unlisten('foo');

        $this->env->dispatch($event = Event::named('foo'));
        $this->assertFalse($event->isPropagationStopped());
    }

    public function testChain()
    {
        $called = false;
        $env = $this->env->chain(static function () use (&$called) {
            $called = true;
        });

        $this->assertTrue($called);
        $this->assertSame($env, $this->env);
    }

    /** @dataProvider reroutingProvider */
    public function testRerouting(string $expected, array $data = null)
    {
        $env = self::createEnv($data);
        $env->rerouteAll(array(
            'GET /' => 'home',
            'GET /temporary' => array('base', false),
        ));
        $env->listen(RedirectEvent::class, static function (RedirectEvent $event, Env $env) {
            $event->setOutput(sprintf(
                '[%s - %s] %s %s redirected to %s%s',
                $event->getCode(),
                $event->getText(),
                $env->getVerb(),
                $env->getPath(),
                $event->getUrl(),
                $event->isPermanent() ? ' [permanent]' : '',
            ));
        });
        $env->run();

        $this->assertSame($expected, $env->getOutput());
    }

    public function reroutingProvider()
    {
        return array(
            'std' => array(
                '[301 - Moved Permanently] GET / redirected to home [permanent]',
            ),
            'not permanent' => array(
                '[302 - Found] GET /temporary redirected to base',
                array(
                    'SERVER' => array(
                        'REQUEST_URI' => '/temporary',
                    ),
                ),
            ),
        );
    }

    public function testRedirectTo()
    {
        $this->env->listen(RedirectEvent::class, static function (RedirectEvent $event, Env $env) {
            $event->setOutput(sprintf(
                '[%s - %s] %s %s redirected to %s%s',
                $event->getCode(),
                $event->getText(),
                $env->getVerb(),
                $env->getPath(),
                $event->getUrl(),
                $event->isPermanent() ? ' [permanent]' : '',
            ));
        });
        $this->env->redirectTo('home');

        $expected = '[302 - Found] GET / redirected to http://localhost/home';
        $actual = $this->env->getOutput();

        $this->assertSame($expected, $actual);
    }

    public function testRedirectBack()
    {
        $this->env->listen(RedirectEvent::class, static function (RedirectEvent $event, Env $env) {
            $event->setOutput(sprintf(
                '[%s - %s] %s %s redirected to %s%s',
                $event->getCode(),
                $event->getText(),
                $env->getVerb(),
                $env->getPath(),
                $event->getUrl(),
                $event->isPermanent() ? ' [permanent]' : '',
            ));
        });
        $this->env->setPreviousUrl('foo');
        $this->env->redirectBack();

        $expected = '[303 - See Other] GET / redirected to foo';
        $actual = $this->env->getOutput();

        $this->assertSame($expected, $actual);
        $this->assertArrayNotHasKey('Location', $this->env->getHeaders());
    }

    public function testRedirect()
    {
        $this->env->listen(RedirectEvent::class, static function (RedirectEvent $event) {
            $event->setUrl('updated');
        });
        $this->env->redirect('/');

        $this->assertNull($this->env->getOutput());
        $this->assertSame('updated', $this->env->getHeader('Location')[0]);
    }

    public function testRedirectIntercepted()
    {
        $this->env->listen(RedirectEvent::class, static function (Env $env) {
            $env->send('foo');
        });
        $this->env->redirect('/');

        $this->assertSame('foo', $this->env->getOutput());
    }

    public function testEventByName()
    {
        $this->env->listen('onRequest', static fn(RequestEvent $event) => $event->setOutput('foo'));
        $this->env->run();

        $this->assertSame('foo', $this->env->getOutput());
    }

    public function testData()
    {
        $this->assertSame('POST', $this->env->set('verb', 'post')->get('verb'));
        $this->assertSame('bar', $this->env->set('foo', 'bar')->get('foo'));
        $this->assertSame('foo', $this->env->setData(array('name' => 'FOO'))->getName());

        $this->expectException('LogicException');
        $this->expectExceptionMessage('Data is readonly: env');

        $this->env->set('env', 'foo');
    }

    public function testDependency()
    {
        $this->env->rule('foo', 'stdClass');
        $this->env->rule(array(
            'bar' => array(
                'shared' => true,
                'class' => 'DateTime',
            ),
        ));

        $std = $this->env->make('foo');
        $std2 = $this->env->make('foo');
        $date = $this->env->make('bar');
        $date2 = $this->env->make('bar');

        $this->assertInstanceOf('stdClass', $std);
        $this->assertInstanceOf('stdClass', $std2);
        $this->assertNotSame($std, $std2);
        $this->assertInstanceOf('DateTime', $date);
        $this->assertSame($date, $date2);
    }

    public function testSubscriber()
    {
        $this->env->addSubscribers(array(
            new class implements EventSubscriberInterface {
                public static function getSubscribedEvents(): array
                {
                    return array('foo');
                }

                public function foo(Event $event)
                {
                    $event->stopPropagation();
                }
            }
        ));

        $event = new Event();
        $this->env->dispatch($event, 'foo');

        $this->assertTrue($event->isPropagationStopped());
    }

    /** @dataProvider redirectionByExceptionProvider */
    public function testRedirectionByException(string $expected, array $data = null)
    {
        $env = self::createEnv($data);
        $env->routeAll(array(
            'GET /' => static fn() => 'Welcome home',
            'GET @route.foo /foo' => static fn() => 'Foo page',
            'GET /go-to-home' => static fn() => throw new Redirection(),
            'GET /go-to-home2' => static fn() => throw Redirection::url('/'),
            'GET /go-to-foo' => static fn() => throw Redirection::to('route.foo'),
            'GET /go-back' => static fn() => throw Redirection::back(),
        ));
        $env->listen('onRedirect', static function (RedirectEvent $event, Env $env) {
            $event->setOutput(sprintf(
                '[%s - %s] %s %s redirected to %s%s',
                $event->getCode(),
                $event->getText(),
                $env->getVerb(),
                $env->getPath(),
                $event->getUrl(),
                $event->isPermanent() ? ' [permanent]' : '',
            ));
        });
        $env->run();

        $actual = $env->getOutput();

        $this->assertSame($expected, $actual);
    }

    public function redirectionByExceptionProvider()
    {
        return array(
            'go home' => array(
                '[302 - Found] GET /go-to-home redirected to /',
                array(
                    'SERVER' => array(
                        'REQUEST_URI' => '/go-to-home',
                    ),
                ),
            ),
            'go home2' => array(
                '[302 - Found] GET /go-to-home2 redirected to /',
                array(
                    'SERVER' => array(
                        'REQUEST_URI' => '/go-to-home2',
                    ),
                ),
            ),
            'go back' => array(
                '[303 - See Other] GET /go-back redirected to /',
                array(
                    'SERVER' => array(
                        'REQUEST_URI' => '/go-back',
                    ),
                ),
            ),
            'go route' => array(
                '[302 - Found] GET /go-to-foo redirected to http://localhost/foo',
                array(
                    'SERVER' => array(
                        'REQUEST_URI' => '/go-to-foo',
                    ),
                ),
            ),
        );
    }

    public function testEnvironmentGetter()
    {
        $env = self::createEnv(array(
            'SERVER' => array(
                'HTTP_USER_AGENT' => 'test',
                'HTTP_CLIENT_IP' => '127.0.0.1,127.1.1.1',
            ),
        ));

        $this->assertSame('test', $env->getUserAgent());
        $this->assertSame(gethostname(), $env->getServerIp());
        $this->assertSame('127.0.0.1', $env->getClientIp());
        $this->assertSame('127.0.0.1', $env->getClientIp(4, 'NO_PRIV'));
    }

    public function testRecommendedLanguage()
    {
        $env = self::createEnv(array(
            'SERVER' => array(
                'HTTP_ACCEPT_LANGUAGE' => 'en',
            ),
        ));

        $this->assertSame('en', $env->wantLanguage());
    }

    public function testAuthorization()
    {
        $env = self::createEnv(array(
            'SERVER' => array(
                'HTTP_AUTHORIZATION' => 'Bearer foo',
            ),
        ));

        $this->assertSame('Bearer foo', $env->getAuthorization());
        $this->assertSame('foo', $env->getAuthorizationBearer());
    }

    public function testDirectCall()
    {
        $this->expectOutputString('printed directly');

        $env = self::createEnv(array(
            'buffering' => false,
            'quiet' => false,
            'SERVER' => array(
                'REQUEST_URI' => '/direct-call',
            ),
        ));
        $env->route('GET /direct-call', static function() {
            echo 'printed directly';
        });
        $env->run();
    }

    public function testEventOrder()
    {
        $handle = static fn($name) => static function(RequestEvent $event) use ($name) {
            $event->data[] = $name;
        };

        $this->env->listen('onRequest', $handle('a'));
        $this->env->listen('onRequest', $handle('b'));
        $this->env->listen('onRequest', $handle('c'));
        $this->env->listen('onRequest', $handle('d'), 1);
        $this->env->listen('onRequest', $handle('e'), -99);

        $this->env->dispatch($event = new RequestEvent());

        $actual = $event->data;
        $expected = array('d', 'a', 'b', 'c', 'e');

        $this->assertSame($expected, $actual);
    }

    public function testCors()
    {
        $env = self::createEnv(array(
            'SERVER' => array(
                'REQUEST_METHOD' => 'OPTIONS',
                'HTTP_ORIGIN' => 'http://foo.com',
            ),
        ));
        $env->setCors(array(
            'origin' => true,
            'expose' => 'Expose-Foo',
            'headers' => 'Allow-Foo',
            'credentials' => true,
            'ttl' => 600,
        ));
        $env->routeAll(array(
            'GET /' => static fn() => 'get home',
            'POST /' => static fn() => 'post home',
        ));
        $env->run();

        $headers = $env->getHeaders();

        $this->assertSame('http://foo.com', $headers['Access-Control-Allow-Origin'][0]);
        $this->assertSame('true', $headers['Access-Control-Allow-Credentials'][0]);
        $this->assertSame('Expose-Foo', $headers['Access-Control-Expose-Headers'][0]);
        $this->assertSame('GET,POST', $headers['Allow'][0]);
        $this->assertSame('OPTIONS,GET,POST', $headers['Access-Control-Allow-Methods'][0]);
        $this->assertSame('Allow-Foo', $headers['Access-Control-Allow-Headers'][0]);
        $this->assertSame('600', $headers['Access-Control-Max-Age'][0]);
    }

    public function testWhitelist()
    {
        $this->assertFalse($this->env->isWhitelisted('127.0.0.1'));

        $this->env->setExempt(array(
            '127.0.0.1',
            '192.168.*',
            '100.*.1.1',
        ));

        $this->assertTrue($this->env->isWhitelisted('127.0.0.1'));
        $this->assertTrue($this->env->isWhitelisted('192.168.20.1'));
        $this->assertFalse($this->env->isWhitelisted('192.169.20.1'));
        $this->assertTrue($this->env->isWhitelisted('100.100.1.1'));
        $this->assertFalse($this->env->isWhitelisted('100.100.1.2'));
    }

    public function testBlacklist()
    {
        $domain = 'dnsbl.spfbl.net';
        $domainIp = '54.233.253.229';

        $this->assertFalse($this->env->isBlacklisted('127.0.0.1'));

        $this->env->setExempt(array(
            '127.0.0.1',
            '192.168.*',
            '100.*.1.1',
        ));
        $this->env->setDnsbl(array($domain, $domainIp));

        $this->assertFalse($this->env->isBlacklisted('127.0.0.1'));
        $this->assertFalse($this->env->isBlacklisted('192.168.20.1'));
        $this->assertTrue($this->env->isBlacklisted('192.169.20.1'));
        $this->assertFalse($this->env->isBlacklisted('100.100.1.1'));
        $this->assertTrue($this->env->isBlacklisted('100.100.1.2'));
        $this->assertTrue($this->env->isBlacklisted('188.163.68.29'));
    }

    public function testSpamCheck()
    {
        $env = self::createEnv(array(
            'SERVER' => array(
                'REMOTE_ADDR' => '188.163.68.29',
            ),
        ));
        $env->setDnsbl(array(
            'dnsbl.spfbl.net',
        ));
        $env->setErrorTemplate('cli', '[CLI] {message}');
        $env->run();

        $this->assertSame('[CLI] 188.163.68.29 [403 - Forbidden] GET /', $env->getOutput());
    }

    public function testRouteInterception()
    {
        $this->env->route('GET / [rich=tags,kbps=100,foo]', 'foo');
        $this->env->listen('onRouteMatch', static function (RouteMatchEvent $match) {
            $match->setKbps($match->getKbps() - 1);
            $match->setTags(array_merge($match->getTags(), array('bar')));
            $match->setAttribute('custom', 'foo');

            $args = array(
                'separator' => ' ',
                'array' => array(
                    'calling',
                    $match->getController(),
                    'with',
                    count($match->getArguments()),
                    'argument',
                    'at',
                    $match->getKbps(),
                    'kbps',
                    'and tagged by',
                    implode(', ', $match->getTags()),
                    'and custom tag',
                    $match->getAttribute('custom'),
                    'so this is very rich',
                    $match->getAttribute('rich'),
                    'total key in match',
                    count($match->getMatch()),
                ),
            );

            $match->setController('implode');
            $match->setArguments($args);
        });
        $this->env->run();

        $expected = 'calling foo with 0 argument at 99 kbps and tagged by foo, bar and custom tag foo so this is very rich tags total key in match 7';
        $actual = $this->env->getOutput();

        $this->assertSame($expected, $actual);
    }

    public function testRouteTotalInterception()
    {
        $this->env->route('GET /', 'foo');
        $this->env->listen('onRouteMatch', static function (RouteMatchEvent $match) {
            $match->setOutput('always me being returned');
        });
        $this->env->run();

        $expected = 'always me being returned';
        $actual = $this->env->getOutput();

        $this->assertSame($expected, $actual);
    }

    public function testLoadRoute()
    {
        $this->env->routeLoad(TEST_DATA . '/classes/Controller');
        $this->env->run();

        $this->assertSame('Welcome home', $this->env->getOutput());
    }
}
