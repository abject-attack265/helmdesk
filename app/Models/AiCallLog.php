<?php

namespace App\Models;

use App\Enums\AiCallPurpose;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property Carbon $created_at 会话首轮请求发起时间
 * @property Carbon $last_at 最近一轮活动时间（列表排序依据）
 * @property AiCallPurpose $purpose 调用用途：reception_reply / conversation_summary 等
 * @property string|null $conversation_id 关联会话（接待场景的合并键与最通用定位钥匙）
 * @property string|null $conversation_message_id 针对单条消息的调用所指向的消息
 * @property string|null $contact_id 联系人维度调用所指向的联系人
 * @property string $model_name 最近一轮所用模型名快照
 * @property string $status success / error：任一条目失败即 error
 * @property string|null $error_message 首个出错条目的错误信息
 * @property int $duration_ms 各条目耗时之和（毫秒）
 * @property int $input_tokens 各条目输入 token 之和
 * @property int $output_tokens 各条目输出 token 之和
 * @property int $turn_count 轮次数（按 turn 计）
 * @property array $system_prompts 最近一轮的 system prompt 快照列表（接待含历史上下文第二条）
 * @property array $available_tools 最近一轮提供给模型的工具定义快照：[{name, description}]
 * @property array $messages 对话时间线：user / assistant 条目，assistant 内按序内嵌 text 与 tool_call 分段
 * @property string $reply_preview 最近一条 AI 回复的纯文本预览（列表用）
 * @property string $search_text 可检索文本：用户输入 + 模型输出 + 工具返回 + turn ID
 */
class AiCallLog extends Model
{
    /**
     * 保存模型调用的输入、输出、工具执行、用量与耗时。
     *
     * 接待调用按会话聚合多轮记录，其余调用每次运行独立成行。
     * messages 条目结构：
     * - user：{role, run_id, turn_id?, created_at, content, media: [{type, url?}], conversation_message_ids}
     * - assistant：{role, run_id, turn_id?, created_at, segments: [{type: text|tool_call, ...}],
     *   model_name, input_tokens, output_tokens, duration_ms, status, error_message}
     */
    use HasUlids, MassPrunable;

    /**
     * 调用日志保留天数；超期由每日 model:prune 清理。
     */
    public const int RETENTION_DAYS = 7;

    protected $table = 'ai_call_logs';

    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'last_at' => 'datetime',
            'purpose' => AiCallPurpose::class,
            'duration_ms' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'turn_count' => 'integer',
            'system_prompts' => 'array',
            'available_tools' => 'array',
            'messages' => 'array',
        ];
    }

    /**
     * 保存前根据消息时间线刷新汇总字段。
     */
    protected static function booted(): void
    {
        static::saving(function (AiCallLog $log): void {
            $log->refreshDerivedColumns();
        });
    }

    /**
     * 保留窗口外的调用日志（创建超过 RETENTION_DAYS 天）纳入清理。
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }

    /**
     * 由 messages 派生汇总列：状态 / 错误信息 / token 与耗时合计 / 轮次数 / 回复预览 / 检索文本 / 最近活动时间。
     */
    private function refreshDerivedColumns(): void
    {
        $entries = $this->messages ?? [];

        $inputTokens = 0;
        $outputTokens = 0;
        $durationMs = 0;
        $firstError = null;
        $turnIds = [];
        $assistantCount = 0;
        $preview = '';
        $searchParts = [];
        $lastAt = null;

        foreach ($entries as $entry) {
            $inputTokens += (int) ($entry['input_tokens'] ?? 0);
            $outputTokens += (int) ($entry['output_tokens'] ?? 0);
            $durationMs += (int) ($entry['duration_ms'] ?? 0);

            if (($entry['status'] ?? null) === 'error' && $firstError === null) {
                $firstError = (string) ($entry['error_message'] ?? '') ?: '未知错误';
            }

            $turnId = $entry['turn_id'] ?? null;
            if (is_string($turnId) && $turnId !== '') {
                $turnIds[$turnId] = true;
                $searchParts[] = $turnId;
            }

            $createdAt = $entry['created_at'] ?? null;
            if (is_string($createdAt) && ($lastAt === null || $createdAt > $lastAt)) {
                $lastAt = $createdAt;
            }

            if (($entry['role'] ?? null) === 'assistant') {
                $assistantCount++;
                $text = self::assistantText($entry);
                if ($text !== '') {
                    $preview = $text;
                }
                $searchParts[] = $text;
                foreach ($entry['segments'] ?? [] as $segment) {
                    if (($segment['type'] ?? null) === 'tool_call') {
                        $searchParts[] = (string) ($segment['result'] ?? '');
                    }
                }
            } else {
                $searchParts[] = (string) ($entry['content'] ?? '');
            }
        }

        $this->status = $firstError === null ? 'success' : 'error';
        $this->error_message = $firstError;
        $this->input_tokens = $inputTokens;
        $this->output_tokens = $outputTokens;
        $this->duration_ms = $durationMs;
        $this->turn_count = $turnIds === [] ? $assistantCount : count($turnIds);
        $this->reply_preview = Str::limit($preview, 300);
        $this->search_text = trim(implode("\n", array_filter($searchParts)));
        $this->last_at = $lastAt !== null ? Carbon::parse($lastAt) : ($this->created_at ?? now());
        $this->created_at ??= now();
    }

    /**
     * 一条 assistant 条目的纯文本（各 text 分段拼接）。
     */
    public static function assistantText(array $entry): string
    {
        $parts = [];
        foreach ($entry['segments'] ?? [] as $segment) {
            if (($segment['type'] ?? null) === 'text') {
                $parts[] = (string) ($segment['content'] ?? '');
            }
        }

        return trim(implode("\n", array_filter($parts)));
    }
}
