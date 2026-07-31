<?php

namespace App\Services\Telegram;

use App\Exceptions\TelegramApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Telegram Bot API 客户端封装。
 *
 * 调用方显式传入 Token，接口失败统一抛出 TelegramApiException。
 */
class TelegramBotApi
{
    private const int TIMEOUT_SECONDS = 10;

    /** 文件上传 / 下载比普通 API 调用耗时更长，单独放宽超时。 */
    private const int DOWNLOAD_TIMEOUT_SECONDS = 30;

    /**
     * 调 getMe 校验 Token 并返回机器人信息（id / username / first_name 等）。
     *
     * @return array<string, mixed>
     */
    public function getMe(string $botToken): array
    {
        return $this->call($botToken, 'getMe');
    }

    /**
     * 注册 webhook，并启用 secret_token 头校验。
     */
    public function setWebhook(string $botToken, string $url, string $secretToken): void
    {
        $this->call($botToken, 'setWebhook', [
            'url' => $url,
            'secret_token' => $secretToken,
            // 订阅消息更新 + 满意度评价按钮的 callback_query。
            'allowed_updates' => ['message', 'edited_message', 'callback_query'],
        ]);
    }

    /** 删除机器人当前的 webhook。 */
    public function deleteWebhook(string $botToken): void
    {
        $this->call($botToken, 'deleteWebhook');
    }

    /**
     * 向指定 Telegram 会话发送文本消息，返回 Telegram 侧的消息对象。
     *
     * $parseMode 传 'HTML' 时按 Telegram HTML 子集解析富文本，传 null 时按纯文本发送。
     *
     * @return array<string, mixed>
     */
    public function sendMessage(string $botToken, int $chatId, string $text, ?int $replyToMessageId = null, ?string $parseMode = null): array
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($parseMode !== null) {
            $payload['parse_mode'] = $parseMode;
        }

        if ($replyToMessageId !== null) {
            $payload['reply_parameters'] = ['message_id' => $replyToMessageId, 'allow_sending_without_reply' => true];
        }

