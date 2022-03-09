<?php

namespace Ekok\App;

use Ekok\Utils\Arr;
use Ekok\Utils\Str;
use Ekok\Logger\Log;
use Ekok\Cache\Cache;
use Ekok\Container\Di;
use Ekok\Container\Box;
use Ekok\App\Event\ErrorEvent;
use Ekok\App\Event\RequestEvent;
use Ekok\App\Event\ResponseEvent;
use Ekok\EventDispatcher\Dispatcher;
use Ekok\Utils\File;

class Fw
{
    const VAR_GLOBALS = 'GET|POST|COOKIE|FILES|SERVER|ENV';
    const ROUTE_VERBS = 'GET|POST|PUT|DELETE|HEAD|OPTIONS';
    const ROUTE_PARAMS = '/(?:\/?@(\w+)(?:(?::([^\/?]+)|(\*)))?(\?)?)/';
    const ROUTE_PATTERN = '/^\s*([\w|]+)(?:\s*@([^\s]+))?(?:\s*(\/[^\s]*))?(?:\s*\[([\w|,=]+)\])?\s*$/';

    const EVENT_REQUEST = 'fw.request';
    const EVENT_RESPONSE = 'fw.response';
    const EVENT_ERROR = 'fw.error';

    const HTTP_100 = 'Continue';
    const HTTP_101 = 'Switching Protocols';
    const HTTP_103 = 'Early Hints';
    const HTTP_200 = 'OK';
    const HTTP_201 = 'Created';
    const HTTP_202 = 'Accepted';
    const HTTP_203 = 'Non-Authoritative Information';
    const HTTP_204 = 'No Content';
    const HTTP_205 = 'Reset Content';
    const HTTP_206 = 'Partial Content';
    const HTTP_300 = 'Multiple Choices';
    const HTTP_301 = 'Moved Permanently';
    const HTTP_302 = 'Found';
    const HTTP_303 = 'See Other';
    const HTTP_304 = 'Not Modified';
    const HTTP_307 = 'Temporary Redirect';
    const HTTP_308 = 'Permanent Redirect';
    const HTTP_400 = 'Bad Request';
    const HTTP_401 = 'Unauthorized';
    const HTTP_402 = 'Payment Required';
    const HTTP_403 = 'Forbidden';
    const HTTP_404 = 'Not Found';
    const HTTP_405 = 'Method Not Allowed';
    const HTTP_406 = 'Not Acceptable';
    const HTTP_407 = 'Proxy Authentication Required';
    const HTTP_408 = 'Request Timeout';
    const HTTP_409 = 'Conflict';
    const HTTP_410 = 'Gone';
    const HTTP_411 = 'Length Required';
    const HTTP_412 = 'Precondition Failed';
    const HTTP_413 = 'Payload Too Large';
    const HTTP_414 = 'URI Too Long';
    const HTTP_415 = 'Unsupported Media Type';
    const HTTP_416 = 'Range Not Satisfiable';
    const HTTP_417 = 'Expectation Failed';
    const HTTP_418 = 'I\'m a teapot';
    const HTTP_422 = 'Unprocessable Entity';
    const HTTP_425 = 'Too Early';
    const HTTP_426 = 'Upgrade Required';
    const HTTP_428 = 'Precondition Required';
    const HTTP_429 = 'Too Many Requests';
    const HTTP_431 = 'Request Header Fields Too Large';
    const HTTP_451 = 'Unavailable For Legal Reasons';
    const HTTP_500 = 'Internal Server Error';
    const HTTP_501 = 'Not Implemented';
    const HTTP_502 = 'Bad Gateway';
    const HTTP_503 = 'Service Unavailable';
    const HTTP_504 = 'Gateway Timeout';
    const HTTP_505 = 'HTTP Version Not Supported';
    const HTTP_506 = 'Variant Also Negotiates';
    const HTTP_507 = 'Insufficient Storage';
    const HTTP_508 = 'Loop Detected';
    const HTTP_510 = 'Not Extended';
    const HTTP_511 = 'Network Authentication Required';

    private $routes = array();
    private $aliases = array();

    public function __construct(private Di $di, private Box $box)
    {
        $this->di->setAlias('di');
        $this->di->inject($this, array('alias' => 'fw'));
        $this->di->inject($this->box, array('alias' => 'box'));
        $this->initialize();
    }

    public static function create(array $data = null, array $rules = null)
    {
        return new self(
            new Di(
                array_replace_recursive(
                    array(
                        Log::class => array('shared' => true, 'inherit' => false, 'alias' => 'log'),
                        Cache::class => array('shared' => true, 'inherit' => false, 'alias' => 'cache'),
                        Dispatcher::class => array('shared' => true, 'inherit' => false, 'alias' => 'dispatcher'),
                    ),
                    $rules ?? array(),
                ),
            ),
            new Box($data),
        );
    }

