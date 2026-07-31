<?php

namespace App\Actions\Conversation;

use App\Enums\TagSource;
use App\Models\Conversation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 把一组 AI 标签建议落到会话上，并守住人工干预边界。
 *
 * 规则：
 * - 人工抑制过（removed_at）的标签：跳过，绝不复打。
 * - 人工手动标签（source=manual）：不动。
 * - 已存在的 AI 标签：刷新置信度/依据。
 * - 不存在的：插入 AI 标签。
 * - AI 永远只增不撤，删除与抑制只允许人工处理。
 */
class ApplyConversationTagSuggestionsAction
{
    use AsAction;

    /**
     * 将 AI 标签建议对账落库，守住人工抑制与人工手动标签边界。
     *
     * @param  list<array{tag_id: string, confidence: float|null, reason: string|null, based_on_seq_no: int|null}>  $suggestions
     */
    public function handle(Conversation $conversation, array $suggestions): void
    {
        $existing = DB::table('conversation_tag_assignments')
            ->where('conversation_id', $conversation->id)
            ->get()
            ->keyBy('tag_id');

        foreach ($suggestions as $suggestion) {
            $tagId = $suggestion['tag_id'];
            $row = $existing->get($tagId);

            if ($row !== null) {
                // 人工抑制过或人工手动打的标签都不被 AI 覆盖。
                if ($row->removed_at !== null || $row->source === TagSource::Manual->value) {
                    continue;
                }

                DB::table('conversation_tag_assignments')
                    ->where('conversation_id', $conversation->id)
                    ->where('tag_id', $tagId)
                    ->update([
                        'confidence' => $suggestion['confidence'] ?? null,
                        'reason' => $suggestion['reason'] ?? null,
                        'based_on_seq_no' => $suggestion['based_on_seq_no'] ?? null,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            DB::table('conversation_tag_assignments')->insert([
                'conversation_id' => $conversation->id,
                'tag_id' => $tagId,
                'source' => TagSource::Ai->value,
                'confidence' => $suggestion['confidence'] ?? null,
                'reason' => $suggestion['reason'] ?? null,
                'assigned_by_user_id' => null,
                'based_on_seq_no' => $suggestion['based_on_seq_no'] ?? null,
                'removed_at' => null,
                'removed_by_user_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
