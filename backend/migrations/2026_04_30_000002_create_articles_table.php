<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

class CreateArticlesTable extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('title', 500)->nullable()->default('');
            $table->string('topic', 500)->nullable()->default('');
            $table->string('style', 100)->nullable()->default('');
            $table->json('outline')->nullable();
            $table->text('content')->nullable();
            $table->json('images')->nullable();
            $table->enum('status', ['draft', 'title_selected', 'outline_confirmed', 'generating', 'completed', 'failed'])->default('draft');
            $table->unsignedInteger('word_count')->default(0);
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
}
