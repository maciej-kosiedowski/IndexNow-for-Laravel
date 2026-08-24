<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Events;

use SlimAD\IndexNow\Job\SubmitJobResult;

final class SubmissionCompleted
{
    public function __construct(public readonly SubmitJobResult $result) {}
}
