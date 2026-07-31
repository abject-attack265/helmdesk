<?php

namespace App\Actions\Reception;

use App\Data\Reception\Runtime\ReceptionToolEventContextData;
use App\Data\Reception\Runtime\ReceptionToolEventDefinitionData;
use App\Enums\ConversationEventType;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use NeuronAI\Tools\ToolInterface;

/**
 * 记录可展示的 AI 接待工具调用事件。
 */
class RecordReceptionToolCalledAction
{
    use AsAction;

    /**
     * 注入接待实时通知器。
     */
    public function __construct(
        private readonly ReceptionRealtimeNotifier $realtimeNotifier,
    ) {}

    /**
     * 保存工具短名称和执行状态。
     */
    public function handle(
        ReceptionToolEventContextData $context,
        ReceptionToolEventDefinitionData $definition,
        ToolInterface $tool,
    ): ConversationEvent {
        $conversation = Conversation::query()->findOrFail($context->conversation_id);
        $status = $this->resultHasError($tool->getResult()) ? 'failed' : 'success';
        $payload = [
            'tool' => $definition->tool_name,
            'source_type' => $definition->source_type,
            'display_name' => $this->displayName($definition),
            'status' => $status,
            'turn_id' => $context->turn_id,
            'call_id' => $tool->getCallId(),
        ];

        $event = DB::transaction(fn (): ConversationEvent => ConversationEvent::query()->create([
            'conversation_id' => $context->conversation_id,
            'actor_user_id' => null,
            'type' => ConversationEventType::ReceptionToolCalled,
            'payload' => $payload,
        ]));

        // 事件提交后读取当前会话归属，供实时通知使用。
        $conversation->refresh();

        Log::info('[reception] 工具调用事件已记录', [
            'conversation_id' => $context->conversation_id,
            'event_id' => (string) $event->id,
            'turn_id' => $context->turn_id,
            'tool' => $definition->tool_name,
            'source_type' => $definition->source_type,
            'status' => $status,
            'call_id' => $tool->getCallId(),
        ]);

        $this->realtimeNotifier->appChanged('conversation_event_created', [
            'conversation_id' => $context->conversation_id,
            'contact_id' => $conversation->contact_id !== null ? (string) $conversation->contact_id : null,
            'event_id' => (string) $event->id,
        ]);

        return $event;
    }

    /**
     * 判断工具结果是否包含非空错误标记。
     */
    private function resultHasError(string $result): bool
    {
        $decoded = json_decode($result, true);

        return is_array($decoded) && (bool) ($decoded['error'] ?? false);
    }

    /**
     * 从工具说明提取客服时间线使用的短名称。
     */
    private function displayName(ReceptionToolEventDefinitionData $definition): string
    {
        $description = trim((string) $definition->description);
        if ($description === '') {
            return Str::headline($definition->tool_name);
        }

        $parts = preg_split('/[。.!！?？]/u', $description, 2);
        $displayName = trim((string) array_first($parts));

        return $displayName !== ''
            ? Str::limit($displayName, 40)
            : Str::headline($definition->tool_name);
    }
}