    public static function statusText(int $code, bool &$exists = null): string
    {
        $exists = defined($httpCode = 'self::HTTP_' . $code);
        $text = $exists ? constant($httpCode) : sprintf('Unsupported HTTP code: %s', $code);

        return $text;
    }

    public static function gmDate(\DateTime|string|int $time = null, int &$diff = null): string
    {
        $ts = match(true) {
            $time instanceof \DateTime => $time->getTimestamp(),
            is_string($time) => strtotime($time),
            $time < 0 => time() + $time,
            default => $time ?? time(),
        };
        $diff = $ts - time();

        return gmdate('D, d M Y H:i:s', $ts) . ' GMT';
    }

    public function getContainer(): Di
    {
        return $this->di;
    }

    public function getBox(): Box
    {
        return $this->box;
    }

    public function getLog(): Log
    {
        return $this->di->make(Log::class);
    }

    public function getCache(): Cache
    {
        return $this->di->make(Cache::class);
    }

    public function getDispatcher(): Dispatcher
    {
        return $this->di->make(Dispatcher::class);
    }

    public function load(string ...$files): static
    {
        array_walk($files, function (string $file) {
            $data = File::load($file) ?? array();

            array_walk($data, function ($value, $key) {
                if ($value instanceof \Closure) {
                    $this->di->call($value);
                } elseif (
                    is_string($key)
                    && is_array($value)
                    && (
                        ($expr = $this->di->isCallExpression($key))
                        || '@' === $key[0]
                    )
                ) {
                    $call = $expr ? $key : static::class . $key;
                    $norm = rtrim(strstr($call . '#', '#', true));

                    $this->di->callArguments($norm, $value);
                } else {
                    $this->box->set($key, $value);
                }
            });
        });

        return $this;
    }

    public function isDev(): bool
    {
        return $this->box['DEV'] ?? false;
    }

    public function setDev(bool $dev): static
    {
        $this->box['DEV'] = $dev;

        return $this;
    }

    public function isBuiltin(): bool
    {
        return $this->box['BUILTIN'] ?? ($this->box['BUILTIN'] = 'cli-server' === PHP_SAPI);
    }

    public function setBuiltin(bool $builtin): static
    {
        $this->box['BUILTIN'] = $builtin;

        return $this;
    }

    public function isCli(): bool
    {
        return $this->box['CLI'] ?? ($this->box['CLI'] = 'cli' === PHP_SAPI);
    }

    public function run(): static
    {
        try {
            $this->runInternal();
        } catch (\Throwable $error) {
            $this->error($error);
        }

        return $this;
    }

    public function error(\Throwable|int $code = 500, string $message = null, array $headers = null, array $payload = null): static
    {
        $error_ = null;
        $code_ = $code;
        $headers_ = $headers;
        $message_ = $message;
        $payload_ = $payload;

        if ($code instanceof \Throwable) {
            $code_ = 500;
            $error_ = $code;
            $message_ = $message ?? ($code->getMessage() ?: null);
        }

        if ($code instanceof HttpException) {
            $code_ = $code->statusCode;
            $headers_ = $code->headers;
            $payload_ = $code->payload;
        }

        $event = new ErrorEvent($code_, $message_, $headers_, $payload_, $error_);

        try {
            $this->getDispatcher()->dispatch($event, null, true);
        } catch (\Throwable $error) {
            $event = new ErrorEvent(500, $error->getMessage() ?: null, error: $error);
        }

        if (null === $event->getMessage()) {
            $event->setMessage(sprintf('[%s - %s] %s %s', $event->getCode(), $event->getText(), $this->getVerb(), $this->getPath()));
        }

        $this->getLog()->log(
            Log::LEVEL_INFO,
            $event->getMessage(),
            Arr::formatTrace(
                $event->getPayload() ?? $event->getError() ?? array()
            ),
        );

        $this->status($event->getCode(), false);
        $this->send($event->getOutput() ?? $this->errorBuild($event), $event->getHeaders(), $event->getMime());

        return $this;
    }

    public function errorTemplate(array $replace = null, string $name = null, string $template = null): static|string
    {
        $text = $template ?? $this->box['ERROR_' . $name] ?? ($this->isCli() ? $this->box['ERROR_CLI'] : $this->box['ERROR_HTML']);

        if ($replace) {
            return strtr($text, Arr::reduce(
                $replace,
                static fn (array $replace, $value, $key) => $replace + array('{' . $key . '}' => $value),
                array(),
            ));
        }

        if ($template && $name) {
            $this->box['ERROR_' . $name] = $template;
        }

        return $this;
    }

