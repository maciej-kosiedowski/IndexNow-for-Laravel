<?php

declare(strict_types=1);

namespace SlimAD\IndexNow\Laravel\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use SlimAD\IndexNow\Laravel\Exceptions\IndexNowConfigurationException;
use SlimAD\IndexNow\Laravel\IndexNowManager;
use SlimAD\IndexNow\Laravel\Tests\Support\Page;
use SlimAD\IndexNow\Laravel\Tests\Support\Post;
use SlimAD\IndexNow\Laravel\Tests\TestCase;
use SlimAD\IndexNow\ValueObject\Url;

final class SubmitsToIndexNowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('posts', static function (Blueprint $table): void {
            $table->id();
            $table->string('slug');
            $table->boolean('published')->default(false);
            $table->timestamps();
        });

        Schema::create('pages', static function (Blueprint $table): void {
            $table->id();
            $table->string('slug');
            $table->timestamps();
        });
    }

    /**
     * @return list<string>
     */
    private function pending(): array
    {
        return array_values(array_map(
            static fn (Url $url): string => $url->value,
            $this->container()->make(IndexNowManager::class)->store()->all(),
        ));
    }

    public function test_creating_a_model_queues_its_urls(): void
    {
        Post::create(['slug' => 'hello-world', 'published' => true]);

        self::assertSame(
            ['https://example.com/blog/hello-world', 'https://example.com/blog'],
            $this->pending(),
        );
    }

    public function test_updating_a_model_queues_its_urls(): void
    {
        $post = Post::create(['slug' => 'hello-world', 'published' => true]);

        $this->container()->make(IndexNowManager::class)->clear();

        $post->update(['slug' => 'hello-again']);

        self::assertSame(
            ['https://example.com/blog/hello-again', 'https://example.com/blog'],
            $this->pending(),
        );
    }

    public function test_deleting_a_model_queues_its_urls(): void
    {
        $post = Post::create(['slug' => 'hello-world', 'published' => true]);

        $this->container()->make(IndexNowManager::class)->clear();

        $post->delete();

        self::assertSame(
            ['https://example.com/blog/hello-world', 'https://example.com/blog'],
            $this->pending(),
        );
    }

    public function test_a_model_can_opt_out_by_returning_no_urls(): void
    {
        Post::create(['slug' => 'draft', 'published' => false]);

        self::assertSame([], $this->pending());
    }

    public function test_submit_to_index_now_returns_the_number_of_queued_urls(): void
    {
        $post = Post::create(['slug' => 'hello-world', 'published' => true]);

        $this->container()->make(IndexNowManager::class)->clear();

        self::assertSame(2, $post->submitToIndexNow());
    }

    public function test_nothing_is_queued_while_the_package_is_disabled(): void
    {
        $this->config()->set('indexnow.enabled', false);
        $this->container()->forgetInstance(IndexNowManager::class);

        Post::create(['slug' => 'hello-world', 'published' => true]);

        self::assertSame([], $this->pending());
    }

    public function test_a_model_without_the_contract_is_rejected(): void
    {
        $this->expectException(IndexNowConfigurationException::class);
        $this->expectExceptionMessage('uses the SubmitsToIndexNow trait but does not implement');

        Page::create(['slug' => 'about']);
    }
}
