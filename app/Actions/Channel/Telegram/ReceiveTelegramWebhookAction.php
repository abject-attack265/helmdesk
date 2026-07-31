<?php

namespace App\Actions\Channel\Telegram;

use App\Actions\Reception\AppendTelegramVisitorMediaAction;
use App\Actions\Reception\AppendTelegramVisitorMessageAction;
use App\Actions\Reception\SubmitConversationRatingAction;
use App\Actions\Reception\UpdateTelegramVisitorMessageAction;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationRatingScore;
use App\Enums\TelegramWebhookMode;
use App\Exceptions\BusinessException;
use App\Exceptions\TelegramApiException;
use App\Jobs\Telegram\ProcessTelegramInboundUpdateJob;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\TelegramInboundUpdate;
use App\Services\LocalePreference;
use App\Services\Reception\ReceptionPipelineDispatcher;
use App\Services\Telegram\TelegramBotApi;
use App\Services\Telegram\TelegramWebhookAuthenticator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use UnexpectedValueException;

/** 验证并记录 Telegram webhook，再处理已领取的入站 Update。 */
class ReceiveTelegramWebhookAction
{
    use AsAction;

    /** Telegram 在 setWebhook 时约定的校验头。 */
    private const string SECRET_HEADER = 'X-Telegram-Bot-Api-Secret-Token';

    /** 网关转发协议携带的业务用户标识。 */
    private const string GATEWAY_EXTERNAL_ID_HEADER = 'X-Gateway-External-Id';

    /** 网关转发协议携带的业务用户邮箱。 */
    private const string GATEWAY_EXTERNAL_EMAIL_HEADER = 'X-Gateway-External-Email';

    /** 创建 Telegram 入站 Update 接收与处理流程。 */
    public function __construct(
        private readonly TelegramWebhookAuthenticator $authenticator,
        private readonly TelegramBotApi $api,
        private readonly AppendTelegramVisitorMessageAction $appendText,
        private readonly AppendTelegramVisitorMediaAction $appendMedia,
        private readonly UpdateTelegramVisitorMessageAction $updateText,
        private readonly SyncGatewayContactIdentityAction $syncGatewayIdentity,
        private readonly ReceptionPipelineDispatcher $pipeline,
    ) {}

    /** 验证 Telegram webhook 并持久化原始 Update。 */
    public function asController(Request $request, string $code): JsonResponse
    {
        $secret = (string) $request->header(self::SECRET_HEADER, '');
        $channel = $this->authenticator->authenticate($code, $secret);
        $payload = $request->all();
        $providerUpdateId = $payload['update_id'] ?? null;
        if (! is_int($providerUpdateId) || $providerUpdateId < 0) {
            throw new UnexpectedValueException('Telegram Update 缺少有效的 update_id。');
        }

        $inbound = TelegramInboundUpdate::query()->firstOrCreate(
            [
                'channel_id' => $channel->id,
                'provider_update_id' => (string) $providerUpdateId,
            ],
            [
                'update_type' => $this->updateType($payload),
                'payload' => $payload,
                'gateway_external_id' => $this->headerOrNull($request, self::GATEWAY_EXTERNAL_ID_HEADER),
                'gateway_external_email' => $this->headerOrNull($request, self::GATEWAY_EXTERNAL_EMAIL_HEADER),
                'available_at' => now(),
            ],
        );
        if ($inbound->wasRecentlyCreated) {
            ProcessTelegramInboundUpdateJob::dispatch((string) $inbound->id)->afterCommit();
        }

        return response()->json(['ok' => true]);
    }

