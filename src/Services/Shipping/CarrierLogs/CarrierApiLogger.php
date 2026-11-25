<?php

namespace Dashed\DashedEcommerceCore\Services\Shipping\CarrierLogs;

use Throwable;
use GuzzleHttp\Promise\Create;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Dashed\DashedEcommerceCore\Models\CarrierApiLog;

/**
 * Writes carrier API traffic to carrier_api_logs, so a failed label or status
 * call can be inspected afterwards (what we sent, what came back).
 *
 * Three ways in:
 * - middleware(): a Guzzle middleware for the Laravel Http client
 *   (`Http::withMiddleware(CarrierApiLogger::middleware('veloyd', 'create_label', $orderId))`).
 *   Every attempt (also retries) becomes one row.
 * - track(): wraps a call that is not made through the Http client (an SDK
 *   with its own curl). Logs the result or the exception, then rethrows.
 * - record(): writes one row directly.
 *
 * Headers are never stored (they carry the API key); credentials in the
 * query string are masked. Logging never breaks the carrier call itself.
 */
class CarrierApiLogger
{
    /** Bodies above this size are cut off, so one huge PDF/base64 response cannot bloat the table. */
    public const MAX_BODY_BYTES = 1048576;

    /** Query parameters that carry credentials and are masked in the stored URL. */
    private const SECRET_QUERY_KEYS = ['api_token', 'apitoken', 'api_key', 'apikey', 'key', 'token', 'access_token', 'password', 'secret'];

    private static bool $enabled = true;

    public static function disable(): void
    {
        self::$enabled = false;
    }

    public static function enable(): void
    {
        self::$enabled = true;
    }

    /**
     * Guzzle middleware that logs each request/response pair, including
     * connection errors and timeouts (status null, error message as body).
     */
    public static function middleware(string $carrier, string $action, ?int $orderId = null): callable
    {
        return function (callable $handler) use ($carrier, $action, $orderId): callable {
            return function (RequestInterface $request, array $options) use ($handler, $carrier, $action, $orderId) {
                $start = microtime(true);
                $requestBody = self::readRequestBody($request);
                $log = function (?int $status, ?string $responseBody) use ($carrier, $action, $orderId, $request, $requestBody, $start): void {
                    self::record(
                        $carrier,
                        $action,
                        $orderId,
                        $request->getMethod(),
                        (string) $request->getUri(),
                        $requestBody,
                        $status,
                        $responseBody,
                        self::elapsedMs($start),
                    );
                };

                try {
                    $promise = $handler($request, $options);
                } catch (Throwable $e) {
                    $log(null, self::describeThrowable($e));

                    throw $e;
                }

                return $promise->then(
                    function ($response) use ($log) {
                        if ($response instanceof ResponseInterface) {
                            $log($response->getStatusCode(), self::readResponseBody($response));
                        }

                        return $response;
                    },
                    function ($reason) use ($log) {
                        $response = is_object($reason) && method_exists($reason, 'getResponse') ? $reason->getResponse() : null;

                        if ($response instanceof ResponseInterface) {
                            $log($response->getStatusCode(), self::readResponseBody($response));
                        } else {
                            $log(null, $reason instanceof Throwable ? self::describeThrowable($reason) : (string) json_encode($reason));
                        }

                        return Create::rejectionFor($reason);
                    }
                );
            };
        };
    }