        return $this->call($botToken, 'sendMessage', $payload);
    }

    /**
     * 发送带 inline 按钮的消息（用于满意度评价的 👍/👎 提示）。
     *
     * @param  list<list<array{text: string, callback_data: string}>>  $inlineKeyboard  按钮行二维数组
     * @return array<string, mixed>
     */
    public function sendInlineKeyboard(string $botToken, int $chatId, string $text, array $inlineKeyboard): array
    {
        return $this->call($botToken, 'sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => ['inline_keyboard' => $inlineKeyboard],
        ]);
    }

    /**
     * 应答 callback_query，消除 Telegram 客户端按钮的加载态，可选弹出轻提示。
     */
    public function answerCallbackQuery(string $botToken, string $callbackQueryId, ?string $text = null): void
    {
        $payload = ['callback_query_id' => $callbackQueryId];
        if ($text !== null) {
            $payload['text'] = $text;
        }

        $this->call($botToken, 'answerCallbackQuery', $payload);
    }

    /**
     * 改写已发出的消息文本并移除其 inline 按钮（评价提交后把提示改成「感谢」）。
     *
     * @return array<string, mixed>
     */
    public function editMessageText(string $botToken, int $chatId, int $messageId, string $text): array
    {
        return $this->call($botToken, 'editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
        ]);
    }

    /**
     * 调 getFile 解析文件的临时下载路径（file_path），用于随后下载文件内容。
     *
     * @return array<string, mixed>
     */
    public function getFile(string $botToken, string $fileId): array
    {
        return $this->call($botToken, 'getFile', ['file_id' => $fileId]);
    }

    /**
     * 拉取 Telegram 用户头像列表，按 Telegram 返回的二维 PhotoSize 数组保留原始结构。
     *
     * @return array<string, mixed>
     */
    public function getUserProfilePhotos(string $botToken, int $userId, int $limit = 1): array
    {
        return $this->call($botToken, 'getUserProfilePhotos', [
            'user_id' => $userId,
            'limit' => $limit,
        ]);
    }

    /**
     * 按 getFile 返回的 file_path 下载文件二进制内容。
     */
    public function downloadFile(string $botToken, string $filePath): string
    {
        $url = rtrim((string) config('services.telegram.api_base'), '/')."/file/bot{$botToken}/{$filePath}";

        try {
            $response = Http::timeout(self::DOWNLOAD_TIMEOUT_SECONDS)->get($url);
        } catch (ConnectionException $e) {
            throw new TelegramApiException('Telegram 文件下载连接失败', 0, $e);
        }

        if (! $response->successful()) {
            throw new TelegramApiException('Telegram 文件下载失败', $response->status());
        }

        return $response->body();
    }

    /**
     * 以图片形式发送文件内容（multipart 上传），返回 Telegram 侧消息对象。
     *
     * @return array<string, mixed>
     */
    public function sendPhoto(string $botToken, int $chatId, string $contents, string $filename, ?string $caption = null, ?int $replyToMessageId = null): array
    {
        return $this->sendMedia($botToken, 'sendPhoto', 'photo', $chatId, $contents, $filename, $caption, $replyToMessageId);
    }

    /**
     * 以文档形式发送文件内容（multipart 上传），返回 Telegram 侧消息对象。
     *
     * @return array<string, mixed>
     */
    public function sendDocument(string $botToken, int $chatId, string $contents, string $filename, ?string $caption = null, ?int $replyToMessageId = null): array
    {
        return $this->sendMedia($botToken, 'sendDocument', 'document', $chatId, $contents, $filename, $caption, $replyToMessageId);
    }

    /**
     * 以 multipart/form-data 上传文件并发送（sendPhoto / sendDocument 共用）。
     *
     * @return array<string, mixed>
     */
    private function sendMedia(string $botToken, string $method, string $field, int $chatId, string $contents, string $filename, ?string $caption, ?int $replyToMessageId): array
    {
        $endpoint = rtrim((string) config('services.telegram.api_base'), '/')."/bot{$botToken}/{$method}";

        $payload = ['chat_id' => $chatId];
        if (filled($caption)) {
            $payload['caption'] = $caption;
        }
        if ($replyToMessageId !== null) {
            // multipart 下嵌套参数需以 JSON 字符串提交。
            $payload['reply_parameters'] = json_encode(['message_id' => $replyToMessageId, 'allow_sending_without_reply' => true]);
        }

        try {
            $response = Http::timeout(self::DOWNLOAD_TIMEOUT_SECONDS)
                ->attach($field, $contents, $filename)
                ->post($endpoint, $payload);
        } catch (ConnectionException $e) {
            throw new TelegramApiException("Telegram API 连接失败：{$method}", 0, $e);
        }

        return $this->unwrap($response, $method);
    }

    /**
     * 发起一次 Bot API 调用并解包 result；非 ok 或网络异常时抛 TelegramApiException。
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function call(string $botToken, string $method, array $payload = []): array
    {
        $endpoint = rtrim((string) config('services.telegram.api_base'), '/')."/bot{$botToken}/{$method}";

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->asJson()
                ->acceptJson()
                ->post($endpoint, $payload);
        } catch (ConnectionException $e) {
            throw new TelegramApiException("Telegram API 连接失败：{$method}", 0, $e);
        }

        return $this->unwrap($response, $method);
    }

    /**
     * 校验 Bot API 响应并解包 result；非 ok 或返回异常内容时抛 TelegramApiException。
     *
     * @return array<string, mixed>
     */
    private function unwrap(Response $response, string $method): array
    {
        $body = $response->json();
        if (! is_array($body)) {
            throw new TelegramApiException("Telegram API 返回非预期内容：{$method}", $response->status());
        }

        if (($body['ok'] ?? false) !== true) {
            $description = is_string($body['description'] ?? null) ? $body['description'] : 'unknown error';
            $errorCode = is_int($body['error_code'] ?? null) ? $body['error_code'] : $response->status();

            throw new TelegramApiException($description, $errorCode);
        }

        $result = $body['result'] ?? [];

        return is_array($result) ? $result : ['result' => $result];
    }
}
