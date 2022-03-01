<?php

namespace Ekok\App;

use Ekok\Utils\Arr;
use Ekok\Utils\Str;
use Ekok\Utils\Val;
use Ekok\Logger\Log;
use Ekok\Cache\Cache;
use Ekok\Container\Di;
use Ekok\Container\Box;
use Ekok\App\Event\ErrorEvent;
use Ekok\App\Event\RequestEvent;
use Ekok\App\Event\ResponseEvent;
use Ekok\EventDispatcher\Dispatcher;

class Fw
{
    const VAR_GLOBALS = 'GET|POST|COOKIE|FILES|SESSION|SERVER|ENV';
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

    public function __construct(
        public Di $di,
        public Box $box,
        public Log $log,
        public Cache $cache,
        public Dispatcher $dispatcher,
        private array|null $data = null,
        private array|null $env = null,
    ) {
        $this->initialize();
    }

    public static function create(array $data = null, array $rules = null, array $env = null)
    {
        $as = static fn($alias) => compact('alias') + array('shared' => true, 'inherit' => false);
        $di = new Di($rules);
        $self = new self($di, new Box(), new Log(), new Cache(), new Dispatcher($di), $data, $env);

        $di->inject($self, $as('fw'));
        $di->inject($self->box, $as('box'));
        $di->inject($self->log, $as('log'));
        $di->inject($self->cache, $as('cache'));
        $di->inject($self->dispatcher, $as('dispatcher'));

        return $self;
    }

    public static function httpAcceptParse(string $text, string $type = null, bool $sort = true): array
    {
        $accepts = array_map(
            static function (string $part) {
                $attrs = array_filter(explode(';', $part), 'trim');
                $content = array_shift($attrs);
                $tags = array_reduce($attrs, static function (array $tags, string $attr) {
                    list($key, $value) = array_map('trim', explode('=', $attr . '='));

                    return $tags + array(
                        $key => is_numeric($value) ? $value * 1 : $value,
                    );
                }, array());

                return compact('content') + $tags;
            },
            array_filter(explode(',', $text), 'trim'),
        );

        return $sort ? self::httpAcceptSort($accepts, $type) : $accepts;
    }

    public static function httpAcceptSort(array $accepts, string $type = null): array
    {
        $sorted = $accepts;

        usort($sorted, static function (array $a, array $b) use ($type) {
            return ($b['q'] ?? 1) <=> ($b['q'] ?? 1);
        });

        return $sorted;
    }

    public function &env(string $key)
    {
        if (0 === strpos($key, 'SESSION')) {
            (PHP_SESSION_ACTIVE === session_status() || $this->sent()) || session_start();

            $this->env['SESSION'] = &$_SESSION;
        }

        $var = &Val::ref($key, $this->env);

        return $var;
    }

    public function cookie(string $key)
    {
        return $this->env('COOKIE.' . $key);
    }

    public function session(string $key, ...$value)
    {
        $session = &$this->env('SESSION.' . $key);

        if ($value) {
            $session = $value[0];
        }

        return $session;
    }

    public function sessionEnd(): static
    {
        $session = &$this->env('SESSION');
        $session = array();

        session_unset();
        session_destroy();

        return $this;
    }

    public function flash(string $key = null)
    {
        $value = $this->session($key);

        if ($key) {
            unset($this->env['SESSION'][$key]);
        }

        return $value;
    }

    public function isDev(): bool
    {
        return $this->data['dev'] ?? false;
    }

    public function setDev(bool $dev): static
    {
        $this->data['dev'] = $dev;

        return $this;
    }

    public function isBuiltin(): bool
    {
        return $this->data['builtin'] ?? ($this->data['builtin'] = 'cli-server' === PHP_SAPI);
    }

    public function setBuiltin(bool $builtin): static
    {
        $this->data['builtin'] = $builtin;

        return $this;
    }

    public function isCli(): bool
    {
        return $this->data['cli'] ?? ($this->data['cli'] = 'cli' === PHP_SAPI);
    }

    public function run(): static
    {
        try {
            $this->handleRun();
        } catch (\Throwable $error) {
            $this->error($error);
        }

        return $this;
    }