    public function alias(string $alias, array $args = null): string
    {
        $path = $this->aliases[$alias] ?? ('/' . ltrim($alias, '/'));
        $params = $args ?? array();

        if (false !== strpos($path, '@')) {
            $path = preg_replace_callback(
                self::ROUTE_PARAMS,
                static function ($match) use ($alias, &$params) {
                    $param = $params[$match[1]] ?? null;

                    if (!$param && !$match[4]) {
                        throw new \LogicException(sprintf('Route param required: %s@%s', $match[1], $alias));
                    }

                    if ($param) {
                        unset($params[$match[1]]);
                    }

                    return $param ? '/' . urldecode($param) : null;
                },
                $path,
                flags: PREG_UNMATCHED_AS_NULL,
            );
        }

        if ($params) {
            $path .= '?' . http_build_query($params);
        }

        return $path;
    }

    public function url(string $path, array $args = null, bool $absolute = false, bool $entry = true): string
    {
        return (
            ($absolute ? $this->getBaseUrl() : $this->getBasePath()) .
            ($entry && ($front = $this->getEntry()) ? '/' . $front : null) .
            $this->alias($path, $args)
        );
    }

    public function baseurl(string $path, array $args = null, bool $absolute = false): string
    {
        return $this->url($path, $args, $absolute, false);
    }

    public function uri(bool $absolute = true): string
    {
        return $this->url($this->getPath(), $this->box['GET'] ?? array(), $absolute);
    }

    public function getMatch(string $key = null, array|string|callable|null $default = null): array|string|callable|null
    {
        return $key ? ($this->box['MATCH'][$key] ?? $default) : ($this->box['MATCH'] ?? null);
    }

    public function getAliases(): array
    {
        return $this->aliases;
    }

    public function routeAll(array $routes): static
    {
        array_walk($routes, function ($handler, $route) {
            $this->route($route, $handler);
        });

        return $this;
    }

    public function route(string $route, callable|string|null $handler = null): static
    {
        $found = preg_match(self::ROUTE_PATTERN, $route, $matches, PREG_UNMATCHED_AS_NULL);

        if (!$found) {
            throw new \LogicException(sprintf('Invalid route: "%s"', $route));
        }

        $pattern = $matches[3] ?? $this->aliases[$matches[2]] ?? null;

        if (!$pattern) {
            throw new \LogicException(
                $matches[2] ? sprintf('Route not exists: %s', $matches[2]) : sprintf('No path defined in route: "%s"', $route),
            );
        }

        $set = array('handler' => $handler, 'alias' => $matches[2]) + $this->routeSet($matches[4]);

        if ($set['alias']) {
            $this->aliases[$set['alias']] = $pattern;
        }

        foreach (explode('|', strtoupper($matches[1])) as $verb) {
            $this->routes[$pattern][$verb] = $set;
        }

        return $this;
    }

    public function routeMatch(string $path = null, string $method = null): array|null
    {
        $path_ = $path ?? $this->getPath();
        $method_ = $method ?? $this->getVerb();

        $args = null;
        $found = $this->routes[$path_] ?? $this->routeFind($path_, $args);
        $handler = $found[$method_] ?? $found[strtoupper($method_)] ?? null;

        return $handler ? $handler + compact('args') : null;
    }

    public function getContentType(): string
    {
        return $this->box['CONTENTTYPE'] ?? (
            $this->box['CONTENTTYPE'] = $this->box['SERVER']['CONTENT_TYPE'] ?? ''
        );
    }

    public function isContentType(string $mime): bool
    {
        return preg_match('/^' . preg_quote($mime, '/') . '/i', $this->getContentType());
    }

    public function isMultipart(): bool
    {
        return $this->isContentType('multipart/form-data');
    }

    public function isJson(): bool
    {
        return $this->isContentType('json');
    }

    public function isRaw(): bool
    {
        return $this->box['RAW'] ?? false;
    }

    public function setRaw(bool $raw): static
    {
        $this->box['RAW'] = $raw;

        return $this;
    }

    public function getJson(): array
    {
        return json_decode($this->getBody() ?? '[]', true) ?: array();
    }

    public function getBody(): string|null
    {
        return $this->box['BODY'] ?? ($this->box['BODY'] = $this->isRaw() ? null : file_get_contents('php://input'));
    }

    public function setBody(string $body): static
    {
        $this->box['BODY'] = $body;

        return $this;
    }

    public function wantsJson(): bool
    {
        return $this->accept('json');
    }

    public function accept(string $mime): bool
    {
        return preg_match('/\b' . preg_quote($mime, '/') . '\b/i', $this->headers('accept') ?? '*/*');
    }

    public function acceptBest(): string
    {
        return Arr::fromHttpAccept($this->headers('accept') ?? '')[0] ?? '*/*';
    }

    public function headers(string $key = null): array|string|null
    {
        $srv = $this->box['SERVER'] ?? array();

        if ($key) {
            $key_ = strtoupper(str_replace('-', '_', $key));

            return $srv[$key_] ?? $srv['HTTP_' . $key_] ?? null;
        }

        return Arr::reduce(
            $srv,
            static fn ($headers, $header, $key) => $headers + (
                str_starts_with($key, 'HTTP_') ?
                    array(ucwords(strtolower(str_replace('_', '-', substr($key, 5))), '-') => $header) :
                    array()
            ),
            array(
                'Content-Type' => $srv['CONTENT_TYPE'] ?? null,
                'Content-Length' => $srv['CONTENT_LENGTH'] ?? null,
            ),
        );
    }

