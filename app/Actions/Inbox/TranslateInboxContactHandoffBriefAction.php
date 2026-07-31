<?php

namespace App\Actions\Inbox;

use App\Actions\Translation\TranslateContactHandoffBriefAction;
use App\Data\CurrentUserContextData;
use App\Data\Inbox\FormQueueInboxContactHandoffBriefTranslationData;
use App\Jobs\Translation\TranslateInboxContactHandoffBriefJob;
use App\Models\Contact;
use App\Services\Translation\TranslationProviderPool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 校验收件箱联系人接手简报翻译请求并执行或排队翻译。
 */
class TranslateInboxContactHandoffBriefAction
{
    use AsAction;

    /**
     * 注入翻译供应商池和接手简报翻译用例。
     */
    public function __construct(
        private readonly TranslationProviderPool $translationPool,
        private readonly TranslateContactHandoffBriefAction $translateAction,
    ) {}

    /**
     * 同步翻译联系人接手简报并返回实际翻译条数。
     */
    public function handle(string $contactId, string $targetLocale, bool $force = false): int
    {
        $contact = Contact::query()->find($contactId);
        if ($contact === null) {
            throw new NotFoundHttpException;
        }

        if (! $this->translationPool->hasUsable()) {
            Log::info('联系人接手简报翻译已跳过：没有可用供应商', [
                'contact_id' => $contactId,
                'target_lang' => $targetLocale,
            ]);

            return 0;
        }

        return $this->translateAction->handle($contact, $targetLocale, $force)->isTranslated() ? 1 : 0;
    }

    /**
     * 校验请求并派发联系人接手简报翻译任务。
     */
    public function asController(Request $request, string $contactId): JsonResponse
    {
        CurrentUserContextData::fromRequest($request);
        $data = FormQueueInboxContactHandoffBriefTranslationData::from($request);

        TranslateInboxContactHandoffBriefJob::dispatch(
            $contactId,
            $data->target_locale,
            $data->force,
        )->afterCommit();

        return response()->json(['queued' => true]);
    }
}
