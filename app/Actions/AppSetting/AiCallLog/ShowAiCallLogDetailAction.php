<?php

namespace App\Actions\AppSetting\AiCallLog;

use App\Data\AiCallLog\AiCallLogDetailData;
use App\Enums\AiCallPurpose;
use App\Models\AiCallLog;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 返回 AI 调用日志详情及其关联的会话消息。
 */
class ShowAiCallLogDetailAction
{
    use AsAction;

    /**
     * 按日志 ID 获取详情数据。
     */
    public function handle(string $id): AiCallLogDetailData
    {
        $log = AiCallLog::query()->find($id);
        if ($log === null) {
            throw new NotFoundHttpException;
        }

        return AiCallLogDetailData::fromLog(
            $log,
            $this->linkedConversationMessages($log),
            $this->visitorName($log),
        );
    }

    /**
     * 获取接待日志所属联系人的显示名称。
     */
    private function visitorName(AiCallLog $log): ?string
    {
        if ($log->purpose !== AiCallPurpose::ReceptionReply || $log->conversation_id === null) {
            return null;
        }

        $name = Conversation::query()
            ->whereKey($log->conversation_id)
            ->with('contact')
            ->first()
            ?->contact
            ?->name;

        return is_string($name) && trim($name) !== '' ? $name : null;
    }

    /**
     * 获取接待日志关联的会话消息和附件。
     *
     * @return Collection<int, ConversationMessage>
     */
    private function linkedConversationMessages(AiCallLog $log): Collection
    {
        if ($log->purpose !== AiCallPurpose::ReceptionReply || $log->conversation_id === null) {
            return new Collection;
        }

        return ConversationMessage::query()
            ->where('conversation_id', $log->conversation_id)
            ->with('attachments.storageProfile')
            ->orderBy('seq_no')
            ->get();
    }

    /**
     * 返回调用日志详情 JSON。
     */
    public function asController(string $id): JsonResponse
    {
        return response()->json($this->handle($id)->toArray());
    }
}