    public function error(\Throwable|int $code = 500, string $message = null, array $headers = null, array $payload = null): static
    {
        $event = new ErrorEvent($code, $message, $headers, $payload);

        try {
            $this->dispatcher->dispatch($event, null, true);
        } catch (\Throwable $error) {
            $event = new ErrorEvent($error);
        }

        return (
            $this
                ->status($event->getCode(), false)
                ->send($event->getOutput() ?? $this->errorBuild($event), $event->getHeaders())
        );
    }

    public function errorTemplate(array $replace = null, string $name = null, string $template = null): static|string
    {
        $text = $template ?? $this->data['error_' . $name] ?? ($this->isCli() ? $this->data['error_cli'] : $this->data['error_html']);

        if ($replace) {
            return strtr($text, Arr::reduce(
                $replace,
                static fn (array $replace, $value, $key) => $replace + array('{' . $key . '}' => $value),
                array(),
            ));
        }

        if ($template && $name) {
            $this->data['error_' . $name] = $template;
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
        return $this->url($this->getPath(), $this->env['GET'] ?? array(), $absolute);
    }

    public function getMatch(): array|null
    {
        return $this->data['match'] ?? null;
    }

    public function getAliases(): array
    {
        return $this->aliases;
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
        return $this->data['contentType'] ?? (
            $this->data['contentType'] = $this->env['SERVER']['CONTENT_TYPE'] ?? ''
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

    public function wantsJson(): bool
    {
        return $this->accept('json');
    }

    public function accept(string $mime): bool
    {
        return preg_match('/\b' . preg_quote($mime, '/') . '\b/i', $this->env['SERVER']['HTTP_ACCEPT'] ?? '*/*');
    }

    public function acceptBest(): string
    {
        return self::httpAcceptParse($this->env['SERVER']['HTTP_ACCEPT'] ?? '')[0] ?? '*/*';
    }

    public function getBasePath(): string
    {
        return $this->data['basePath'] ?? ($this->data['basePath'] = $this->isBuiltin() || $this->isCli() ? '' : Str::fixslashes(dirname($this->env['SERVER']['SCRIPT_NAME'] ?? '')));
    }

    public function setBasePath(string $basePath): static
    {
        $this->data['basePath'] = ($basePath && '/' !== $basePath[0] ? '/' : '') . $basePath;

        return $this;
    }

    public function getBaseUrl(): string
    {
        return $this->data['baseUrl'] ?? ($this->data['baseUrl'] = (
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
        $this->data['baseUrl'] = $baseUrl;

        return $this;
    }

    public function getPath(): string
    {
        if (null === ($this->data['path'] ?? null)) {
            $entry = $this->getEntry();
            $basePath = $this->getBasePath();
            $uri = rawurldecode(strstr(($this->env['SERVER']['REQUEST_URI'] ?? '') . '?', '?', true));
            $base = $entry ? rtrim($basePath . '/' . $entry, '/') : $basePath;

            $this->data['path'] = urldecode('/' . ltrim('' === $base ? $uri : preg_replace("#^{$base}#", '', $uri, 1), '/'));
        }

        return $this->data['path'];
    }

    public function setPath(string $path): static
    {
        $this->data['path'] = ('/' !== ($path[0] ?? '') ? '/' : '') . $path;

        return $this;
    }

    public function getScheme(): string
    {
        return $this->data['scheme'] ?? ($this->data['scheme'] = ($this->env['SERVER']['HTTPS'] ?? '') ? 'https' : 'http');
    }

    public function setScheme(string $scheme): static
    {
        $this->data['scheme'] = $scheme;

        return $this;
    }

    public function getHost(): string
    {
        return $this->data['host'] ?? ($this->data['host'] = strstr(($this->env['SERVER']['HTTP_HOST'] ?? 'localhost') . ':', ':', true));
    }

    public function setHost(string $host): static
    {
        $this->data['host'] = $host;

        return $this;
    }

    public function getPort(): int
    {
        return $this->data['port'] ?? ($this->data['port'] = intval($this->env['SERVER']['SERVER_PORT'] ?? 80));
    }

    public function setPort(string|int $port): static
    {
        $this->data['port'] = intval($port);

        return $this;
    }

    public function getEntry(): string|null
    {
        return $this->data['entry'] ?? ($this->data['entry'] = $this->isBuiltin() || $this->isCli() ? '' : basename($this->env['SERVER']['SCRIPT_NAME'] ?? ''));
    }

    public function setEntry(string|null $entry): static
    {
        $this->data['entry'] = $entry;

        return $this;
    }

    public function getVerb(): string
    {
        return $this->data['verb'] ?? (
            $this->data['verb'] = $this->env['SERVER']['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $this->env['POST']['_method'] ?? $this->env['SERVER']['REQUEST_METHOD'] ?? 'GET'
        );
    }

    public function setVerb(string $verb): static
    {
        $this->data['verb'] = $verb;

        return $this;
    }

    public function getProtocol(): string
    {
        return $this->data['protocol'] ?? ($this->data['protocol'] = $this->env['SERVER']['SERVER_PROTOCOL'] ?? 'HTTP/1.1');
    }

    public function setProtocol(string $protocol): static
    {
        $this->data['protocol'] = $protocol;

        return $this;
    }

    public function getMime(): string|null
    {
        return $this->data['mime'] ?? null;
    }

    public function setMime(string $mime): static
    {
        $this->data['mime'] = $mime;

        return $this;
    }

    public function getCharset(): string|null
    {
        return $this->data['charset'] ?? ($this->data['charset'] = 'UTF-8');
    }

    public function setCharset(string $charset): static
    {
        $this->data['charset'] = $charset;

        return $this;
    }

    public function getHeaders(): array
    {
        return array_map(
            static fn(array $value) => array_reduce(
                $value,
                static fn (array $norm, array $value) => array_merge($norm, array($value[0])),
                array(),
            ),
            $this->data['headers'] ?? array(),
        );
    }

    public function setHeader(string $name, $value, bool $replace = null): static
    {
        if (is_array($value)) {
            array_walk($value, function ($value) use ($name, $replace) {
                $this->data['headers'][$name][] = array($value, $replace);
            });
        } else {
            $this->data['headers'][$name][] = array($value, $replace ?? true);
        }

        return $this;
    }

    public function setHeaders(array $headers): static
    {
        array_walk($headers, function ($value, $name) {
            $this->setHeader($name, $value);
        });

        return $this;
    }

    public function getOutput(): string|null
    {
        return $this->data['output'] ?? null;
    }

    public function setOutput($value): static
    {
        if (is_array($value) || $value instanceof \JsonSerializable) {
            $this->data['output'] = json_encode($value);
            $this->data['mime'] = $this->getMime() ?? 'application/json';
        } elseif (is_scalar($value) || $value instanceof \Stringable) {
            $this->data['output'] = (string) $value;
        }

        return $this;
    }

    public function isQuiet(): bool
    {
        return $this->data['quiet'] ?? false;
    }

    public function setQuiet(bool $quiet): static
    {
        $this->data['quiet'] = $quiet;

        return $this;
    }

    public function code(): int|null
    {
        return $this->data['code'] ?? null;
    }

    public function text(): string|null
    {
        return $this->data['text'] ?? null;
    }

    public function status(int $code, bool $throw = true): static
    {
        $exists = defined($httpCode = 'self::HTTP_' . $code);
        $text = $exists ? constant($httpCode) : sprintf('Unsupported HTTP code: %s', $code);

        if (!$exists && $throw) {
            throw new \LogicException($text);
        }

        $this->data['text'] = $text;
        $this->data['code'] = $code;

        return $this;
    }

    public function sent(): bool
    {
        return $this->data['sent'] ?? ($this->data['sent'] = headers_sent());
    }

    public function send($value, array $headers = null, int $code = null): static
    {
        if (!$this->sent()) {
            if ($code || !$this->code()) {
                $this->status($code ?? 200);
            }

            $this->setOutput($value);
            $this->setHeaders($headers ?? array());

            $statusCode = $this->code();
            $statusText = $this->text();
            $mime = $this->getMime() ?? 'text/html';
            $charset = $this->getCharset();
            $protocol = $this->getProtocol();

            foreach ($this->data['headers'] ?? array() as $name => $value) {
                $set = ucwords($name, '-') . ': ';

                foreach ($value as list($val, $replace)) {
                    header($set . $val, $replace, $statusCode);
                }
            }

            header($protocol . ' ' . $statusCode . ' ' . $statusText, true, $statusCode);
            header('Content-Type: ' . $mime . ';charset=' . $charset, true, $statusCode);

            $this->isQuiet() || $this->throttle($this->getOutput(), $this->getMatch()['kbps'] ?? 0);
        }

        return $this;
    }

    public function throttle(string|null $text, int $kbps): void
    {
        if ($text && 0 < $kbps) {
            $ctr = 0;
            $now = microtime(true);

            foreach (str_split($text, 1024) as $part) {
                // Throttle output
                ++$ctr;

                $sleep = !connection_aborted() && $ctr / $kbps > ($elapsed = microtime(true) - $now);

                if ($sleep) {
                    usleep(round(1e6 * ($ctr / $kbps - $elapsed)));
                }

                echo $part;
            }
        } else {
            echo $text;
        }
    }

    public function chain(callable|string $cb): static
    {
        $this->di->call($cb);

        return $this;
    }

    public function getLoadDirectories(): array
    {
        return $this->data['loadDirectories'] ?? array();
    }

    public function setLoadDirectories(string|array $directories): static
    {
        $this->data['loadDirectories'] = array_map(
            static fn(string $dir) => rtrim(Str::fixslashes($dir), '/') . '/',
            Arr::ensure($directories),
        );

        return $this;
    }

    public function getLoadExtensions(): array
    {
        return $this->data['loadExtensions'] ?? array('.php');
    }

    public function setLoadExtensions(string|array $extensions): static
    {
        $this->data['loadExtensions'] = array_map(
            static fn(string $ext) => '.' . trim($ext, '.'),
            Arr::ensure($extensions),
        );

        return $this;
    }

    public function load(string $file, array $data = null, bool $safe = false, $defaults = null): mixed
    {
        $found = (
            file_exists($found = $file)
            || (
                $found = Arr::first(
                    $this->getLoadDirectories(),
                    fn (string $dir) => (
                        file_exists($found = $dir . $file)
                        || ($found = Arr::first(
                            $this->getLoadExtensions(),
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

    private function initialize(): void
    {
        $globals = explode('|', self::VAR_GLOBALS);

        array_walk($globals, function ($global) {
            if (!isset($this->env[$global])) {
                $this->env[$global] = $GLOBALS['_' . $global] ?? array();
            }
        });

        $this->data['error_html'] =
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
        $this->data['error_cli'] =
        <<<'TEXT'
{code} - {text}
{message}
{trace}

TEXT;
    }

    private function handleRun(): void
    {
        $this->dispatcher->dispatch($event = new RequestEvent());

        if ($event->isPropagationStopped()) {
            $this->handleRunResult(null, $event->getOutput());

            return;
        }

        if (!$this->routes) {
            throw new \LogicException('No route defined');
        }

        $match = $this->routeMatch();

        if (!$match) {
            throw new HttpException(404);
        }

        $this->data['match'] = $match;

        list($result, $output) = $this->handleRunHandler($match['handler'], $match['args']);

        $this->handleRunResult($result, $output);
    }

    private function handleRunHandler(string|callable $handler, array|null $args): array
    {
        ob_start();
        $result = $this->di->callArguments($handler, $args);
        $output = ob_get_clean();

        return array($result, $output);
    }

    private function handleRunResult($result, string $output): void
    {
        $this->dispatcher->dispatch($event = new ResponseEvent($result, $output));

        if (is_callable($result_ = $event->getResult())) {
            $this->di->call($result_);
        } else {
            $this->send(null === $result_ ? $event->getOutput() : $result_);
        }
    }

    private function errorBuild(ErrorEvent $error): string|array
    {
        $dev = $this->isDev();
        $data = array(
            'code' => $this->code(),
            'text' => $this->text(),
            'data' => $error->getPayload(),
            'message' => $error->getMessage() ?? sprintf('[%s] %s %s', $this->code(), $this->getVerb(), $this->getPath()),
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
}
