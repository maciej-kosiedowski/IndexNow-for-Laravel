<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Http\Controllers;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves the IndexNow key as text/plain so no static file has to be dropped into
 * `public/`.
 */
final class KeyFileController
{
    public function __invoke(Repository $config): Response
    {
        $key = $config->get('indexnow.key');

        if (! \is_string($key) || $key === '') {
            throw new NotFoundHttpException;
        }

        return new Response($key, Response::HTTP_OK, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
