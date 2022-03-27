<?php

declare(strict_types=1);

namespace Ekok\App;

use Ekok\Utils\Str;

/**
 * Http utils
 *
 * @method static HttpException errorBadRequest(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorUnauthorized(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorPaymentRequired(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorForbidden(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorNotFound(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorMethodNotAllowed(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorNotAcceptable(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorProxyAuthenticationRequired(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorRequestTimeout(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorConflict(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorGone(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorLengthRequired(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorPreconditionFailed(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorPayloadTooLarge(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorUriTooLong(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorUnsupportedMediaType(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorRangeNotSatisfiable(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorExpectationFailed(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorIMATeapot(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorUnprocessableEntity(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorTooEarly(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorUpgradeRequired(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorPreconditionRequired(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorTooManyRequests(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorRequestHeaderFieldsTooLarge(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorUnavailableForLegalReasons(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorInternalServerError(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorNotImplemented(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorBadGateway(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorServiceUnavailable(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorGatewayTimeout(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorHttpVersionNotSupported(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorVariantAlsoNegotiates(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorInsufficientStorage(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorLoopDetected(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorNotExtended(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 * @method static HttpException errorNetworkAuthenticationRequired(string $message = null, array $payload = null, array $headers = null, int $code = 0, \Throwable $previous = null)
 */
class Http
{
    const CONTINUE                        = 100;
    const SWITCHING_PROTOCOLS             = 101;
    const EARLY_HINTS                     = 103;
    const OK                              = 200;
    const CREATED                         = 201;
    const ACCEPTED                        = 202;
    const NON_AUTHORITATIVE_INFORMATION   = 203;
    const NO_CONTENT                      = 204;
    const RESET_CONTENT                   = 205;
    const PARTIAL_CONTENT                 = 206;
    const MULTIPLE_CHOICES                = 300;
    const MOVED_PERMANENTLY               = 301;
    const FOUND                           = 302;
    const SEE_OTHER                       = 303;
    const NOT_MODIFIED                    = 304;
    const TEMPORARY_REDIRECT              = 307;
    const PERMANENT_REDIRECT              = 308;
    const BAD_REQUEST                     = 400;
    const UNAUTHORIZED                    = 401;
    const PAYMENT_REQUIRED                = 402;
    const FORBIDDEN                       = 403;
    const NOT_FOUND                       = 404;
    const METHOD_NOT_ALLOWED              = 405;
    const NOT_ACCEPTABLE                  = 406;
    const PROXY_AUTHENTICATION_REQUIRED   = 407;
    const REQUEST_TIMEOUT                 = 408;
    const CONFLICT                        = 409;
    const GONE                            = 410;
    const LENGTH_REQUIRED                 = 411;
    const PRECONDITION_FAILED             = 412;
    const PAYLOAD_TOO_LARGE               = 413;
    const URI_TOO_LONG                    = 414;
    const UNSUPPORTED_MEDIA_TYPE          = 415;
    const RANGE_NOT_SATISFIABLE           = 416;
    const EXPECTATION_FAILED              = 417;
    const I_M_A_TEAPOT                    = 418;
    const UNPROCESSABLE_ENTITY            = 422;
    const TOO_EARLY                       = 425;
    const UPGRADE_REQUIRED                = 426;
    const PRECONDITION_REQUIRED           = 428;
    const TOO_MANY_REQUESTS               = 429;
    const REQUEST_HEADER_FIELDS_TOO_LARGE = 431;
    const UNAVAILABLE_FOR_LEGAL_REASONS   = 451;
    const INTERNAL_SERVER_ERROR           = 500;
    const NOT_IMPLEMENTED                 = 501;
    const BAD_GATEWAY                     = 502;
    const SERVICE_UNAVAILABLE             = 503;
    const GATEWAY_TIMEOUT                 = 504;
    const HTTP_VERSION_NOT_SUPPORTED      = 505;
    const VARIANT_ALSO_NEGOTIATES         = 506;
    const INSUFFICIENT_STORAGE            = 507;
    const LOOP_DETECTED                   = 508;
    const NOT_EXTENDED                    = 510;
    const NETWORK_AUTHENTICATION_REQUIRED = 511;

