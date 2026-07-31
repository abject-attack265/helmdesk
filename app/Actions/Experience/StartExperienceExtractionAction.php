<?php

namespace App\Actions\Experience;

use App\Data\Experience\ExperienceExtractionWindowData;
use App\Data\Experience\FormStartExperienceExtractionData;
use App\Enums\AiModelPurpose;
use App\Enums\ExperienceExtractionStatus;
use App\Enums\KnowledgeBaseCategory;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Enums\ReceptionLanguage;
use App\Exceptions\BusinessException;
use App\Jobs\Experience\ExtractInstanceExperienceJob;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ExperienceExtraction;
use App\Models\KnowledgeBase;
use App\Models\User;
use App\Services\AiRuntime\AiModelPool;
use App\Services\KnowledgeBase\KnowledgeBaseReferenceLock;
use App\Services\LocalePreference;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 从所选联系人已关闭的人工会话中创建经验提炼任务。
 */
class StartExperienceExtractionAction
{
    use AsAction;

    /** 经验提炼创建锁的有效秒数。 */
    private const int CREATION_LOCK_TTL_SECONDS = 60;

    /** 等待其他创建请求完成的最长秒数。 */
    private const int CREATION_LOCK_WAIT_SECONDS = 15;

    /**
     * 注入知识库引用锁和经验提炼使用的模型池。
     */
    public function __construct(
        private readonly AiModelPool $aiModelPool,
        private readonly KnowledgeBaseReferenceLock $referenceLock,
    ) {}

    /**
     * 校验联系人和会话后创建任务，并按操作者语言派发提炼作业。
     *
     * @param  list<string>  $contactIds
     */
    public function handle(KnowledgeBase $knowledgeBase, array $contactIds, ExperienceExtractionWindowData $window, User $actor): ExperienceExtraction
    {
        // 窗口是操作者本地日历日，须按其时区换算成 UTC 边界，与「创建提炼任务」页看到的会话集合保持一致。
        $timezone = $actor->resolvedTimezone();

        if (! $this->aiModelPool->hasUsable(AiModelPurpose::BackgroundTask)) {
            throw new BusinessException(__('experience.errors.no_background_model'));
        }

        $contactIds = array_values(array_unique($contactIds));

        $qualified = Contact::query()
            ->whereIn('id', $contactIds)
            ->whereHas('conversations', function (Builder $query) use ($window, $timezone): void {
                $query
                    ->whereNotNull('closed_at')
                    ->where('closed_at', '>=', $window->startsAt($timezone))
                    ->where('closed_at', '<', $window->endsAtExclusive($timezone))
                    ->whereHas('messages', function ($messageQuery): void {
                        $messageQuery->where('role', MessageRole::Teammate)
                            ->where('kind', MessageKind::Text)
                            ->whereNotNull('content')
                            ->whereNull('recalled_at');
                    });
            })
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();

        if (count($qualified) !== count($contactIds)) {
            throw new BusinessException(__('experience.errors.invalid_contact_selection'));
        }

        $conversations = $this->windowConversationsFor($window, $timezone, $qualified);

        if ($conversations->count() > FormStartExperienceExtractionData::MAX_CONVERSATIONS) {
            throw new BusinessException(__('experience.errors.too_many_conversations', [
                'max' => FormStartExperienceExtractionData::MAX_CONVERSATIONS,
                'count' => $conversations->count(),
            ]));
        }

        try {
            $extraction = Cache::lock(
                'experience-extraction:create',
                self::CREATION_LOCK_TTL_SECONDS,
            )->block(
                self::CREATION_LOCK_WAIT_SECONDS,
                function () use ($knowledgeBase, $actor, $conversations): ExperienceExtraction {
                    $hasRunning = ExperienceExtraction::query()
                        ->useWritePdo()
                        ->where('status', ExperienceExtractionStatus::Running)
                        ->exists();

                    if ($hasRunning) {
                        throw new BusinessException(__('experience.errors.extraction_already_running'));
                    }

                    try {
                        return $this->referenceLock->run(
                            [(string) $knowledgeBase->id],
                            function (Closure $refreshLock) use ($knowledgeBase, $actor, $conversations): ExperienceExtraction {
                                $currentKnowledgeBase = KnowledgeBase::query()
                                    ->useWritePdo()
                                    ->findOrFail($knowledgeBase->id);
                                $refreshLock();

                                return DB::transaction(function () use ($currentKnowledgeBase, $actor, $conversations): ExperienceExtraction {
                                    // 初始数量用于显示处理进度，完成后由任务写入实际使用的会话数。
                                    $extraction = ExperienceExtraction::query()->create([
                                        'knowledge_base_id' => $currentKnowledgeBase->id,
                                        'triggered_by_user_id' => $actor->id,
                                        'status' => ExperienceExtractionStatus::Running,
                                        'scanned_from' => $conversations->min('closed_at'),
                                        'scanned_until' => $conversations->max('closed_at'),
                                        'conversation_count' => $conversations->count(),
                                    ]);

                                    $now = now();
                                    $extraction->conversations()->attach(
                                        array_fill_keys(
                                            $conversations->pluck('id')->map(static fn ($id): string => (string) $id)->all(),
                                            ['created_at' => $now],
                                        ),
                                    );

                                    return $extraction;
                                });
                            },
                        );
                    } catch (LockTimeoutException $exception) {
                        Log::warning('经验提炼创建等待知识库操作超时。', [
                            'knowledge_base_id' => (string) $knowledgeBase->id,
                            'actor_user_id' => (string) $actor->id,
                        ]);

                        throw new BusinessException(
                            __('knowledge_base.messages.operation_busy'),
                            previous: $exception,
                        );
                    }
                },
            );
        } catch (LockTimeoutException $exception) {
            Log::warning('经验提炼创建等待其他创建请求超时。', [
                'knowledge_base_id' => (string) $knowledgeBase->id,
                'actor_user_id' => (string) $actor->id,
            ]);

            throw new BusinessException(
                __('experience.errors.extraction_already_running'),
                previous: $exception,
            );
        }

        $language = ReceptionLanguage::from(LocalePreference::normalizeFrontend($actor->locale));
        ExtractInstanceExperienceJob::dispatch((string) $extraction->id, $language)->afterCommit();

        return $extraction;
    }

    /**
     * 返回所选联系人在窗口内的全部已关闭会话。
     *
     * 没有人工消息的会话仍提供相邻提问上下文。
     *
     * @param  list<string>  $contactIds
     * @return Collection<int, Conversation>
     */
    private function windowConversationsFor(ExperienceExtractionWindowData $window, string $timezone, array $contactIds): Collection
    {
        return Conversation::query()
            ->whereIn('contact_id', $contactIds)
            ->whereNotNull('closed_at')
            ->where('closed_at', '>=', $window->startsAt($timezone))
            ->where('closed_at', '<', $window->endsAtExclusive($timezone))
            ->get(['id', 'closed_at']);
    }

    /**
     * 校验创建请求并返回经验提炼页。
     */
    public function asController(Request $request, string $knowledgeBase): RedirectResponse
    {
        $model = KnowledgeBase::query()
            ->where('category', KnowledgeBaseCategory::Qa)
            ->findOrFail($knowledgeBase);

        $data = FormStartExperienceExtractionData::from($request);
        $window = ExperienceExtractionWindowData::normalize(
            Carbon::parse($data->from),
            Carbon::parse($data->to),
            $request->user()->resolvedTimezone(),
        );

        $this->handle($model, $data->contact_ids, $window, $request->user());

        return redirect()->route('app.manage.experience-extraction.index', [
            'knowledgeBase' => (string) $model->id,
        ]);
    }
}
