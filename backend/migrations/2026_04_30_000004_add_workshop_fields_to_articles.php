<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

class AddWorkshopFieldsToArticles extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('selected_title', 500)->nullable()->after('title');
            $table->json('generated_titles')->nullable()->after('selected_title');
            $table->text('title_supplement')->nullable()->after('generated_titles');
            $table->string('ai_model', 100)->nullable()->after('title_supplement');
        });

        // 扩展 status 枚举以覆盖 workshop 的 9 种状态
        Schema::getConnection()->statement(
            "ALTER TABLE articles MODIFY COLUMN status ENUM("
                . "'draft','title_generating','title_selecting','title_selected',"
                . "'outline_generating','outline_editing','outline_confirmed',"
                . "'content_generating','generating','image_analyzing','image_generating',"
                . "'completed','failed'"
                . ") NOT NULL DEFAULT 'draft'"
        );
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['selected_title', 'generated_titles', 'title_supplement', 'ai_model']);
        });

        Schema::getConnection()->statement(
            "ALTER TABLE articles MODIFY COLUMN status ENUM("
                . "'draft','title_selected','outline_confirmed','generating','completed','failed'"
                . ") NOT NULL DEFAULT 'draft'"
        );
    }
}