    /** 处理一条已领取的 Telegram 入站 Update。 */
    public function handle(TelegramInboundUpdate $inbound): void
    {
        $channel = $inbound->channel()->firstOrFail();
        $payload = $inbound->payload;

        $callbackQuery = $payload['callback_query'] ?? null;
        if (is_array($callbackQuery)) {
            $this->handleCallbackQuery($channel, $callbackQuery);

            return;
        }

        $editedMessage = $payload['edited_message'] ?? null;
        if (is_array($editedMessage)) {
            $this->handleEditedMessage($channel, $editedMessage);

            return;
        }

        $message = $payload['message'] ?? null;

        if (! is_array($message) || ! is_array($message['from'] ?? null) || ! is_array($message['chat'] ?? null)) {
            Log::info('Telegram 暂不处理的 Update 已记录。', [
                'inbound_update_id' => (string) $inbound->id,
                'provider_update_id' => $inbound->provider_update_id,
                'update_type' => $inbound->update_type,
            ]);

            return;
        }

        $from = $message['from'];
        $chat = $message['chat'];

        $chatId = (int) ($chat['id'] ?? 0);
        $userId = (int) ($from['id'] ?? 0);
        $messageId = (int) ($message['message_id'] ?? 0);
        if ($chatId === 0 || $userId <= 0 || $messageId <= 0) {
            throw new UnexpectedValueException('Telegram 消息缺少有效的 chat、from 或 message_id。');
        }
        $displayName = $this->composeDisplayName(
            $this->stringOrNull($from['first_name'] ?? null),
            $this->stringOrNull($from['last_name'] ?? null),
            $this->stringOrNull($from['username'] ?? null),
        );

        [$mediaKind, $fileId, $fileName, $mimeType] = $this->extractMedia($message);
        $caption = trim((string) ($message['caption'] ?? ''));

        if ($fileId !== '') {
            $result = $this->handleMedia(
                $channel, $chatId, $userId, $displayName, $messageId,
                $mediaKind, $fileId, $fileName, $mimeType, $caption !== '' ? $caption : null,
            );
            $this->syncGatewayIdentity($inbound, $channel, $result);
            $this->enqueueIfAiReply($result, $caption);

            return;
        }

        $text = trim((string) ($message['text'] ?? ''));
        if ($text === '') {
            Log::info('Telegram 暂不处理的消息类型已记录。', [
                'inbound_update_id' => (string) $inbound->id,
                'provider_update_id' => $inbound->provider_update_id,
            ]);

            return;
        }

        if ($this->isStartCommand($text)) {
            $settings = $channel->telegramSettings();
            $locale = LocalePreference::normalizeLaravel(
                $this->stringOrNull($from['language_code'] ?? null)
                    ?? $settings->default_visitor_locale->value,
            );

            $this->api->sendMessage(
                (string) $settings->bot_token,
                $chatId,
                (string) __('reception.telegram.start_message', [], $locale),
                $messageId,
            );

            return;
        }

        $result = $this->appendText->handle(
            $channel->code,
            (string) $userId,
            $displayName,
            $text,
            $messageId,
            $chatId,
            $this->stringOrNull($from['username'] ?? null),
            $this->stringOrNull($from['language_code'] ?? null),
            $this->boolOrNull($from['is_premium'] ?? null),
            $this->boolOrNull($from['is_bot'] ?? null),
            $this->stringOrNull($chat['type'] ?? null),
        );
        $this->syncGatewayIdentity($inbound, $channel, $result);
        $this->enqueueIfAiReply($result, $text);
    }

    /** 处理 Telegram 文本编辑并重新触发 AI 接待。 */
    private function handleEditedMessage(Channel $channel, array $editedMessage): void
    {
        $text = trim((string) ($editedMessage['text'] ?? ''));
        $telegramMessageId = (int) ($editedMessage['message_id'] ?? 0);

        if ($text === '' || $telegramMessageId === 0) {
            return;
        }

        $result = $this->updateText->handle($channel, $telegramMessageId, $text);

        if ($result['conversation'] !== null && $result['message'] !== null) {
            $this->enqueueIfAiReply($result, $text);
        }
    }

