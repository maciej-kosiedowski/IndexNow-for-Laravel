<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Client;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use SlimAD\IndexNow\Client\IndexNowClient;
use SlimAD\IndexNow\Dto\SubmitRequest;
use SlimAD\IndexNow\Exception\SubmitFailedException;
use SlimAD\IndexNow\Laravel\Config\HttpOptions;

/**
 * Submits through Laravel's HTTP client, so timeouts, retries and `Http::fake()`
 * all behave the way the rest of the application expects.
 */
final readonly class LaravelHttpIndexNowClient implements IndexNowClient
{
    public function __construct(
        private Factory $http,
        private HttpOptions $options,
    ) {}

    public function submit(SubmitRequest $request): void
    {
        try {
            $response = $this->http
                ->asJson()
                ->accept('application/json')
                ->withUserAgent($this->options->userAgent)
                ->timeout($this->options->timeout)
                ->connectTimeout($this->options->connectTimeout)
                // throw: false keeps a rejected response a response, so the failure
                // is reported as a SubmitFailedException rather than a Laravel one.
                ->retry($this->options->retries, $this->options->retryDelay, throw: false)
                ->post($request->endpoint, $request->toArray());
        } catch (ConnectionException $exception) {
            throw SubmitFailedException::transportError($request->endpoint, $exception);
        }

        if ($response->successful()) {
            return;
        }

        throw SubmitFailedException::rejected(
            $request->endpoint,
            $response->status(),
            $response->body(),
        );
    }
}
