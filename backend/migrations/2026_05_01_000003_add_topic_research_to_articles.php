<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

/**
 * Phase 3.5 — 选题研究 Agent：
 *   1) articles 表新增 research_data JSON 列，保存 TopicResearchAgent 产出
 *   2) status ENUM 追加 'topic_researching'，对齐 WorkshopState::TOPIC_RESEARCHING
 */
class AddTopicResearchToArticles extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->json('research_data')->nullable()->after('title_supplement')
                ->comment('TopicResearchAgent 产出：{summary, sources}；降级时为 NULL');
        });

        Schema::getConnection()->statement(
            "ALTER TABLE articles MODIFY COLUMN status ENUM("
                . "'draft','topic_researching',"
                . "'title_generating','title_selecting','title_selected',"
                . "'outline_generating','outline_editing','outline_confirmed',"
                . "'content_generating','generating','image_analyzing','image_generating',"
                . "'completed','failed'"
                . ") NOT NULL DEFAULT 'draft'"
        );
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('research_data');
        });

        Schema::getConnection()->statement(
            "ALTER TABLE articles MODIFY COLUMN status ENUM("
                . "'draft',"
                . "'title_generating','title_selecting','title_selected',"
                . "'outline_generating','outline_editing','outline_confirmed',"
                . "'content_generating','generating','image_analyzing','image_generating',"
                . "'completed','failed'"
                . ") NOT NULL DEFAULT 'draft'"
        );
    }
}
