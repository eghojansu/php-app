<?php

declare(strict_types=1);

namespace Ekok\App;

use Ekok\Utils\Arr;
use Ekok\Utils\Str;
use Ekok\Logger\Log;
use Ekok\Utils\Call;
use Ekok\Utils\File;
use Ekok\Cache\Cache;
use Ekok\Container\Di;
use Ekok\Router\Router;
use Ekok\Config\Configurator;
use Ekok\EventDispatcher\Dispatcher;
use Ekok\EventDispatcher\Event as BaseEvent;

class Env
{
    const VAR_GLOBALS = 'GET|POST|COOKIE|FILES|SERVER|ENV';
    const ROUTE_VERBS = 'GET|POST|PUT|DELETE|HEAD|OPTIONS';
    const ROUTE_PARAMS = '/(?:\/?@(\w+)(?:(?::([^\/?]+)|(\*)))?(\?)?)/';
    const ROUTE_PATTERN = '/^\s*([\w|]+)(?:\s*@([^\s]+))?(?:\s*(\/[^\s]*))?(?:\s*\[([^\]]+)\])?\s*$/';

    /** @var Di */
    private $di;

    public static function create(string $name = null, array $data = null)
    {
        return new static($name, $data);
    }

    public function __construct(private string|null $name, private array|null $data = null)
    {
        $this->initialize();
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    public function getCache(): Cache
    {
        return $this->make(Cache::class);
    }

    public function getConfig(): Configurator
    {
        return $this->make(Configurator::class);
    }

    public function getContainer(): Di
    {
        return $this->di;
    }

    public function getDispatcher(): Dispatcher
    {
        return $this->make(Dispatcher::class);
    }

    public function getLog(): Log
    {
        return $this->make(Log::class);
    }

    public function getRouter(): Router
    {
        return $this->make(Router::class);
    }

    public function loadServices(string ...$directories): static
    {
        $this->getConfig()->loadServices(...$directories);

        return $this;
    }

    public function loadSubscribers(string ...$directories): static
    {
        $this->getConfig()->loadSubscribers(...$directories);

        return $this;
    }

    public function loadRoutes(string ...$directories): static
    {
        $this->getConfig()->loadRoutes(...$directories);

        return $this;
    }

    public function make(string $key, array $args = null)
    {
        return $this->di->make($key, $args);
    }

    public function rule(string|array $name, array|callable|string $rule = null): static
    {
        if (is_array($name)) {
            $this->di->register($name);
        } else {
            $this->di->addRule($name, $rule);
        }

        return $this;
    }

    public function call(string|callable $cb, ...$args)
    {
        return $this->di->call($cb, ...$args);
    }

    public function callArguments(string|callable $cb, array $args = null)
    {
        return $this->di->callArguments($cb, $args);
    }

    public function getCallable(string|callable $cb): callable|null
    {
        return is_callable($fn = $this->di->getCallable($cb)) ? $fn : null;
    }

    public function addSubscribers(array $subscribers): static
    {
        $this->getDispatcher()->addSubscribers($subscribers);

        return $this;
    }

    public function listen(
        string $eventName,
        callable|string $handler,
        int $priority = null,
        bool $once = false,
    ): static {
        $this->getDispatcher()->on($eventName, $handler, $priority, $once);

        return $this;
    }

    public function unlisten(string $eventName, int $pos = null): static
    {
        $this->getDispatcher()->off($eventName, $pos);

        return $this;
    }

    public function dispatch(BaseEvent $event, string $eventName = null, bool $once = false): static
    {
        $this->getDispatcher()->dispatch($event, $eventName, $once);

        return $this;
    }

    public function log(string $level, string $message, array $context = null): static
    {
        $this->getLog()->log($level, $message, $context);

        return $this;
    }

    public function chain(callable|string $cb): static
    {
        $this->call($cb);

        return $this;
    }

    public function has(string $key): bool
    {
        return (
            isset($this->data[$key])
            || ($this->data && array_key_exists($key, $this->data))
            || $this->access($key)
        );
    }

    public function get(string $key)
    {
        return $this->data[$key] ?? (($this->access($key, $get)) ? $this->$get() : null);
    }

    public function set(string $key, $value): static
    {
        if ($this->access($key, $set, false, true)) {
            $this->$set($value);
        } elseif ($this->access($key)) {
            throw new \LogicException(sprintf('Data is readonly: %s', $key));
        } else {
            $this->data[$key] = $value;
        }

        return $this;
    }

    public function getData(): array
    {
        return $this->data ?? array();
    }

    public function setData(array $data): static
    {
        array_walk($data, fn($value, $key) => $this->set($key, $value));

        return $this;
    }

    public function load(string ...$files): static
    {
        array_walk($files, function (string $file) {
            $data = File::load($file, array('env' => $this)) ?? array();

            array_walk($data, function ($value, $key) {
                if ($value instanceof \Closure) {
                    $this->call($value);
                } elseif (
                    is_string($key)
                    && is_array($value)
                    && (
                        ($expr = Call::check($key))
                        || '@' === $key[0]
                    )
                ) {
                    $call = $expr ? $key : static::class . $key;
                    $norm = rtrim(strstr($call . '#', '#', true));

                    $this->callArguments($norm, $value);
                } else {
                    $this->set($key, $value);
                }
            });
        });

        return $this;
    }

    public function isName(string ...$names): bool
    {
        return Str::equals($this->getName(), ...$names);
    }

    public function getName(): string
    {
        return $this->name ?? 'prod';
    }

    public function setName(string|null $name): static
    {
        $this->name = $name ? strtolower($name) : null;

        return $this;
    }

    public function getProjectDir(): string|null
    {
        return $this->data['project_dir'] ?? null;
    }

    public function setProjectDir(string $projectDir): static
    {
        $this->data['project_dir'] = Str::fixslash($projectDir);

        return $this;
    }

    public function getTmpDir(): string|null
    {
        return $this->data['tmp_dir'] ?? (
            $this->data['tmp_dir'] = $this->getProjectDir() . '/var'
        );
    }

    public function setTmpDir(string $tmpDir): static
    {
        $this->data['tmp_dir'] = Str::fixslash($tmpDir);

        return $this;
    }

    public function getSeed(): string
    {
        return $this->data['seed'] ?? (
            $this->data['seed'] = Str::hash($this->getProjectDir() ?? static::class)
        );
    }

    public function setSeed(string|null $seed): static
    {
        $this->data['seed'] = $seed;

        return $this;
    }

    public function getNavigationMode(): string
    {
        return $this->data['navigation_mode'] ?? 'header';
    }

    public function setNavigationMode(string $mode): static
    {
        $this->data['navigation_mode'] = strtolower($mode);

        return $this;
    }

    public function getNavigationKey(): string
    {
        return $this->data['navigation_key'] ?? 'referer';
    }

    public function setNavigationKey(string $key): static
    {
        $this->data['navigation_key'] = $key;

        return $this;
    }

    public function getPreviousUrl(string $fallbackUrl = null): string|null
    {
        return $this->data['previous_url'] ?? (
            $this->data['previous_url'] = $this->getResolvedPreviousUrl() ?? $fallbackUrl
        );
    }

    public function setPreviousUrl(string $url): static
    {
        $this->data['previous_url'] = $url;

        return $this;
    }

    public function getBackUrl(): string|null
    {
        return $this->data['back_url'] ?? $this->uri();
    }

    public function setBackUrl(string $url): static
    {
        $this->data['back_url'] = $url;

        return $this;
    }

    public function isDebug(): bool
    {
        return $this->data['debug'] ?? (
            $this->data['debug'] = $this->isName('dev', 'development')
        );
    }

    public function setDebug(bool $debug): static
    {
        $this->data['debug'] = $debug;

        return $this;
    }

    public function getLanguageKey(): string
    {
        return $this->data['language_key'] ?? 'lang';
    }

    public function setLanguageKey(string $language): static
    {
        $this->data['language_key'] = $language;

        return $this;
    }

    public function isPreflight(): bool
    {
        return isset($this->data['SERVER']['HTTP_ACCESS_CONTROL_REQUEST_METHOD']);
    }

    public function isCors(): bool
    {
        return isset($this->data['SERVER']['HTTP_ORIGIN']) && $this->getCors('origin');
    }

    public function getCors(string $key = null): array|string|bool|null
    {
        return $key ? ($this->data['cors'][$key] ?? null) : ($this->data['cors'] ?? null);
    }

    public function setCors(array $cors): static
    {
        $this->data['cors'] = array_replace(
            $this->data['cors'] ?? array(
				'headers' => '',
				'origin' => false,
				'credentials' => false,
				'expose' => false,
				'ttl'=> 0,
            ),
            $cors,
        );

        return $this;
    }

    public function getExempt(): array|null
    {
        return $this->data['exempt'] ?? null;
    }

    public function addExempt(string $ip): static
    {
        $this->data['exempt'][] = $ip;

        return $this;
    }

    public function setExempt(array $ips): static
    {
        $this->data['exempt'] = array();

        array_walk($ips, fn (string $ip) => $this->addExempt($ip));

        return $this;
    }

    public function isWhitelisted(string $ip): bool
    {
        if (!($exempt = $this->getExempt()) || ($found = in_array($ip, $exempt))) {
            return $found ?? false;
        }

        $parts = explode('.', $ip);
        $expected = array_fill(0, count($parts), true);

        return Arr::some($exempt, static fn (string $str) => $expected === array_map(
            static fn ($a, $b) => !$b || '*' === $b || $a == $b,
            $parts,
            explode('.', $str),
        ));
    }

    public function getDnsbl(): array|null
    {
        return $this->data['dnsbl'] ?? null;
    }

    public function addDnsbl(string $host): static
    {
        $add = (
            filter_var($host, FILTER_VALIDATE_IP, FILTER_NULL_ON_FAILURE|FILTER_FLAG_IPV4)
            ?? filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_NULL_ON_FAILURE)
        );

        if ($add) {
            $this->data['dnsbl'][] = $add;
        }

        return $this;
    }