    public function getBasePath(): string
    {
        return $this->box['BASEPATH'] ?? ($this->box['BASEPATH'] = $this->isBuiltin() || $this->isCli() ? '' : Str::fixslashes(dirname($this->box['SERVER']['SCRIPT_NAME'] ?? '')));
    }

    public function setBasePath(string $basePath): static
    {
        $this->box['BASEPATH'] = ($basePath && '/' !== $basePath[0] ? '/' : '') . $basePath;

        return $this;
    }

    public function getBaseUrl(): string
    {
        return $this->box['BASEURL'] ?? ($this->box['BASEURL'] = (
                $this->getScheme() .
                '://' .
                $this->getHost() .
                (in_array($port = $this->getPort(), array(0, 80, 443)) ? null : ':' . $port) .
                $this->getBasePath()
            )
        );
    }

    public function setBaseUrl(string $baseUrl): static
    {
        $this->box['BASEURL'] = $baseUrl;

        return $this;
    }

    public function getPath(): string
    {
        if (null === ($this->box['PATH'] ?? null)) {
            $entry = $this->getEntry();
            $basePath = $this->getBasePath();
            $uri = rawurldecode(strstr(($this->box['SERVER']['REQUEST_URI'] ?? '') . '?', '?', true));
            $base = $entry ? rtrim($basePath . '/' . $entry, '/') : $basePath;

            $this->box['PATH'] = urldecode('/' . ltrim('' === $base ? $uri : preg_replace("#^{$base}#", '', $uri, 1), '/'));
        }

        return $this->box['PATH'];
    }

    public function setPath(string $path): static
    {
        $this->box['PATH'] = ('/' !== ($path[0] ?? '') ? '/' : '') . $path;

        return $this;
    }

    public function isSecure(): bool
    {
        return $this->box['SECURE'] ?? ($this->box['SECURE'] = !!($this->box['SERVER']['HTTPS'] ?? null));
    }

    public function setSecure(bool $secure): static
    {
        $this->box['SECURE'] = $secure;

        return $this;
    }

    public function getScheme(): string
    {
        return $this->box['SCHEME'] ?? ($this->box['SCHEME'] = $this->isSecure() ? 'https' : 'http');
    }

    public function setScheme(string $scheme): static
    {
        $this->box['SCHEME'] = $scheme;

        return $this;
    }

    public function getHost(): string
    {
        return $this->box['HOST'] ?? ($this->box['HOST'] = strstr(($this->box['SERVER']['HTTP_HOST'] ?? 'localhost') . ':', ':', true));
    }

    public function setHost(string $host): static
    {
        $this->box['HOST'] = $host;

        return $this;
    }

    public function getPort(): int
    {
        return $this->box['PORT'] ?? ($this->box['PORT'] = intval($this->box['SERVER']['SERVER_PORT'] ?? 80));
    }

    public function setPort(string|int $port): static
    {
        $this->box['PORT'] = intval($port);

        return $this;
    }

    public function getEntry(): string|null
    {
        return $this->box['ENTRY'] ?? ($this->box['ENTRY'] = $this->isBuiltin() || $this->isCli() ? '' : basename($this->box['SERVER']['SCRIPT_NAME'] ?? ''));
    }

    public function setEntry(string|null $entry): static
    {
        $this->box['ENTRY'] = $entry;

        return $this;
    }

