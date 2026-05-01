<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class AgentLog extends Model
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_DEGRADED = 'degraded';
    public const STATUS_FAILED = 'failed';

    protected ?string $table = 'agent_logs';

    protected array $fillable = [
        'user_id',
        'article_id',
        'agent_name',
        'input_summary',
        'output_summary',
        'duration_ms',
        'status',
        'error_message',
    ];

    protected array $casts = [
        'article_id' => 'integer',
        'user_id' => 'integer',
        'duration_ms' => 'integer',
    ];

    public function user(): \Hyperf\Database\Model\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function article(): \Hyperf\Database\Model\Relations\BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