    public function setDnsbl(array $hosts): static
    {
        $this->data['dnsbl'] = array();

        array_walk($hosts, fn (string $host) => $this->addDnsbl($host));

        return $this;
    }

    public function isBlacklisted(string $ip): bool
    {
        if ($this->isWhitelisted($ip) || !($dnsbl = $this->getDnsbl())) {
            return false;
        }

        $ipr = implode('.', array_reverse(explode('.', $ip)));

        return Arr::some(
            $dnsbl,
            static fn(string $host) => checkdnsrr($ipr . '.' . $host, 'A'),
        );
    }

    public function isBuiltin(): bool
    {
        return $this->data['builtin'] ?? ($this->data['builtin'] = 'cli-server' === PHP_SAPI);
    }

    public function isCli(): bool
    {
        return $this->data['cli'] ?? ($this->data['cli'] = 'cli' === PHP_SAPI);
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

    public function error(
        \Throwable|int $code = 500,
        string $message = null,
        array $headers = null,
        array $payload = null,
    ): static {
        if ($code instanceof Redirection) {
            return match(true) {
                $code->isBack => $this->redirectBack($code->url),
                $code->isRoute => $this->redirectTo(
                    $code->url ?? '/',
                    $code->args,
                    $code->permanent,
                    $code->statusCode,
                ),
                default => $this->redirect(
                    $code->url ?? '/',
                    $code->permanent,
                    $code->statusCode,
                ),
            };
        }

        $error_ = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
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

        $event = new Event\Error($code_, $message_, $headers_, $payload_, $error_);

        try {
            $this->dispatch($event, null, true);
        } catch (\Throwable $error) {
            $event = new Event\Error(500, $error->getMessage() ?: null, error: $error);
        }

        if (null === $event->getMessage()) {
            $event->setMessage(sprintf(
                '%s [%s - %s] %s %s',
                $this->getClientIp() ?? 'localhost',
                $event->getCode(),
                $event->getText(),
                $this->getVerb(),
                $this->getPath(),
            ));
        }

        $this->log(
            Log::LEVEL_INFO,
            $event->getMessage(),
            Arr::formatTrace($event->getPayload() ?? $event->getError() ?? array()),
        );

        $this->runResponse($event, null, fn () => $this->errorBuild($event));

        return $this;
    }

    public function alias(string $alias, array|string $args = null): string
    {
        return $this->getRouter()->alias($alias, $args);
    }

    public function createUrl(
        string $path,
        array|string $args = null,
        bool $absolute = false,
        bool $entry = true,
    ): string {
        return (
            ($absolute ? $this->getBaseUrl() : $this->getBasePath()) .
            ($entry && ($front = $this->getEntry()) ? '/' . $front : null) .
            $this->alias($path, $args)
        );
    }

    public function url(string $path, array|string $args = null, bool $absolute = false): string
    {
        return $this->createUrl($path, $args, $absolute);
    }

    public function baseurl(string $path, array|string $args = null, bool $absolute = false): string
    {
        return $this->createUrl($path, $args, $absolute, false);
    }

    public function uri(bool $absolute = true): string
    {
        return $this->url($this->getPath(), $this->data['GET'] ?? array(), $absolute);
    }

    public function getMatch(
        string $key = null,
        array|string|callable|null $default = null,
    ): array|string|callable|null {
        return $key ? ($this->data['match'][$key] ?? $default) : ($this->data['match'] ?? null);
    }

    public function redirect(string $url, bool $permanent = null, int $code = null): static
    {
        $event = new Event\Redirect($code ?? ($permanent ? 301 : 302), $url, $permanent);

        $this->dispatch($event);

        if (!$event->isPropagationStopped()) {
            $this->addHeader('Location', $event->getUrl());
        }

        if (!$this->runInterception($event)) {
            $this->runResponse($event);
        }

        return $this;
    }

    public function redirectTo(
        string $path,
        array $args = null,
        bool $permanent = null,
        int $code = null,
    ): static {
        return $this->redirect(
            $this->url($path, $args, true),
            $permanent,
            $code,
        );
    }

    public function redirectBack(string $fallbackUrl = null): static
    {
        return $this->redirect($this->getPreviousUrl($fallbackUrl ?? $this->url('/')), null, 303);
    }

    public function routeAll(array $routes): static
    {
        $this->getRouter()->routeAll($routes);

        return $this;
    }

    public function route(string $route, callable|string|null $handler = null): static
    {
        $this->getRouter()->route($route, $handler);

        return $this;
    }

    public function rerouteAll(array $routes): static
    {
        array_walk($routes, fn ($args, $route) => $this->reroute($route, ...((array) $args)));

        return $this;
    }

    public function reroute(string $route, string $url, bool $permanent = true): static
    {
        return $this->route($route, fn () => $this->redirect($url, $permanent));
    }

    public function getContentType(): string
    {
        return $this->data['contenttype'] ?? (
            $this->data['contenttype'] = $this->data['SERVER']['CONTENT_TYPE'] ?? ''
        );
    }

    public function isContentType(string $mime): bool
    {
        return !!preg_match('/\b' . preg_quote($mime, '/') . '\b/i', $this->getContentType());
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
        return $this->data['raw'] ?? false;
    }

    public function setRaw(bool $raw): static
    {
        $this->data['raw'] = $raw;

        return $this;
    }

    public function getJson(): array
    {
        return json_decode($this->getBody() ?? '[]', true) ?: array();
    }

    public function getBody(): string|null
    {
        return $this->data['body'] ?? (
            $this->data['body'] = $this->isRaw() ? null : file_get_contents('php://input')
        );
    }

    public function setBody(string $body): static
    {
        $this->data['body'] = $body;

        return $this;
    }

    public function getPost(string $name = null)
    {
        return $name ? ($this->data['POST'][$name] ?? null) : $this->data['POST'];
    }

    public function getPostInt(string $name, int $default = null): int
    {
        return is_numeric($val = $this->getPost($name)) ? intval($val) : $default ?? 0;
    }

    public function getQuery(string $name = null)
    {
        return $name ? ($this->data['GET'][$name] ?? null) : $this->data['GET'];
    }

    public function getQueryInt(string $name, int $default = null): int
    {
        return is_numeric($val = $this->getQuery($name)) ? intval($val) : $default ?? 0;
    }

    public function getFiles(string $name = null)
    {
        return $name ? ($this->data['FILES'][$name] ?? null) : $this->data['FILES'];
    }

    public function getServer(string $name = null)
    {
        return $name ? ($this->data['SERVER'][$name] ?? null) : $this->data['SERVER'];
    }

    public function getEnv(string $name = null)
    {
        return $name ? ($this->data['ENV'][$name] ?? null) : $this->data['ENV'];
    }

    public function getServerIp(): string
    {
        return $this->data['SERVER']['SERVER_ADDR'] ?? gethostname();
    }

    public function getClientIp(string|int|null $version = null, string ...$ranges): string|null
    {
        $options = FILTER_NULL_ON_FAILURE | array_reduce(
            $ranges,
            fn (int $options, string $range) => $options | (
                defined($flag = 'FILTER_FLAG_' . strtoupper($range) . '_RANGE') ? constant($flag) : 0
            ),
            $version && defined($flag = 'FILTER_FLAG_IPV' . $version) ? constant($flag) : FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6,
        );

        return Arr::first(
            array(
                'HTTP_CLIENT_IP',
                'HTTP_X_FORWARDED_FOR',
                'HTTP_X_FORWARDED',
                'HTTP_X_CLUSTER_CLIENT_IP',
                'HTTP_FORWARDED_FOR',
                'HTTP_FORWARDED',
                'REMOTE_ADDR',
            ),
            fn (string $key) => Arr::first(
                explode(',', $this->data['SERVER'][$key] ?? ''),
                static fn (string $ip) => filter_var(trim($ip), FILTER_VALIDATE_IP, $options),
            ),
        );
    }

    public function getUserAgent(): string|null
    {
        return $this->data['SERVER']['HTTP_USER_AGENT'] ?? null;
    }

    public function getAuthorization(): string|null
    {
        return $this->data['SERVER']['HTTP_AUTHORIZATION'] ?? null;
    }

    public function getAuthorizationBearer(): string|null
    {
        return sscanf($this->getAuthorization() ?? '', 'Bearer %s', $token) > 0 ? $token : null;
    }

    public function wantsJson(): bool
    {
        return $this->accept('json');
    }

    public function accept(string $mime = null, bool $all = false): array|string|bool
    {
        $accept = $this->data['SERVER']['HTTP_ACCEPT'] ?? '*/*';

        return match(true) {
            !!$mime => !!preg_match('/\b' . preg_quote($mime, '/') . '\b/i', $accept),
            $all => array_column(Http::parseHeader($accept), 'content'),
            default => Http::parseHeader($accept)[0]['content'],
        };
    }

    public function acceptLanguage(string $lang = null, bool $all = false): array|string|bool
    {
        $accept = $this->data['SERVER']['HTTP_ACCEPT_LANGUAGE'] ?? '*';

        return match(true) {
            !!$lang => !!preg_match('/\b' . preg_quote($lang, '/') . '\b/i', $accept),
            $all => array_column(Http::parseHeader($accept), 'content'),
            default => Http::parseHeader($accept)[0]['content'],
        };
    }

    public function wantLanguage(): string|null
    {
        return (
            $this->data['GET'][$key = $this->getLanguageKey()]
            ?? $this->session($key)
            ?? (trim($this->acceptLanguage(), '*') ?: null)
        );
    }

    public function headers(string $key = null): array|string|null
    {
        if ($key) {
            return (
                $this->data['SERVER'][$key]
                ?? $this->data['SERVER'][$upper = strtoupper(str_replace('-', '_', $key))]
                ?? $this->data['SERVER']['HTTP_' . $upper]
                ?? null
            );
        }

        return Arr::reduce(
            $this->data['SERVER'] ?? array(),
            static fn ($headers, $header, $key) => $headers + (
                str_starts_with($key, 'HTTP_') ?
                    array(ucwords(strtolower(str_replace('_', '-', substr($key, 5))), '-') => $header) :
                    array()
            ),
            array(
                'Content-Type' => $this->data['SERVER']['CONTENT_TYPE'] ?? null,
                'Content-Length' => $this->data['SERVER']['CONTENT_LENGTH'] ?? null,
            ),
        );
    }

    public function getBasePath(): string
    {
        return $this->data['basepath'] ?? (
            $this->data['basepath'] = $this->isBuiltin() || $this->isCli() ? '' :
                Str::fixslash(dirname($this->data['SERVER']['SCRIPT_NAME'] ?? ''))
        );
    }

    public function setBasePath(string $basePath): static
    {
        $this->data['basepath'] = ($basePath && '/' !== $basePath[0] ? '/' : '') . $basePath;

        return $this;
    }

    public function getBaseUrl(): string
    {
        return $this->data['baseurl'] ?? ($this->data['baseurl'] = (
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
        $this->data['baseurl'] = $baseUrl;

        return $this;
    }

    public function getPath(): string
    {
        if (null === ($this->data['path'] ?? null)) {
            $entry = $this->getEntry();
            $basePath = $this->getBasePath();
            $uri = rawurldecode(strstr(($this->data['SERVER']['REQUEST_URI'] ?? '') . '?', '?', true));
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

    public function isSecure(): bool
    {
        return $this->data['secure'] ?? (
            $this->data['secure'] = !!($this->data['SERVER']['HTTPS'] ?? null)
        );
    }

    public function setSecure(bool $secure): static
    {
        $this->data['secure'] = $secure;

        return $this;
    }

    public function getScheme(): string
    {
        return $this->data['scheme'] ?? (
            $this->data['scheme'] = $this->isSecure() ? 'https' : 'http'
        );
    }

    public function setScheme(string $scheme): static
    {
        $this->data['scheme'] = $scheme;

        return $this;
    }

    public function getHost(): string
    {
        return $this->data['host'] ?? (
            $this->data['host'] = strstr(($this->data['SERVER']['HTTP_HOST'] ?? 'localhost') . ':', ':', true)
        );
    }

    public function setHost(string $host): static
    {
        $this->data['host'] = $host;

        return $this;
    }

    public function getPort(): int
    {
        return $this->data['port'] ?? (
            $this->data['port'] = intval($this->data['SERVER']['SERVER_PORT'] ?? 80)
        );
    }

    public function setPort(string|int $port): static
    {
        $this->data['port'] = intval($port);

        return $this;
    }

    public function getEntry(): string|null
    {
        return $this->data['entry'] ?? (
            $this->data['entry'] = $this->isBuiltin() || $this->isCli() ? '' : basename($this->data['SERVER']['SCRIPT_NAME'] ?? '')
        );
    }

    public function setEntry(string|null $entry): static
    {
        $this->data['entry'] = $entry;

        return $this;
    }

    public function isVerb(string ...$verbs): bool
    {
        return (
            Str::equals($this->getVerb(), ...$verbs)
            || Str::equals(strtolower($this->getVerb()), ...$verbs)
        );
    }

    public function getVerb(): string
    {
        return $this->data['verb'] ?? (
            $this->data['verb'] = $this->data['SERVER']['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $this->data['POST']['_method'] ?? $this->data['SERVER']['REQUEST_METHOD'] ?? 'GET'
        );
    }

    public function setVerb(string $verb): static
    {
        $this->data['verb'] = strtoupper($verb);

        return $this;
    }

    public function getProtocol(): string
    {
        return $this->data['protocol'] ?? (
            $this->data['protocol'] = $this->data['SERVER']['SERVER_PROTOCOL'] ?? 'HTTP/1.1'
        );
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

    public function getMimeFile(string $file): string
    {
        $mimes = $this->getMimeList();
        $ext = ltrim(strrchr('.' . $file, '.'), '.');
        $list = $mimes[$ext] ?? $mimes[strtolower($ext)] ?? 'application/octet-stream';

        return is_array($list) ? reset($list) : $list;
    }

    public function getMimeList(): array
    {
        return $this->data['mime_list'] ?? array();
    }

    public function setMimeList(array $mimeList, bool $replace = true): static
    {
        if ($replace) {
            $this->data['mime_list'] = array();
        }

        array_walk($mimeList, function (string|array $mime, string $ext) {
            $this->data['mime_list'][strtolower($ext)] = $mime;
        });

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

    public function hasHeader(string $name, array &$found = null): bool
    {
        $found = array();

        if (isset($this->data['headers'][$name])) {
            $found = array($name);
        } else {
            $found = preg_grep(
                '/^' . preg_quote($name, '/') . '$/i',
                array_keys($this->data['headers'] ?? array()),
            ) ?: array();
        }

        return !!$found;
    }

    public function getHeader(string $key): array
    {
        return Arr::reduce(
            $this->hasHeader($key, $found) ? $found : array(),
            fn(array $headers, string $header) => array_merge(
                $headers,
                $this->data['headers'][$header],
            ),
            array(),
        );
    }

    public function getHeaders(): array
    {
        return $this->data['headers'] ?? array();
    }

    public function addHeader(string $name, $value, bool $replace = true): static
    {
        if ($replace) {
            $this->removeHeaders($name);

            $this->data['headers'][$name] = (array) $value;
        } else {
            if (is_array($value)) {
                array_walk($value, function ($value) use ($name) {
                    $this->data['headers'][$name][] = $value;
                });
            } else {
                $this->data['headers'][$name][] = $value;
            }
        }

        return $this;
    }

    public function setHeaders(array $headers, bool $replace = false): static
    {
        if ($replace) {
            $this->data['headers'] = array();
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
                unset($this->data['headers'][$key]);
            });
        });

        return $this;
    }

    public function getCookieJar(): array
    {
        return $this->data['cookie_jar'] ?? array(
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
        $this->data['cookie_jar'] = array_replace($this->getCookieJar(), $jar);

        return $this;
    }

    public function getCookie(string $name = null)
    {
        return $name ? ($this->data['COOKIE'][$name] ?? null) : $this->data['COOKIE'];
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

            $this->data['COOKIE'][$name] = $value;
        } else {
            $exp = -2592000;
            $cookie .= 'deleted';

            unset($this->data['COOKIE'][$name]);
        }

        if ($set = $exp ?? $expires ?? $jar['expires']) {
            $cookie .= '; Expires=' . Http::stamp($set, null, $max) . '; Max-Age=' . $max;
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

    public function session(string $name = null, ...$sets)
    {
        (PHP_SESSION_ACTIVE === session_status() || $this->sent()) || session_start();

        if (!isset($this->data['session'])) {
            $this->data['session'] = &$GLOBALS['_SESSION'];
        }

        if ($name) {
            if ($sets) {
                $this->data['session'][$name] = $sets[0];
            }

            return $this->data['session'][$name] ?? null;
        }

        return $this->data['session'];
    }

    public function flashSession(string $name)
    {
        $value = $this->session($name);

        unset($this->data['session'][$name]);

        return $value;
    }

    public function getSession(string $name = null)
    {
        return $this->session($name);
    }

    public function setSession(string $name, $value): static
    {
        $this->session($name, $value);

        return $this;
    }

    public function removeSession(string $name = null): static
    {
        $this->session();

        if ($name) {
            unset($this->data['session'][$name]);
        } else {
            session_unset();
            session_destroy();
        }

        return $this;
    }

    public function getOutput(): string|null
    {
        return $this->data['output'] ?? null;
    }

    public function setOutput($value, string $mime = null): static
    {
        list($setOutput, $setMime) = match(true) {
            is_array($value) || $value instanceof \JsonSerializable => array(
                json_encode($value),
                'application/json',
            ),
            is_scalar($value) || $value instanceof \Stringable => array(
                (string) $value,
                'text/html',
            ),
            default => array(null, null),
        };

        if ($mime || (!$this->getMime() && $setMime)) {
            $this->setMime($mime ?? $setMime);
        }

        $this->data['output'] = $setOutput;

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

    public function isBuffering(): bool
    {
        return $this->data['buffering'] ?? true;
    }

    public function setBuffering(bool $buffering): static
    {
        $this->data['buffering'] = $buffering;

        return $this;
    }

    public function getBufferingLevel(): int|null
    {
        return $this->data['buffering_level'] ?? null;
    }

    public function startBuffering(): bool
    {
        if ($this->isBuffering()) {
            $this->data['buffering_level'] = ob_get_level();

            return ob_start();
        }

        return false;
    }

    public function stopBuffering(): array
    {
        $buffers = array();

        if ($level = $this->getBufferingLevel()) {
            while (ob_get_level() > $level) {
                $buffers[] = ob_get_clean();
            }

            $this->data['buffering_level'] = null;
        }

        return $buffers;
    }

    public function code(): int|null
    {
        return $this->data['code'] ?? null;
    }

    public function text(): string|null
    {
        return $this->data['text'] ?? null;
    }

    public function status(int $code): static
    {
        $this->data['text'] = Http::statusText($code);
        $this->data['code'] = $code;

        return $this;
    }

    public function sent(): bool
    {
        return $this->data['sent'] ?? ($this->data['sent'] = headers_sent());
    }

    public function send(
        $value = null,
        array $headers = null,
        int $status = null,
        string|null $mime = null,
        int $kbps = null,
    ): static {
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

        if ($this->isVerb('GET', 'HEAD') && $backUrl = $this->getBackUrl()) {
            $this->setBackUrlForNextRequest($backUrl);
        }

        foreach ($this->data['headers'] ?? array() as $name => $headers) {
            $set = ucwords($name, '-') . ': ';
            $replace = empty($headers[1]);

            foreach ($headers as $header) {
                header($set . $header, $replace, $code);
            }
        }

        header($this->getProtocol() . ' ' . $code . ' ' . $this->text(), true, $code);

        $this->stopBuffering();

        if (is_callable($value)) {
            $this->call($value);
        } elseif ($shout) {
            $this->shoutText($output, $kbps);
        }

        $this->data['sent'] = true;

        return $this;
    }

    public function sendFile(
        string $file,
        array $headers = null,
        string|bool $download = null,
        bool $range = false,
        string|null $mime = null,
        int $kbps = null,
    ): static {
        if ($this->sent()) {
            return $this;
        }

        $lastModified = filemtime($file);
        $modifiedSince = $this->data['SERVER']['HTTP_IF_MODIFIED_SINCE'] ?? null;

        $this->addHeader('Last-Modified', Http::stamp($lastModified));

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
        $header = $range ? ($this->data['SERVER']['HTTP_RANGE'] ?? null) : null;

        if (
            $header
            && !preg_match('/^bytes=(?:(\d+)-(\d+)?)|(?:\-(\d+))/', $header, $parts, PREG_UNMATCHED_AS_NULL)
        ) {
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
            'Expires' => Http::stamp(time() + 604800),
        ));
        $this->setHeaders($headers ?? array());
        $this->send(status: $status, mime: $mime ?? $this->getMimeFile($file));
        $this->shoutFile($file, $kbps, $offset, $length);

        return $this;
    }

    public function shoutFile(
        string $file,
        int $kbps = null,
        int $seek = null,
        int $length = null,
    ): void {
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
                usleep(intval(1e6 * ($ctr / $kbps - $elapsed)));
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
                usleep(intval(1e6 * ($ctr / $kbps - $elapsed)));
            }
        }
    }

    public function getRenderSetup(): array
    {
        return $this->data['render_setup'] ?? array(
            'directories' => array(),
            'extensions' => array('.php'),
        );
    }

    public function setRenderSetup(
        string|array $directories,
        string|array $extensions = null,
    ): static {
        $this->data['render_setup'] = array(
            'directories' => array_map(
                static fn(string $dir) => Str::fixslash($dir) . '/',
                Arr::ensure($directories),
            ),
            'extensions' => array_map(
                static fn(string $ext) => '.' . trim($ext, '.'),
                Arr::ensure($extensions ?? array('.php')),
            ),
        );

        return $this;
    }

    public function renderFind(string $file): string|null
    {
        $setup = $this->getRenderSetup();

        return (
            file_exists($found = $file)
            || (
                $found = Arr::first(
                    $setup['directories'],
                    fn (string $dir) => (
                        file_exists($found = $dir . $file)
                        || ($found = Arr::first(
                            $setup['extensions'],
                            static fn(string $ext) => (
                                file_exists($found = $dir . $file . $ext)
                                || file_exists($found = $dir . strtr($file, '.', '/') . $ext) ?
                                    $found : null
                            )
                        )) ? $found : null
                    )
                )
            )
        ) ? $found : null;
    }

    public function render(
        string $file,
        array $data = null,
        bool $safe = false,
        $defaults = null,
    ): mixed {
        $found = $this->renderFind($file);

        if (!$found && $safe) {
            return $defaults;
        }

        if (!$found) {
            throw new \LogicException(sprintf('File not found: "%s"', $file));
        }

        File::load($found, $data, $safe, $output);

        return $output;
    }

    public function getErrorTemplate(string $name = null): string|null
    {
        return (
            $this->data['error_template'][$name]
            ?? $this->data['error_template'][strtolower($name)]
            ?? null
        );
    }

    public function setErrorTemplate(string $name, string $template): static
    {
        $this->data['error_template'][strtolower($name)] = $template;

        return $this;
    }

    public function flattenError(Event\Error $error): array
    {
        $data = array(
            'code' => $error->getCode(),
            'text' => $error->getText(),
            'data' => $error->getPayload(),
            'message' => $error->getMessage(),
        );

        if ($this->isDebug()) {
            $data['trace'] = $error->getTrace();
        }

        return $data;
    }

    private function errorBuild(Event\Error $error): string|array
    {
        $debug = $this->isDebug();
        $data = $this->flattenError($error);

        if ($this->wantsJson()) {
            return $data;
        }

        $replace = $data;
        $replace['data'] = null;
        $replace['trace'] = $debug ? implode("\n", $replace['trace']) : null;

        if ($this->isCli()) {
            $template = $this->getErrorTemplate('cli');
        } else {
            $template = $this->getErrorTemplate('html');

            if ($debug) {
                $replace['trace'] = '<pre>' . $replace['trace'] . '</pre>';
            }
        }

        return strtr($template, Arr::quoteKeys($replace, '{}'));
    }

    private function getResolvedPreviousUrl(): string|null
    {
        return match ($this->getNavigationMode()) {
            'header' => $this->headers($this->getNavigationKey()),
            'cookie' => $this->getCookie($this->getNavigationKey()),
            'session' => $this->getSession($this->getNavigationKey()),
            'query' => urldecode($this->getQuery($this->getNavigationKey()) ?? ''),
            default => null,
        };
    }

    private function setBackUrlForNextRequest(string $url): void
    {
        match ($this->getNavigationMode()) {
            'cookie' => $this->setCookie($this->getNavigationKey(), $url),
            'session' => $this->setSession($this->getNavigationKey(), $url),
            default => 'none set',
        };
    }

    private function access(
        string $key,
        string &$method = null,
        bool $read = true,
        bool $write = false,
    ): bool {
        $case = Str::casePascal($key);
        $method = Arr::first(
            array_merge(
                $read ? array('get', 'is', 'has') : array(),
                $write ? array('set') : array(),
            ),
            fn (string $method) => match (true) {
                method_exists($this, $found = $method . $key) => $found,
                method_exists($this, $found = $method . $case) => $found,
                default => null,
            },
        );

        return !!$method;
    }

    private function runInternal(): void
    {
        $this->runSpamChecks();
        $this->dispatch($event = new Event\Request());

        if ($this->runInterception($event)) {
            return;
        }

        $this->runPreCors();

        $match = $this->getRouter()->match($this->getPath(), $this->getVerb(), $routes);

        if (!$routes || (!$match && !$this->isVerb('OPTIONS'))) {
            throw Http::error(404);
        }

        $result = null;
        $output = null;
        $kbps = 0;

        if ($match && !$this->isPreflight()) {
            $this->data['match'] = $match;

            $this->dispatch($event = new Event\RouteMatch($match));

            if ($this->runInterception($event)) {
                return;
            }

            if (!$controller = $this->getCallable($event->getController())) {
                throw Http::error(404);
            }

            $kbps = $event->getKbps();

            list($result, $output) = $this->runHandle($controller, $event->getArguments());
        }

        if ($this->isVerb('OPTIONS')) {
            $result = null;
            $output = null;
        }

        $this->runPostCors(array_keys($routes));
        $this->runResponse($result, $output, null, $kbps);
    }

    private function runHandle(callable $handler, array|null $args): array
    {
        $this->startBuffering();

        $result = $this->callArguments($handler, $args);
        $output = $this->stopBuffering();

        return array($result, $output[0] ?? null);
    }

    private function runInterception(BaseEvent $event): bool
    {
        if ($this->sent()) {
            return true;
        }

        if ($event->isPropagationStopped()) {
            $this->runResponse($event);

            return true;
        }

        return false;
    }

    private function runResponse(
        $result,
        string $output = null,
        \Closure $getOutput = null,
        string|int $kbps = null,
    ): void {
        if ($result instanceof Event\Request) {
            $event = new Event\Response(null);
            $event->setOutput($result->getOutput() ?? $output);
            $event->setHeaders($result->getHeaders());
            $event->setCode($result->getCode());
            $event->setKbps($result->getKbps());
            $event->setMime($result->getMime());
            $event->setKbps($result->getKbps() ?? intval($kbps ?? 0));
        } else {
            $event = new Event\Response($result, $output);
            $event->setKbps(intval($kbps ?? 0));
        }

        $this->dispatch($event);

        if (is_callable($response = $event->getResult())) {
            $this->call($response);
        } elseif (
            $event->getOutput()
            || (
                !$response
                || ($raw = is_scalar($response) || is_array($response) || $response instanceof \Stringable)
            )
        ) {
            $this->send(
                $event->getOutput() ?: (($raw ?? false) ? $response : $event->getOutput() ?? ($getOutput ? $getOutput() : null)),
                $event->getHeaders(),
                $event->getCode(),
                $event->getMime(),
                $event->getKbps(),
            );
        }
    }

    private function runSpamChecks(): void
    {
        if (
            ($ip = $this->getClientIp())
            && $this->isBlacklisted($ip)
        ) {
            throw Http::error(403);
        }
    }

    private function runPreCors(): void
    {
        $headers = $this->isCors() ? array(
            'Access-Control-Allow-Origin' => (true === ($allow = $this->getCors('origin'))) ? $this->data['SERVER']['HTTP_ORIGIN'] : $allow,
            'Access-Control-Allow-Credentials' => var_export($this->getCors('credentials') ?? false, true),
        ) : array();

        $this->setHeaders($headers);
    }

    private function runPostCors(array $verbs): void
    {
        $cors = $this->isCors();
        $setup = $this->getCors();
        $allowed = implode(',', $verbs);
        $headers = array();

        if ($cors && ($setup['expose'] ?? null)) {
            $headers['Access-Control-Expose-Headers'] = $setup['expose'];
        }

        if (!$this->hasHeader('Allow')) {
            $headers['Allow'] = $allowed;
        }

        if ($cors && !$this->hasHeader('Access-Control-Allow-Methods')) {
            $headers['Access-Control-Allow-Methods'] = 'OPTIONS,' . $allowed;
        }

        if ($cors && ($setup['headers'] ?? null) && !$this->hasHeader('Access-Control-Allow-Headers')) {
            $headers['Access-Control-Allow-Headers'] = $setup['headers'];
        }

        if ($cors && ($setup['ttl'] ?? null)) {
            $headers['Access-Control-Max-Age'] = var_export($setup['ttl'], true);
        }

        $this->setHeaders($headers);
    }

    private function initialize(): void
    {
        $this->registerSelf();
        $this->registerGlobals();
        $this->registerErrorTemplate();
        $this->registerExceptionHandler();
        $this->registerContainerRules();
        $this->registerConfiguration();
    }

    private function registerSelf(): void
    {
        $this->di = new Di();
        $this->di->inject($this, array('alias' => 'env', 'name' => static::class));

        if (self::class !== static::class) {
            $this->di->addAlias(self::class, static::class);
        }
    }

    private function registerGlobals(): void
    {
        $globals = explode('|', self::VAR_GLOBALS);

        array_walk($globals, function ($global) {
            if (!isset($this->data[$global])) {
                $this->data[$global] = $GLOBALS['_' . $global] ?? array();
            }
        });
    }

    private function registerErrorTemplate(): void
    {
        $this->data['error_template']['html'] =
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
        $this->data['error_template']['cli'] =
        <<<'TEXT'
{code} - {text}
{message}
{trace}

TEXT;
    }

    private function registerExceptionHandler(): void
    {
        set_exception_handler(fn(\Throwable $error) => $this->error($error));
    }

    private function registerContainerRules(): void
    {
        $this->di->register($this->getContainerRules());
    }

    protected function registerConfiguration(): void
    {
        // anything to do loading advanced configuration
    }

    protected function getContainerRules(): array
    {
        return array_fill_keys(
            array(
                Cache::class,
                Configurator::class,
                Dispatcher::class,
                Log::class,
                Router::class,
            ),
            array('shared' => true, 'alias' => true, 'inherit' => false),
        );
    }
}