    public function getVerb(): string
    {
        return $this->box['VERB'] ?? (
            $this->box['VERB'] = $this->box['SERVER']['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $this->box['POST']['_method'] ?? $this->box['SERVER']['REQUEST_METHOD'] ?? 'GET'
        );
    }

    public function setVerb(string $verb): static
    {
        $this->box['VERB'] = $verb;

        return $this;
    }

    public function getProtocol(): string
    {
        return $this->box['PROTOCOL'] ?? ($this->box['PROTOCOL'] = $this->box['SERVER']['SERVER_PROTOCOL'] ?? 'HTTP/1.1');
    }

    public function setProtocol(string $protocol): static
    {
        $this->box['PROTOCOL'] = $protocol;

        return $this;
    }

    public function getMime(): string|null
    {
        return $this->box['MIME'] ?? null;
    }

    public function setMime(string $mime): static
    {
        $this->box['MIME'] = $mime;

        return $this;
    }

    public function getMimeFile(string $file): string
    {
        $ext = ltrim(strrchr('.' . $file, '.'), '.');
        $list = $this->box['MIME_LIST'][$ext] ?? $this->box['MIME_LIST'][strtolower($ext)] ?? 'application/octet-stream';

        return is_array($list) ? reset($list) : $list;
    }

    public function getMimeList(): array
    {
        return $this->box['MIME_LIST'] ?? array();
    }

    public function setMimeList(array $mimeList, bool $replace = true): static
    {
        if ($replace) {
            $this->box['MIME_LIST'] = array();
        }

        array_walk($mimeList, function (string|array $mime, string $ext) {
            $this->box['MIME_LIST'][strtolower($ext)] = $mime;
        });

        return $this;
    }

    public function getCharset(): string|null
    {
        return $this->box['CHARSET'] ?? ($this->box['CHARSET'] = 'UTF-8');
    }

    public function setCharset(string $charset): static
    {
        $this->box['CHARSET'] = $charset;

        return $this;
    }

    public function hasHeader(string $name, array &$found = null): bool
    {
        $found = array();

        if (isset($this->box['HEADERS'][$name])) {
            $found = array($name);
        } else {
            $found = preg_grep(
                '/^' . preg_quote($name, '/') . '$/i',
                array_keys($this->box['HEADERS'] ?? array()),
            ) ?: array();
        }

        return !!$found;
    }

    public function getHeader(string $key): array
    {
        return Arr::reduce(
            $this->hasHeader($key, $found) ? $found : array(),
            fn(array $headers, string $header) => array_merge($headers, $this->box['HEADERS'][$header]),
            array(),
        );
    }

    public function getHeaders(): array
    {
        return $this->box['HEADERS'] ?? array();
    }

    public function addHeader(string $name, $value, bool $replace = true): static
    {
        if ($replace) {
            $this->removeHeaders($name);

            $this->box['HEADERS'][$name] = (array) $value;
        } else {
            if (is_array($value)) {
                array_walk($value, function ($value) use ($name) {
                    $this->box['HEADERS'][$name][] = $value;
                });
            } else {
                $this->box['HEADERS'][$name][] = $value;
            }
        }

        return $this;
    }

    public function setHeaders(array $headers, bool $replace = false): static
    {
        if ($replace) {
            $this->box['HEADERS'] = array();
        }

        array_walk($headers, function ($value, $name) {
            $this->addHeader($name, $value, false);
        });

        return $this;
    }

    public function removeHeaders(string ...$keys): static
    {
        array_walk($keys, function ($key) {
            $this->hasHeader($key, $found);

            array_walk($found, function ($key) {
                unset($this->box['HEADERS'][$key]);
            });
        });

        return $this;
    }

    public function getCookieJar(): array
    {
        return $this->box['COOKIE_JAR'] ?? array(
            'expires' => null,
            'path' => $this->getBasePath(),
            'domain' => $this->getHost(),
            'secure' => $this->isSecure(),
            'httponly' => true,
            'raw' => false,
            'samesite' => 'Lax',
        );
    }

    public function setCookieJar(array $jar): static
    {
        $this->box['COOKIE_JAR'] = array_replace($this->getCookieJar(), $jar);

        return $this;
    }

    public function cookie(string $name = null, ...$set)
    {
        if ($name) {
            if ($set) {
                $this->setCookie($name, $set[0]);
            }

            return $this->box['COOKIE'][$name] ?? $set[0] ?? null;
        }

        return $this->box['COOKIE'];
    }

    public function setCookie(
        string $name,
        string $value = null,
        \DateTime|string|int $expires = null,
        string $path = null,
        string $domain = null,
        bool $secure = null,
        bool $httponly = null,
        string $samesite = null,
        bool $raw = null,
    ): static {
        $jar = $this->getCookieJar();
        $cookie = $name . '=';

        if ($value) {
            $cookie .= ($raw ?? $jar['raw']) ? urlencode($value) : $value;

            $this->box['COOKIE'][$name] = $value;
        } else {
            $exp = -2592000;
            $cookie .= 'deleted';

            unset($this->box['COOKIE'][$name]);
        }

        if ($set = $exp ?? $expires ?? $jar['expires']) {
            $cookie .= '; Expires=' . self::gmDate($set, $max) . '; Max-Age=' . $max;
        }

        if ($set = $domain ?? $jar['domain']) {
            $cookie .= '; Domain=' . $set;
        }

        if ($set = $path ?? $jar['path']) {
            $cookie .= '; Path=' . $set;
        }

        if ($set = $secure ?? $jar['secure']) {
            $cookie .= '; Secure';
        }

        if ($set = $httponly ?? $jar['httponly']) {
            $cookie .= '; HttpOnly';
        }

        if ($set = $samesite ?? $jar['samesite']) {
            if (!in_array($low = strtolower($set), array('lax', 'strict', 'none'))) {
                throw new \LogicException(sprintf('Invalid samesite value: %s', $set));
            }

            if ('none' === $low && false === strpos($cookie, '; Secure')) {
                throw new \LogicException('Samesite None require a secure context');
            }

            $cookie .= '; SameSite=' . ucfirst($low);
        }

        return $this->addHeader('Set-Cookie', $cookie, false);
    }

    public function removeCookie(
        string $name,
        string $path = null,
        string $domain = null,
        bool $secure = null,
        bool $httponly = null,
        string $samesite = null,
    ): static {
        return $this->setCookie($name, null, null, $path, $domain, $secure, $httponly, $samesite);
    }

    public function session(string $name = null, ...$set)
    {
        if ($name) {
            if ($set) {
                $this->box['SESSION'][$name] = $set[0];
            }

            return $this->box['SESSION'][$name] ?? null;
        }

        return $this->box['SESSION'];
    }

    public function flash(string $name)
    {
        return $this->box->cut('SESSION.' . $name);
    }

    public function getOutput(): string|null
    {
        return $this->box['OUTPUT'] ?? null;
    }

    public function setOutput($value, string $mime = null): static
    {
        list($setOutput, $setMime) = match(true) {
            is_array($value) || $value instanceof \JsonSerializable => array(json_encode($value), 'application/json'),
            is_scalar($value) || $value instanceof \Stringable => array((string) $value, 'text/html'),
            default => array(null, null),
        };

        if ($mime || (!$this->getMime() && $setMime)) {
            $this->setMime($mime ?? $setMime);
        }

        $this->box['OUTPUT'] = $setOutput;

        return $this;
    }

    public function isQuiet(): bool
    {
        return $this->box['QUIET'] ?? false;
    }

    public function setQuiet(bool $quiet): static
    {
        $this->box['QUIET'] = $quiet;

        return $this;
    }

    public function isBuffering(): bool
    {
        return $this->box['BUFFERING'] ?? true;
    }

    public function setBuffering(bool $buffering): static
    {
        $this->box['BUFFERING'] = $buffering;

        return $this;
    }

    public function getBufferingLevel(): int|null
    {
        return $this->box['BUFFERING_LEVEL'] ?? null;
    }

    public function stopBuffering(): array
    {
        $buffers = array();

        if ($level = $this->getBufferingLevel()) {
            while (ob_get_level() > $level) {
                $buffers[] = ob_get_clean();
            }

            $this->box['BUFFERING_LEVEL'] = null;
        }

        return $buffers;
    }

    public function code(): int|null
    {
        return $this->box['CODE'] ?? null;
    }

    public function text(): string|null
    {
        return $this->box['TEXT'] ?? null;
    }

    public function status(int $code, bool $throw = true): static
    {
        $text = self::statusText($code, $exists);

        if (!$exists && $throw) {
            throw new \LogicException($text);
        }

        $this->box['TEXT'] = $text;
        $this->box['CODE'] = $code;

        return $this;
    }

    public function sent(): bool
    {
        return $this->box['SENT'] ?? ($this->box['SENT'] = headers_sent());
    }

    public function send($value = null, array $headers = null, int $status = null, string|null $mime = null, int $kbps = null): static
    {
        if ($this->sent()) {
            return $this;
        }

        if ($status || !$this->code()) {
            $this->status($status ?? 200);
        }

        $this->setHeaders($headers ?? array());
        $this->setOutput($value, $mime);

        $code = $this->code();
        $output = $this->getOutput();
        $shout = $output && !$this->isQuiet();

        if (!$this->hasHeader('Content-Type') && $this->getMime()) {
            $this->addHeader('Content-Type', $this->getMime() . ';charset=' . $this->getCharset());
        }

        if (!$this->hasHeader('Content-Length') && $output) {
            $this->addHeader('Content-Length', strlen($output));
        }

        foreach ($this->box['HEADERS'] ?? array() as $name => $headers) {
            $set = ucwords($name, '-') . ': ';
            $replace = empty($headers[1]);

            foreach ($headers as $header) {
                header($set . $header, $replace, $code);
            }
        }

        header($this->getProtocol() . ' ' . $code . ' ' . $this->text(), true, $code);

        $this->stopBuffering();

        if (is_callable($value)) {
            $this->di->call($value);
        } elseif ($shout) {
            $this->shoutText($output, $kbps);
        }

        $this->box['SENT'] = true;

        return $this;
    }

    public function sendFile(string $file, array $headers = null, string|bool $download = null, bool $range = false, string|null $mime = null, int $kbps = null): static
    {
        if ($this->sent()) {
            return $this;
        }

        $lastModified = filemtime($file);
        $modifiedSince = $this->headers('if_modified_since');

        $this->addHeader('Last-Modified', self::gmDate($lastModified));

        if ($range) {
            $this->addHeader('Accept-Ranges', 'bytes');
        }

        if ($modifiedSince && strtotime($modifiedSince) === $lastModified) {
            return $this->send(status: 304); // not modified
        }

        $size = filesize($file);
        $status = 200;
        $offset = 0;
        $length = $size;
        $header = $range ? $this->headers('range') : null;

        if ($header && !preg_match('/^bytes=(?:(\d+)-(\d+)?)|(?:\-(\d+))/', $header, $parts, PREG_UNMATCHED_AS_NULL)) {
            return $this->send(status: 416, headers: array('Content-Range' => sprintf('bytes */%u', $size)));
        }

        if ($parts ?? null) {
            $status = 206;
            $offset = $parts[3] ? $size - $parts[3] : intval($parts[1]);
            $length = ($parts[2] ?? $size - 1) + 1 - $offset;

            $this->addHeader('Content-Range', sprintf('bytes %u-%u/%u', $offset, $length, $size));
        }

        if ($download) {
            $header = is_string($download) ? 'attachment; filename="' . $download . '"' : 'attachment';

            $this->addHeader('Content-Disposition', $header);
        }

        $this->setHeaders(array(
            'Content-Length' => sprintf('%u', $length),
            'Cache-Control' => 'public, max-age=604800',
            'Expires' => gmdate("D, d M Y H:i:s", time() + 604800) . " GMT",
        ));
        $this->setHeaders($headers ?? array());
        $this->send(status: $status, mime: $mime ?? $this->getMimeFile($file));
        $this->shoutFile($file, $kbps, $offset, $length);

        return $this;
    }

    public function shoutFile(string $file, int $kbps = null, int $seek = null, int $length = null): void
    {
        $fp = fopen($file, 'rb');
        $size = $length ?? filesize($file);

        if ($seek > 0) {
            fseek($fp, $seek);
        }

        if (0 >= $kbps) {
            echo fread($fp, $size);
            flush();
            fclose($fp);

            return;
        }

        $now = microtime(true);
        $ctr = 0;
        $pos = 0;

        while (CONNECTION_NORMAL === connection_status() && $pos < $size) {
            $part = fread($fp, 1024);
            $pos += strlen($part);

            echo $part;
            flush();

            $elapsed = microtime(true) - $now;
            $sleep = ++$ctr / $kbps > $elapsed;

            if ($sleep) {
                usleep(round(1e6 * ($ctr / $kbps - $elapsed)));
            }
        }

        fclose($fp);
    }

    public function shoutText(string|null $text, int $kbps = null): void
    {
        if (0 >= $kbps) {
            echo $text;

            return;
        }

        $ctr = 0;
        $pos = 0;
        $now = microtime(true);
        $size = strlen($text);

        while (CONNECTION_NORMAL === connection_status() && $pos < $size) {
            $part = substr($text, $pos, 1024);
            $pos += strlen($part);

            echo $part;
            flush();

            $elapsed = microtime(true) - $now;
            $sleep = ++$ctr / $kbps > $elapsed;

            if ($sleep) {
                usleep(round(1e6 * ($ctr / $kbps - $elapsed)));
            }
        }
    }

    public function chain(callable|string $cb): static
    {
        $this->di->call($cb);

        return $this;
    }

    public function renderSetup(string|array $directories, string|array $extensions = null): static
    {
        $this->box['RENDER'] = array(
            'directories' => array_map(
                static fn(string $dir) => rtrim(Str::fixslashes($dir), '/') . '/',
                Arr::ensure($directories),
            ),
            'extensions' => array_map(
                static fn(string $ext) => '.' . trim($ext, '.'),
                Arr::ensure($extensions),
            ),
        );

        return $this;
    }

    public function render(string $file, array $data = null, bool $safe = false, $defaults = null): mixed
    {
        $found = (
            file_exists($found = $file)
            || (
                $found = Arr::first(
                    $this->box['RENDER']['directories'] ?? array(),
                    fn (string $dir) => (
                        file_exists($found = $dir . $file)
                        || ($found = Arr::first(
                            $this->box['RENDER']['extensions'] ?? array('.php'),
                            static fn(string $ext) => (
                                file_exists($found = $dir . $file . $ext)
                                || file_exists($found = $dir . strtr($file, '.', '/') . $ext) ? $found : null
                            )
                        )) ? $found : null
                    )
                )
            )
        ) ? $found : null;

        if (!$found && $safe) {
            return $defaults;
        }

        if (!$found) {
            throw new \LogicException(sprintf('File not found: "%s"', $file));
        }

        return (static function () {
            try {
                ob_start();
                extract(func_get_arg(0));
                require func_get_arg(1);

                return ob_get_clean();
            } catch (\Throwable $error) {
                while (ob_get_level() > func_get_arg(2)) {
                    ob_end_clean();
                }

                throw new \LogicException(sprintf(
                    'Error in template: %s (%s)',
                    func_get_arg(3),
                    $error->getMessage(),
                ), 0, $error);
            }
        })($data ?? array(), $found, ob_get_level(), $file);
    }

    private function routeFind(string $path, array &$args = null): array|null
    {
        return Arr::first(
            $this->routes,
            function (array $routes, string $pattern) use ($path, &$args) {
                return $this->routeMatchPattern($pattern, $path, $args) ? $routes : null;
            },
        );
    }

    private function routeMatchPattern(string $pattern, string $path, array &$args = null): bool
    {
        $match = !!preg_match($this->routeRegExp($pattern), $path, $matches);
        $args = array_filter(array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));

        return $match;
    }

