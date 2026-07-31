<?php

namespace App\Actions\Inbox;

use App\Actions\Contact\ShowContactConversationTimelineAction;
use App\Data\CurrentUserContextData;
use App\Enums\ConversationTimelineEntryType;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 加载收件箱联系人会话时间线窗口。
 */
class LoadInboxContactTimelineAction
{
    use AsAction;

    /**
     * 创建联系人时间线窗口加载动作。
     */
    public function __construct(
        private readonly ShowContactConversationTimelineAction $timeline,
    ) {}

    /**
     * 处理收件箱联系人时间线窗口请求。
     */
    public function asController(Request $request, string $contactId): JsonResponse
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $contact = Contact::query()
            ->findOrFail($contactId);
        $hasBefore = $request->filled('before');
        $hasAfter = $request->filled('after');
        $hasAnchor = $request->filled('anchor_type') || $request->filled('anchor_id');

        $validated = $request->validate([
            'before' => [
                'nullable',
                'string',
                Rule::prohibitedIf($hasAfter || $hasAnchor),
            ],
            'after' => [
                'nullable',
                'string',
                Rule::prohibitedIf($hasBefore || $hasAnchor),
            ],
            'anchor_type' => [
                'nullable',
                'required_with:anchor_id',
                Rule::prohibitedIf($hasBefore || $hasAfter),
                Rule::in(array_column(ConversationTimelineEntryType::cases(), 'value')),
            ],
            'anchor_id' => [
                'nullable',
                'required_with:anchor_type',
                Rule::prohibitedIf($hasBefore || $hasAfter),
                'string',
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $timeline = $this->timeline->handle(
            contact: $contact,
            perPage: (int) ($validated['per_page'] ?? 50),
            viewer: $request->user(),
            before: $validated['before'] ?? null,
            after: $validated['after'] ?? null,
            anchorType: isset($validated['anchor_type']) ? ConversationTimelineEntryType::from($validated['anchor_type']) : null,
            anchorId: $validated['anchor_id'] ?? null,
        );

        return response()->json(['timeline' => $timeline]);
    }
}
