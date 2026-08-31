<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use SlimAD\IndexNow\Laravel\Concerns\SubmitsToIndexNow;
use SlimAD\IndexNow\Laravel\Contracts\ProvidesIndexNowUrls;

/**
 * @property string $slug
 * @property bool $published
 */
final class Post extends Model implements ProvidesIndexNowUrls
{
    use SubmitsToIndexNow;

    /** @var string */
    protected $table = 'posts';

    /** @var list<string> */
    protected $fillable = ['slug', 'published'];

    /** @var array<string, string> */
    protected $casts = ['published' => 'boolean'];

    /**
     * @return iterable<int, string>
     */
    public function indexNowUrls(): iterable
    {
        if ($this->published !== true) {
            return [];
        }

        return [
            'https://example.com/blog/'.$this->slug,
            'https://example.com/blog',
        ];
    }
}
