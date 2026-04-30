<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

class CreateAgentLogsTable extends Migration
{
    public function up(): void
    {
        Schema::create('agent_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('article_id')->nullable()->constrained('articles')->onDelete('set null');
            $table->string('agent_name', 100);
            $table->string('input_summary', 500)->nullable()->default('');
            $table->text('output_summary')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->enum('status', ['running', 'success', 'failed'])->default('running');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('article_id');
            $table->index('agent_name');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_logs');
    }
}