    const STATUS = array(
        self::CONTINUE => 'Continue',
        self::SWITCHING_PROTOCOLS => 'Switching Protocols',
        self::EARLY_HINTS => 'Early Hints',
        self::OK => 'OK',
        self::CREATED => 'Created',
        self::ACCEPTED => 'Accepted',
        self::NON_AUTHORITATIVE_INFORMATION => 'Non-Authoritative Information',
        self::NO_CONTENT => 'No Content',
        self::RESET_CONTENT => 'Reset Content',
        self::PARTIAL_CONTENT => 'Partial Content',
        self::MULTIPLE_CHOICES => 'Multiple Choices',
        self::MOVED_PERMANENTLY => 'Moved Permanently',
        self::FOUND => 'Found',
        self::SEE_OTHER => 'See Other',
        self::NOT_MODIFIED => 'Not Modified',
        self::TEMPORARY_REDIRECT => 'Temporary Redirect',
        self::PERMANENT_REDIRECT => 'Permanent Redirect',
        self::BAD_REQUEST => 'Bad Request',
        self::UNAUTHORIZED => 'Unauthorized',
        self::PAYMENT_REQUIRED => 'Payment Required',
        self::FORBIDDEN => 'Forbidden',
        self::NOT_FOUND => 'Not Found',
        self::METHOD_NOT_ALLOWED => 'Method Not Allowed',
        self::NOT_ACCEPTABLE => 'Not Acceptable',
        self::PROXY_AUTHENTICATION_REQUIRED => 'Proxy Authentication Required',
        self::REQUEST_TIMEOUT => 'Request Timeout',
        self::CONFLICT => 'Conflict',
        self::GONE => 'Gone',
        self::LENGTH_REQUIRED => 'Length Required',
        self::PRECONDITION_FAILED => 'Precondition Failed',
        self::PAYLOAD_TOO_LARGE => 'Payload Too Large',
        self::URI_TOO_LONG => 'URI Too Long',
        self::UNSUPPORTED_MEDIA_TYPE => 'Unsupported Media Type',
        self::RANGE_NOT_SATISFIABLE => 'Range Not Satisfiable',
        self::EXPECTATION_FAILED => 'Expectation Failed',
        self::I_M_A_TEAPOT => 'I\'m a teapot',
        self::UNPROCESSABLE_ENTITY => 'Unprocessable Entity',
        self::TOO_EARLY => 'Too Early',
        self::UPGRADE_REQUIRED => 'Upgrade Required',
        self::PRECONDITION_REQUIRED => 'Precondition Required',
        self::TOO_MANY_REQUESTS => 'Too Many Requests',
        self::REQUEST_HEADER_FIELDS_TOO_LARGE => 'Request Header Fields Too Large',
        self::UNAVAILABLE_FOR_LEGAL_REASONS => 'Unavailable For Legal Reasons',
        self::INTERNAL_SERVER_ERROR => 'Internal Server Error',
        self::NOT_IMPLEMENTED => 'Not Implemented',
        self::BAD_GATEWAY => 'Bad Gateway',
        self::SERVICE_UNAVAILABLE => 'Service Unavailable',
        self::GATEWAY_TIMEOUT => 'Gateway Timeout',
        self::HTTP_VERSION_NOT_SUPPORTED => 'HTTP Version Not Supported',
        self::VARIANT_ALSO_NEGOTIATES => 'Variant Also Negotiates',
        self::INSUFFICIENT_STORAGE => 'Insufficient Storage',
        self::LOOP_DETECTED => 'Loop Detected',
        self::NOT_EXTENDED => 'Not Extended',
        self::NETWORK_AUTHENTICATION_REQUIRED => 'Network Authentication Required',
    );

    public static function __callStatic($name, $arguments)
    {
        if (
            preg_match('/^error(.+)$/i', $name, $match)
            && (
                defined($name = 'self::' . strtoupper($match[1]))
                || defined($name = 'self::' . strtoupper(Str::caseSnake($match[1])))
            )
            && (($code = constant($name)) >= 400)
        ) {
            return self::error($code, ...$arguments);
        }

        return self::error(500, sprintf('Invalid error call: %s', $name));
    }

    public static function statusText(int $code, bool $safe = true, bool &$exists = null): string
    {
        $exists = isset(self::STATUS[$code]);
        $text = self::STATUS[$code] ?? sprintf('Unsupported HTTP code: %s', $code);

        if (!$exists && !$safe) {
            throw new \LogicException($text);
        }

        return $text;
    }

    public static function parseHeader(string $text, bool $sort = true): array
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

        if ($sort) {
            usort($accepts, static function (array $a, array $b) {
                return ($b['q'] ?? 1) <=> ($a['q'] ?? 1);
            });
        }

        return $accepts;
    }

    public static function stamp(\DateTime|string|int $time = null, string $format = null, int &$diff = null): string
    {
        $ts = match (true) {
            $time instanceof \DateTime => (clone $time)->setTimezone(new \DateTimeZone('GMT'))->getTimestamp(),
            is_string($time) => (new \DateTime($time, new \DateTimeZone('GMT')))->getTimestamp(),
            $time < 0 => time() + $time,
            default => $time ?? time(),
        };
        $diff = $ts - time();

        return gmdate($format ?? \DateTimeInterface::RFC7231, $ts);
    }

    public static function error(
        int $statusCode = 500,
        string $message = null,
        array $payload = null,
        array $headers = null,
        int $code = 0,
        \Throwable $previous = null,
    ): HttpException {
        return new HttpException(
            $statusCode,
            $message,
            $payload,
            $headers,
            $code,
            $previous,
        );
    }
}
