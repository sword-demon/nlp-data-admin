<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class Article extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_TITLE_SELECTED = 'title_selected';
    public const STATUS_OUTLINE_CONFIRMED = 'outline_confirmed';
    public const STATUS_GENERATING = 'generating';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected ?string $table = 'articles';

    protected array $fillable = [
        'user_id',
        'title',
        'selected_title',
        'generated_titles',
        'title_supplement',
        'topic',
        'style',
        'outline',
        'content',
        'images',
        'status',
        'word_count',
        'ai_model',
    ];

    protected array $casts = [
        'outline' => 'json',
        'generated_titles' => 'json',
        'images' => 'json',
        'word_count' => 'integer',
    ];

    public function user(): \Hyperf\Database\Model\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agentLogs(): \Hyperf\Database\Model\Relations\HasMany
    {
        return $this->hasMany(AgentLog::class);
    }
}
