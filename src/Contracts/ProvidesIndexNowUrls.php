<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Contracts;

interface ProvidesIndexNowUrls
{
    /**
     * Absolute URLs that should be submitted to IndexNow whenever this model is
     * saved or deleted.
     *
     * Return an empty iterable to skip the model (for example while it is still
     * a draft).
     *
     * @return iterable<int, string>
     */
    public function indexNowUrls(): iterable;
}