    private function routeRegExp(string $pattern): string
    {
        return (
            '#^' .
            preg_replace_callback(
                self::ROUTE_PARAMS,
                static fn (array $match) => (
                    '/' .
                    $match[4] .
                    '(?<' .
                        $match[1] .
                        '>' .
                        ($match[3] ? '.*' : ($match[2] ?? '[\w-]+')) .
                    ')'
                ),
                $pattern,
                flags: PREG_UNMATCHED_AS_NULL,
            ) .
            '/?$#'
        );
    }

    private function routeSet(string|null $str): array
    {
        return Arr::reduce(
            array_filter(explode(',', $str), 'trim'),
            static function (array $set, string $line) {
                list($tag, $value) = array_map('trim', explode('=', $line . '='));

                if ('' === $value) {
                    $set['tags'][] = $tag;
                } else {
                    $set[$tag] = $value;
                }

                return $set;
            },
            array(),
        );
    }

    private function runInternal(): void
    {
        $this->getDispatcher()->dispatch($event = new RequestEvent());

        if ($event->isPropagationStopped()) {
            $this->runResult(null, $event->getOutput(), $event->getSpeed());

            return;
        }

        if (!$this->routes) {
            throw new \LogicException('No route defined');
        }

        $match = $this->routeMatch();

        if (!$match) {
            throw new HttpException(404);
        }

        $this->box['MATCH'] = $match;

        list($result, $output) = $this->runHandle($match['handler'], $match['args']);

        $this->runResult($result, $output, intval($match['kbps'] ?? 0));
    }

