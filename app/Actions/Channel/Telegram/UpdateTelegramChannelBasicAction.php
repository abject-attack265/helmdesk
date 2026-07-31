<?php

namespace App\Actions\Channel\Telegram;

use App\Actions\Reception\Plan\ResolveChannelReceptionPlanAction;
use App\Data\Channel\Telegram\ChannelTelegramSettingsData;
use App\Data\Channel\Telegram\FormUpdateTelegramChannelBasicData;
use App\Enums\ChannelType;
use App\Enums\TelegramWebhookMode;
use App\Exceptions\BusinessException;
use App\Exceptions\TelegramApiException;
use App\Models\Channel;
use App\Services\Telegram\TelegramBotApi;
use App\Services\Telegram\TelegramChannelConnectionLock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/** 保存 Telegram 渠道信息和机器人连接设置。 */
class UpdateTelegramChannelBasicAction
{
    use AsAction;

    /**
     * 注入 Telegram 渠道保存和连接所需服务。
     */
    public function __construct(
        private readonly ResolveChannelReceptionPlanAction $resolveChannelReceptionPlan,
        private readonly TelegramBotApi $api,
        private readonly TelegramChannelConnectionLock $connectionLock,
        private readonly RegisterTelegramWebhookAction $registerWebhook,
        private readonly IsTelegramBotAvailableAction $isBotAvailable,
    ) {}

    /**
     * 保存渠道信息，并为尚未连接的直连渠道注册 webhook。
     */
    public function handle(Channel $channel, FormUpdateTelegramChannelBasicData $data): void
    {
        try {
            $this->connectionLock->runForChannel((string) $channel->id, function () use ($channel, $data): void {
                $this->updateWhileLocked($channel, $data);
            });
        } catch (LockTimeoutException $exception) {
            Log::warning('Telegram 渠道保存等待连接操作超时。', [
                'channel_id' => (string) $channel->id,
            ]);

            throw new BusinessException(
                __('channel.telegram.errors.connection_busy'),
                previous: $exception,
            );
        }
    }

    /**
     * 在已持有渠道连接锁时保存设置并连接 Telegram。
     */
    private function updateWhileLocked(Channel $channel, FormUpdateTelegramChannelBasicData $data): void
    {
        $channel->refresh();
        $submittedPlanId = $data->reception_plan_id;
        $requireUsable = $submittedPlanId !== $channel->reception_plan_id;
        $planId = $this->resolveChannelReceptionPlan->handle(
            $submittedPlanId,
            requireUsable: $requireUsable,
        );

        $current = $channel->telegramSettings();
        $submittedToken = filled($data->bot_token) ? trim((string) $data->bot_token) : null;

        if ($submittedToken === null && ! filled($current->bot_token)) {
            throw ValidationException::withMessages([
                'bot_token' => __('channel.telegram.errors.bot_token_required'),
            ]);
        }

        $newToken = $submittedToken !== null && $submittedToken !== $current->bot_token
            ? $submittedToken
            : null;
        $botUsername = $current->bot_username;
        $botId = $current->bot_id;
        $switchedToDirect = $current->webhook_mode === TelegramWebhookMode::Gateway
            && $data->webhook_mode === TelegramWebhookMode::Direct;
        $switchedFromDirect = $current->webhook_mode === TelegramWebhookMode::Direct
            && $data->webhook_mode === TelegramWebhookMode::Gateway;
        $shouldRegisterWebhook = $data->webhook_mode === TelegramWebhookMode::Direct
            && ($newToken !== null
                || $switchedToDirect
                || $current->webhook_registered_at === null);
        $tokenForIdentity = $newToken ?? ($botId === null ? $current->bot_token : null);

        if (filled($tokenForIdentity)) {
            try {
                $profile = $this->api->getMe((string) $tokenForIdentity);
            } catch (TelegramApiException) {
                throw ValidationException::withMessages([
                    'bot_token' => __('channel.telegram.errors.invalid_bot_token'),
                ]);
            }

            $resolvedBotId = $profile['id'] ?? null;
            if (! is_int($resolvedBotId) || $resolvedBotId <= 0) {
                throw ValidationException::withMessages([
                    'bot_token' => __('channel.telegram.errors.invalid_bot_token'),
                ]);
            }

            $botUsername = is_string($profile['username'] ?? null)
                ? $profile['username']
                : null;
            $botId = $resolvedBotId;
        }

        if ($botId === null) {
            throw ValidationException::withMessages([
                'bot_token' => __('channel.telegram.errors.invalid_bot_token'),
            ]);
        }

        $rotateWebhookSecret = $switchedToDirect || $switchedFromDirect || $newToken !== null;

        $settings = new ChannelTelegramSettingsData(
            bot_token: $newToken ?? $current->bot_token,
            webhook_secret: $rotateWebhookSecret ? Str::random(48) : $current->webhook_secret,
            bot_username: $botUsername,
            bot_id: $botId,
            default_visitor_locale: $data->default_visitor_locale,
            visitor_message_ai_translation_enabled: $data->visitor_message_ai_translation_enabled,
            translation_context_hint: filled($data->translation_context_hint) ? trim($data->translation_context_hint) : null,
            webhook_registered_at: $data->webhook_mode === TelegramWebhookMode::Gateway
                || $newToken !== null
                || $switchedToDirect
                ? null
                : $current->webhook_registered_at,
            webhook_mode: $data->webhook_mode,
        );

        $this->connectionLock->runForBot($botId, function () use ($channel, $data, $planId, $settings, $botId): void {
            if (! $this->isBotAvailable->handle($channel, $botId)) {
                throw ValidationException::withMessages([
                    'bot_token' => __('channel.telegram.errors.bot_already_connected'),
                ]);
            }

            $channel->update([
                'name' => $data->name,
                'description' => filled($data->description) ? $data->description : null,
                'reception_plan_id' => $planId,
                'settings' => $settings,
            ]);
        });

        if ($shouldRegisterWebhook) {
            $this->registerWebhook->handleWhileChannelLocked($channel);
        }
    }

    /**
     * 处理详情页提交并返回当前渠道。
     */
    public function asController(Request $request, string $channel): RedirectResponse
    {
        $channelModel = Channel::query()
            ->where('type', ChannelType::Telegram)
            ->findOrFail($channel);

        $this->handle($channelModel, FormUpdateTelegramChannelBasicData::from($request));

        return redirect()->route('app.manage.channels.telegram.show', [
            'channel' => $channelModel->id,
        ]);
    }
}