    /** 将网关协议中的外部用户身份同步到联系人。 */
    private function syncGatewayIdentity(TelegramInboundUpdate $inbound, Channel $channel, array $result): void
    {
        $settings = $channel->telegramSettings();
        if ($settings->webhook_mode !== TelegramWebhookMode::Gateway) {
            return;
        }

        $externalId = $inbound->gateway_external_id;
        if ($externalId === null) {
            return;
        }

        $conversation = $result['conversation'];
        $contact = $conversation->contact()->firstOrFail();

        $this->syncGatewayIdentity->handle(
            $channel,
            $contact,
            $externalId,
            $inbound->gateway_external_email,
        );
    }

    /** 处理满意度评价按钮回调并更新提示消息。 */
    private function handleCallbackQuery(Channel $channel, array $callbackQuery): void
    {
        $botToken = (string) $channel->telegramSettings()->bot_token;

        $callbackId = (string) ($callbackQuery['id'] ?? '');
        [$score, $conversationId] = $this->parseRatingCallback((string) ($callbackQuery['data'] ?? ''));

        if ($score === null || $conversationId === null || $callbackId === '' || $botToken === '') {
            if ($callbackId !== '' && $botToken !== '') {
                $this->safeAnswer($botToken, $callbackId, null);
            }

            return;
        }

        $conversation = Conversation::query()
            ->where('id', $conversationId)
            ->where('channel_id', $channel->id)
            ->first();

        if ($conversation === null) {
            Log::warning('Telegram 评价回调找不到渠道内会话。', [
                'channel_id' => (string) $channel->id,
                'conversation_id' => $conversationId,
                'callback_id' => $callbackId,
            ]);
        } else {
            try {
                SubmitConversationRatingAction::run($conversation, $score);
            } catch (BusinessException $exception) {
                Log::warning('Telegram 评价回调被业务状态拒绝。', [
                    'conversation_id' => $conversationId,
                    'reason' => $exception->getMessage(),
                ]);
            }
        }

        $locale = LocalePreference::normalizeLaravel($conversation?->visitor_locale);
        $thanks = (string) __('conversation.rating.telegram.thanks', [], $locale);
        $this->safeAnswer($botToken, $callbackId, $thanks);

        $messageObject = is_array($callbackQuery['message'] ?? null) ? $callbackQuery['message'] : [];
        $chat = is_array($messageObject['chat'] ?? null) ? $messageObject['chat'] : [];
        $chatId = (int) ($chat['id'] ?? 0);
        $promptMessageId = (int) ($messageObject['message_id'] ?? 0);
        if ($chatId !== 0 && $promptMessageId !== 0) {
            try {
                $this->api->editMessageText($botToken, $chatId, $promptMessageId, $thanks);
            } catch (TelegramApiException $exception) {
                Log::warning('Telegram 评价提示消息更新失败。', [
                    'conversation_id' => $conversationId,
                    'reason' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * 解析 `csat:{score}:{conversationId}` 格式的评价数据。
     *
     * @return array{0: ?ConversationRatingScore, 1: ?string}
     */
    private function parseRatingCallback(string $data): array
    {
        if (! str_starts_with($data, 'csat:')) {
            return [null, null];
        }

        $parts = explode(':', $data, 3);
        if (count($parts) !== 3) {
            return [null, null];
        }

        $score = ConversationRatingScore::tryFrom($parts[1]);
        $conversationId = trim($parts[2]);

        return [$score, $conversationId !== '' ? $conversationId : null];
    }

    /** 应答 Telegram callback_query，并记录非关键网络失败。 */
    private function safeAnswer(string $botToken, string $callbackId, ?string $text): void
    {
        try {
            $this->api->answerCallbackQuery($botToken, $callbackId, $text);
        } catch (TelegramApiException $exception) {
            Log::warning('Telegram 评价按钮应答失败。', [
                'callback_id' => $callbackId,
                'reason' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * 下载 Telegram 媒体并写入会话。
     *
     * @return array{conversation: Conversation, message: ?ConversationMessage}
     */
    private function handleMedia(
        Channel $channel,
        int $chatId,
        int $userId,
        ?string $displayName,
        int $messageId,
        string $mediaKind,
        string $fileId,
        ?string $fileName,
        ?string $mimeType,
        ?string $caption,
    ): array {
        $botToken = (string) $channel->telegramSettings()->bot_token;
        if ($botToken === '') {
            throw new UnexpectedValueException('Telegram 渠道缺少 Bot Token。');
        }

        $file = $this->api->getFile($botToken, $fileId);
        $filePath = is_string($file['file_path'] ?? null) ? $file['file_path'] : '';
        if ($filePath === '') {
            throw new UnexpectedValueException('Telegram getFile 未返回 file_path。');
        }

        $contents = $this->api->downloadFile($botToken, $filePath);
        $resolvedName = filled($fileName) ? $fileName : basename($filePath);
        $resolvedMime = filled($mimeType)
            ? $mimeType
            : ($mediaKind === 'image' ? 'image/jpeg' : 'application/octet-stream');

        return $this->appendMedia->handle(
            $channel->code,
            (string) $userId,
            $displayName,
            $mediaKind,
            $contents,
            $resolvedName,
            $resolvedMime,
            $caption,
            $messageId,
            $chatId,
        );
    }

    /**
     * 从 Telegram 消息中提取图片或文件字段。
     *
     * @return array{0: string, 1: string, 2: ?string, 3: ?string}
     */
    private function extractMedia(array $message): array
    {
        $photo = is_array($message['photo'] ?? null) ? $message['photo'] : [];
        if ($photo !== []) {
            $largest = array_last($photo);
            $fileId = is_array($largest) ? (string) ($largest['file_id'] ?? '') : '';
            if ($fileId !== '') {
                return ['image', $fileId, null, null];
            }
        }

        $document = is_array($message['document'] ?? null) ? $message['document'] : null;
        if ($document !== null && filled($document['file_id'] ?? null)) {
            return ['file', (string) $document['file_id'], $this->stringOrNull($document['file_name'] ?? null), $this->stringOrNull($document['mime_type'] ?? null)];
        }

        return ['', '', null, null];
    }

    /** 将 AI 接待中的文本消息接入接待管线。 */
    private function enqueueIfAiReply(array $result, string $text): void
    {
        $conversation = $result['conversation'];
        $message = $result['message'];

        if ($message !== null && $conversation->inbox_status === ConversationInboxStatus::AiHandling) {
            $this->pipeline->enqueueVisitorMessage((string) $conversation->id, $text, (string) $message->id);
        }
    }

    /** 从 Telegram 用户字段生成展示名。 */
    private function composeDisplayName(?string $firstName, ?string $lastName, ?string $username): ?string
    {
        $name = trim(implode(' ', array_filter([$firstName, $lastName], static fn (?string $part): bool => filled($part))));
        if ($name !== '') {
            return $name;
        }

        if (filled($username)) {
            return '@'.$username;
        }

        return null;
    }

    /** 将第三方字段收窄为非空字符串。 */
    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /** 判断文本是否为 Telegram 启动命令。 */
    private function isStartCommand(string $text): bool
    {
        return preg_match('/^\/start(?:@\w+)?(?:\s|$)/i', $text) === 1;
    }

    /** 将第三方字段收窄为布尔值。 */
    private function boolOrNull(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }

    /** 识别 Telegram Update 的顶层类型。 */
    private function updateType(array $payload): string
    {
        foreach (['message', 'edited_message', 'callback_query'] as $type) {
            if (is_array($payload[$type] ?? null)) {
                return $type;
            }
        }

        return 'unknown';
    }

    /** 读取并规范化可选的网关协议头。 */
    private function headerOrNull(Request $request, string $name): ?string
    {
        $value = trim((string) $request->header($name, ''));

        return $value !== '' ? $value : null;
    }
}
