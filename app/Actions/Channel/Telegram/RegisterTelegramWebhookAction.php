<?php

namespace App\Actions\Channel\Telegram;

use App\Enums\ChannelType;
use App\Enums\TelegramWebhookMode;
use App\Exceptions\BusinessException;
use App\Exceptions\TelegramApiException;
use App\Models\Channel;
use App\Services\Telegram\TelegramBotApi;
use App\Services\Telegram\TelegramChannelConnectionLock;
use App\Services\Telegram\TelegramWebhookUrl;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Lorisleiva\Actions\Concerns\AsAction;

/** 向 Telegram 注册渠道 webhook。 */
class RegisterTelegramWebhookAction
{
    use AsAction;

    /**
     * 注入 Telegram 连接所需服务。
     */
    public function __construct(
        private readonly TelegramBotApi $api,
        private readonly TelegramChannelConnectionLock $connectionLock,
        private readonly IsTelegramBotAvailableAction $isBotAvailable,
    ) {}

    /**
     * 串行清理已有 webhook 并重新注册。
     */
    public function handle(Channel $channel): void
    {
        try {
            $this->connectionLock->runForChannel((string) $channel->id, function () use ($channel): void {
                $this->handleWhileChannelLocked($channel);
            });
        } catch (LockTimeoutException $exception) {
            Log::warning('Telegram 渠道连接等待超时。', [
                'channel_id' => (string) $channel->id,
            ]);

            throw new BusinessException(
                __('channel.telegram.errors.connection_busy'),
                previous: $exception,
            );
        }
    }

    /**
     * 在已持有渠道连接锁时验证机器人并注册 webhook。
     */
    public function handleWhileChannelLocked(Channel $channel): void
    {
        $channel->refresh();
        $settings = $channel->telegramSettings();
        $botToken = (string) $settings->bot_token;

        if ($botToken === '') {
            Log::warning('Telegram webhook 注册缺少 Bot Token。', [
                'channel_id' => (string) $channel->id,
            ]);

            throw new BusinessException(__('channel.telegram.errors.invalid_bot_token'));
        }

        // 网关模式由外部服务持有 webhook。
        if ($settings->webhook_mode === TelegramWebhookMode::Gateway) {
            throw new BusinessException(__('channel.telegram.errors.webhook_gateway_managed'));
        }

        $botId = $settings->bot_id;
        $botUsername = $settings->bot_username;

        if ($botId === null) {
            try {
                $profile = $this->api->getMe($botToken);
            } catch (TelegramApiException $exception) {
                Log::warning('Telegram 机器人身份校验失败。', [
                    'channel_id' => (string) $channel->id,
                    'reason' => $exception->getMessage(),
                ]);

                throw new BusinessException(__('channel.telegram.errors.invalid_bot_token'));
            }

            $resolvedBotId = $profile['id'] ?? null;
            if (! is_int($resolvedBotId) || $resolvedBotId <= 0) {
                throw new BusinessException(__('channel.telegram.errors.invalid_bot_token'));
            }

            $botId = $resolvedBotId;
            $botUsername = is_string($profile['username'] ?? null)
                ? $profile['username']
                : null;
        }

        $this->connectionLock->runForBot($botId, function () use ($channel, $botId, $botUsername): void {
            if (! $this->isBotAvailable->handle($channel, $botId)) {
                throw new BusinessException(__('channel.telegram.errors.bot_already_connected'));
            }

            $channel->refresh();
            $settings = $channel->telegramSettings();
            if ($settings->bot_id === null) {
                $channel->update([
                    'settings' => $settings->withBotIdentity($botId, $botUsername),
                ]);
            }

            $this->registerWhileLocked($channel->refresh());
        });
    }

    /**
     * 在已持有渠道和机器人锁时调用 Telegram 注册 webhook。
     */
    private function registerWhileLocked(Channel $channel): void
    {
        $settings = $channel->telegramSettings();
        $botToken = (string) $settings->bot_token;

        $channel->update([
            'settings' => $settings->withWebhookRegisteredAt(null),
        ]);

        try {
            // 保留排队更新，避免删除和注册之间的消息丢失。
            $this->api->deleteWebhook($botToken);
            $this->api->setWebhook(
                $botToken,
                TelegramWebhookUrl::for($channel->code),
                $settings->webhook_secret,
            );
        } catch (TelegramApiException $exception) {
            Log::warning('Telegram webhook 注册失败。', [
                'channel_id' => (string) $channel->id,
                'reason' => $exception->getMessage(),
            ]);

            throw new BusinessException(__('channel.telegram.errors.webhook_registration_failed'));
        }

        $settings = $channel->refresh()->telegramSettings();
        $channel->update([
            'settings' => $settings->withWebhookRegisteredAt(now()->toIso8601String()),
        ]);

        Log::info('Telegram webhook 注册成功。', [
            'channel_id' => (string) $channel->id,
        ]);
    }

    /** 处理手动注册请求。 */
    public function asController(Request $request, string $channel): RedirectResponse
    {
        $channelModel = Channel::query()
            ->where('type', ChannelType::Telegram)
            ->findOrFail($channel);

        $this->handle($channelModel);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('channel.telegram.webhook_registered')]);

        return redirect()->route('app.manage.channels.telegram.show', [
            'channel' => $channelModel->id,
        ]);
    }
}
