<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Events;

final readonly class UrlsQueued
{
    /**
     * @param  list<string>  $urls
     */
    public function __construct(public readonly array $urls) {}
}