    private function runHandle(string|callable $handler, array|null $args): array
    {
        if ($this->isBuffering()) {
            $this->box['BUFFERING_LEVEL'] = ob_get_level();

            ob_start();
        }

        $result = $this->di->callArguments($handler, $args);
        $output = $this->stopBuffering();

        return array($result, $output[0] ?? null);
    }

    private function runResult($result, string $output, int $speed = null): void
    {
        $this->getDispatcher()->dispatch($event = new ResponseEvent($result, $output));

        if (is_callable($response = $event->getResult())) {
            $this->di->call($response);
        } elseif (!$response) {
            $this->send($event->getOutput(), kbps: $event->getSpeed() ?? $speed);
        } elseif (is_scalar($response) || is_array($response) || $response instanceof \Stringable) {
            $this->send($response, kbps: $event->getSpeed() ?? $speed);
        }
    }

    private function errorBuild(ErrorEvent $error): string|array
    {
        $dev = $this->isDev();
        $data = array(
            'code' => $error->getCode(),
            'text' => $error->getText(),
            'data' => $error->getPayload(),
            'message' => $error->getMessage(),
        );

        if ($dev) {
            $data['trace'] = $error->getTrace();
        }

        if ($this->wantsJson()) {
            return $data;
        }

        if ($this->isCli()) {
            $replace = $data;
            $replace['data'] = null;
            $replace['trace'] = $dev ? implode("\n", $replace['trace']) : null;

            return $this->errorTemplate($replace, 'cli');
        }

        $replace = $data;
        $replace['data'] = null;
        $replace['trace'] = $dev ? '<pre>' . implode("\n", $replace['trace']) . '</pre>' : null;

        return $this->errorTemplate($replace, 'html');
    }

    private function initialize(): void
    {
        $globals = explode('|', self::VAR_GLOBALS);

        array_walk($globals, function ($global) {
            if (!isset($this->box[$global])) {
                $this->box[$global] = $GLOBALS['_' . $global] ?? array();
            }
        });

        $this->box['ERROR_HTML'] =
        <<<'HTML'
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{code} - {text}</title>
  </head>
  <body>
    <div>
      <h1>{code} - {text}</h1>
      <p>{message}</p>
      {trace}
    </div>
  </body>
</html>
HTML;
        $this->box['ERROR_CLI'] =
        <<<'TEXT'
{code} - {text}
{message}
{trace}

TEXT;
        $this->box->beforeRef(function ($key, array &$data) {
            if (is_string($key) && 0 === strpos($key, 'SESSION')) {
                (PHP_SESSION_ACTIVE === session_status() || $this->sent()) || session_start();

                if (!isset($data['SESSION'])) {
                    $data['SESSION'] = &$GLOBALS['_SESSION'];
                }
            }
        });
        $this->box->beforeUnref(function ($key) {
            if (is_string($key) && 0 === strpos($key, 'SESSION')) {
                session_unset();
                session_destroy();
            }
        });
    }
}
