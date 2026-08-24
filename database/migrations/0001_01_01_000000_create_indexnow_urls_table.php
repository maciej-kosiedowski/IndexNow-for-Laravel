<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->schema()->create($this->table(), function (Blueprint $table): void {
            $table->id();
            $table->char('url_hash', 64)->unique();
            $table->text('url');
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists($this->table());
    }

    private function schema(): Illuminate\Database\Schema\Builder
    {
        $connection = config('indexnow.stores.database.connection');

        return Schema::connection(is_string($connection) && $connection !== '' ? $connection : null);
    }

    private function table(): string
    {
        $table = config('indexnow.stores.database.table');

        return is_string($table) && $table !== '' ? $table : 'indexnow_urls';
    }
};
