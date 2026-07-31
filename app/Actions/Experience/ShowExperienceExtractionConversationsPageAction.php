<?php

namespace App\Actions\Experience;

use App\Actions\KnowledgeBase\BuildKnowledgeBaseSidebarDataAction;
use App\Data\Experience\ExperienceExtractionData;
use App\Data\Experience\ListExtractableConversationItemData;
use App\Data\Experience\ShowExperienceExtractionConversationsPagePropsData;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Models\Conversation;
use App\Models\ExperienceExtraction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 渲染「提炼任务会话清单」页：只读展示该任务消费的会话，供逐个打开详情抽屉核对。
 */
class ShowExperienceExtractionConversationsPageAction
{
    use AsAction;

    /**
     * 组装任务与其会话清单 props。
     */
    public function handle(ExperienceExtraction $extraction): ShowExperienceExtractionConversationsPagePropsData
    {
        $conversations = $extraction->conversations()
            ->with('contact')
            ->withCount(['messages as teammate_messages_count' => function ($q): void {
                $q->where('role', MessageRole::Teammate)
                    ->where('kind', MessageKind::Text)
                    ->whereNotNull('content')
                    ->whereNull('recalled_at');
            }])
            ->orderByDesc('closed_at')
            ->get()
            ->map(static fn (Conversation $c): ListExtractableConversationItemData => ListExtractableConversationItemData::fromModel($c, true))
            ->all();

        return new ShowExperienceExtractionConversationsPagePropsData(
            sidebar: BuildKnowledgeBaseSidebarDataAction::run(),
            extraction: ExperienceExtractionData::fromModel($extraction),
            conversations: $conversations,
        );
    }

    /**
     * 解析当前应用下的任务并渲染页面。
     */
    public function asController(Request $request, string $extraction): Response
    {
        $model = ExperienceExtraction::query()

            ->with('knowledgeBase')
            ->findOrFail($extraction);

        return Inertia::render('experiences/Conversations', $this->handle($model));
    }
}
