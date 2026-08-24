<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use SlimAD\IndexNow\Laravel\Concerns\SubmitsToIndexNow;

/**
 * A model that forgets to implement ProvidesIndexNowUrls.
 *
 * @property string $slug
 */
final class Page extends Model
{
    use SubmitsToIndexNow;

    /** @var string */
    protected $table = 'pages';

    /** @var list<string> */
    protected $fillable = ['slug'];
}
