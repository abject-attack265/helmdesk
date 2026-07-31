<?php

namespace App\Actions\AppSetting\AiCallLog;

use App\Data\AiCallLog\AiCallLogFilterData;
use App\Data\AiCallLog\ListAiCallLogItemData;
use App\Data\AiCallLog\ShowAiCallLogListPagePropsData;
use App\Data\EnumOptionData;
use App\Data\SimplePaginationData;
use App\Enums\AiCallPurpose;
use App\Models\AiCallLog;
use App\Models\ConversationMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 渲染应用设置中的「AI 调用日志」列表页：一行=一个 AI 会话，分页 + 用途/状态筛选 +
 * 搜索支持定位字段精确匹配和 search_text 内容子串匹配。
 */
class ShowAiCallLogListAction
{
    use AsAction;

    /**
     * 分页查询调用日志并组装列表 props（不加载 messages 等大 JSON 列）。
     */
    public function handle(AiCallLogFilterData $filters, int $page, int $perPage): ShowAiCallLogListPagePropsData
    {
        $perPage = max(1, min($perPage, 100));
        $page = max(1, $page);

        $paginator = AiCallLog::query()
            ->select([
                'id', 'created_at', 'last_at', 'purpose', 'conversation_id',
                'conversation_message_id', 'contact_id', 'model_name', 'status',
                'input_tokens', 'output_tokens', 'turn_count', 'reply_preview',
            ])
            ->when($filters->purpose !== null, fn (Builder $q) => $q->where('purpose', $filters->purpose))
            ->when($filters->status !== null, fn (Builder $q) => $q->where('status', $filters->status))
            ->when($filters->search !== null && $filters->search !== '', function (Builder $q) use ($filters): void {
                $needle = (string) $filters->search;
                $escaped = addcslashes($needle, '\\%_');
                // 搜的是某条会话消息 ID 时，解析到其所属会话再匹配（接待日志按会话合并，行上只有 conversation_id）。
                $conversationOfMessage = ConversationMessage::query()->whereKey($needle)->value('conversation_id');
                $q->where(function (Builder $w) use ($needle, $escaped, $conversationOfMessage): void {
                    $w->where('id', $needle)
                        ->orWhere('conversation_id', $needle)
                        ->orWhere('conversation_message_id', $needle)
                        ->orWhere('contact_id', $needle)
                        ->orWhereRaw("search_text LIKE ? ESCAPE '\\'", ["%{$escaped}%"]);
                    if (is_string($conversationOfMessage) && $conversationOfMessage !== '') {
                        $w->orWhere('conversation_id', $conversationOfMessage);
                    }
                });
            })
            ->orderByDesc('last_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $logs = $paginator->getCollection()
            ->map(fn (AiCallLog $log) => ListAiCallLogItemData::fromModel($log))
            ->all();

        return new ShowAiCallLogListPagePropsData(
            logs: $logs,
            pagination: SimplePaginationData::fromPaginator($paginator),
            filters: $filters,
            purpose_options: EnumOptionData::fromCases(AiCallPurpose::cases()),
            status_options: [
                new EnumOptionData(value: 'success', label: __('ai.call_status.success')),
                new EnumOptionData(value: 'error', label: __('ai.call_status.error')),
            ],
        );
    }

    /**
     * 解析请求里的筛选与分页参数并渲染列表页。
     */
    public function asController(Request $request): Response
    {
        $purpose = $request->query('purpose');
        $status = $request->query('status');
        $search = $request->query('search');

        $filters = new AiCallLogFilterData(
            purpose: AiCallPurpose::tryFrom((string) $purpose)?->value,
            status: in_array($status, ['success', 'error'], true) ? $status : null,
            search: is_string($search) && trim($search) !== '' ? trim($search) : null,
        );

        return Inertia::render('appSettings/aiCallLog/List', $this->handle(
            $filters,
            (int) $request->query('page', 1),
            (int) $request->query('per_page', SimplePaginationData::DEFAULT_PER_PAGE),
        )->toArray());
    }
}