    /**
     * Runs $call and logs it as one carrier request. For SDKs that do their
     * own HTTP: we log the payload we handed the SDK and a description of the
     * result ($describe($result), default: the result as JSON). A thrown
     * exception is logged with status null and its message, then rethrown.
     *
     * @template T
     *
     * @param  callable(): T  $call
     * @param  (callable(T): mixed)|null  $describe
     * @return T
     */
    public static function track(
        string $carrier,
        string $action,
        ?int $orderId,
        string $method,
        string $url,
        mixed $requestBody,
        callable $call,
        ?callable $describe = null,
        int $successStatus = 200,
    ): mixed {
        $start = microtime(true);

        try {
            $result = $call();
        } catch (Throwable $e) {
            self::record($carrier, $action, $orderId, $method, $url, $requestBody, null, self::describeThrowable($e), self::elapsedMs($start));

            throw $e;
        }

        $responseBody = null;

        try {
            $responseBody = $describe ? $describe($result) : $result;
        } catch (Throwable $e) {
            $responseBody = '[kon resultaat niet beschrijven: ' . $e->getMessage() . ']';
        }

        self::record($carrier, $action, $orderId, $method, $url, $requestBody, $successStatus, $responseBody, self::elapsedMs($start));

        return $result;
    }

    /**
     * Writes one log row. Never throws: a broken log table must not break a
     * label run.
     */
    public static function record(
        string $carrier,
        string $action,
        ?int $orderId,
        string $method,
        string $url,
        mixed $requestBody,
        ?int $status,
        mixed $responseBody,
        ?int $durationMs,
    ): ?CarrierApiLog {
        if (! self::$enabled) {
            return null;
        }

        try {
            return CarrierApiLog::create([
                'carrier' => mb_substr($carrier, 0, 50),
                'action' => mb_substr($action, 0, 80),
                'order_id' => $orderId,
                'method' => mb_substr(strtoupper($method), 0, 10),
                'url' => self::maskUrl($url),
                'request_body' => self::normalizeBody($requestBody),
                'response_status' => $status,
                'response_body' => self::normalizeBody($responseBody),
                'duration_ms' => $durationMs,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            rescue(fn () => Log::warning('Carrier API log could not be written', [
                'carrier' => $carrier,
                'action' => $action,
                'message' => $e->getMessage(),
            ]), null, false);

            return null;
        }
    }

    public static function maskUrl(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['query'])) {
            return $url;
        }

        parse_str($parts['query'], $query);
        $masked = false;
        foreach ($query as $key => $value) {
            if (in_array(strtolower((string) $key), self::SECRET_QUERY_KEYS, true)) {
                $query[$key] = '***';
                $masked = true;
            }
        }

        if (! $masked) {
            return $url;
        }

        $base = strstr($url, '?', true) ?: $url;
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $base . '?' . http_build_query($query) . $fragment;
    }

    public static function normalizeBody(mixed $body): ?string
    {
        if ($body === null) {
            return null;
        }

        if (! is_string($body)) {
            $body = is_scalar($body)
                ? (string) $body
                : (string) json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }

        if ($body === '') {
            return '';
        }

        if (str_starts_with($body, '%PDF') || ! mb_check_encoding($body, 'UTF-8')) {
            return '[binaire inhoud, ' . strlen($body) . ' bytes]';
        }

        if (strlen($body) > self::MAX_BODY_BYTES) {
            return mb_strcut($body, 0, self::MAX_BODY_BYTES) . "\n[afgekapt, " . strlen($body) . ' bytes totaal]';
        }

        return $body;
    }

    private static function readRequestBody(RequestInterface $request): ?string
    {
        try {
            $stream = $request->getBody();
            if ($stream->isSeekable()) {
                $stream->rewind();
            }
            $contents = $stream->getContents();
            if ($stream->isSeekable()) {
                $stream->rewind();
            }

            return $contents;
        } catch (Throwable) {
            return null;
        }
    }

    private static function readResponseBody(ResponseInterface $response): ?string
    {
        try {
            $stream = $response->getBody();
            if (! $stream->isSeekable()) {
                return '[stream, niet gelezen]';
            }
            $stream->rewind();
            $contents = $stream->getContents();
            $stream->rewind();

            return $contents;
        } catch (Throwable) {
            return null;
        }
    }

    private static function describeThrowable(Throwable $e): string
    {
        return class_basename($e) . ': ' . $e->getMessage();
    }

    private static function elapsedMs(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }
}
