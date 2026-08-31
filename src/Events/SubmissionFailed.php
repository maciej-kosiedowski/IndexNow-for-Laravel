<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Events;

use SlimAD\IndexNow\Exception\SubmitFailedException;

final readonly class SubmissionFailed
{
    public function __construct(public readonly SubmitFailedException $failure) {}
}
