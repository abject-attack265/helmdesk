<?php

use App\Models\AiModel;
use App\Models\Channel;
use App\Models\ReceptionPlan;
use App\Models\ReceptionPlanVersion;
use App\Settings\GeneralSettings;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

// Telegram 渠道测试共享夹具：被 telegram 各测试文件 require_once 引入，函数存在性保护避免重复声明。

if (! function_exists('postTelegramUpdate')) {
    /**
     * 模拟 Telegram 回调：向 webhook 路由 POST 一条 message 更新，默认带渠道正确 secret 头。
     *
     * @param  array<string, mixed>  $message  Telegram update 中的 message 字段
     */
    function postTelegramUpdate(Channel $channel, array $message, ?string $secret = null): TestResponse
    {
        return test()->postJson(
            "/webhook/telegram/{$channel->code}",
            [
                'update_id' => $message['message_id'],
                'message' => $message,
            ],
            ['X-Telegram-Bot-Api-Secret-Token' => $secret ?? $channel->settings->webhook_secret],
        );
    }
}

if (! function_exists('createTelegramTestModel')) {
    /**
     * Seed 一个全局可用的接待对话 LLM 模型，使接待方案在渠道侧判定为可部署。
     *
     * 模型由系统统一配置，运行时按 reception_chat 用途从系统模型池取用。
     */
    function createTelegramTestModel(GeneralSettings $app): AiModel
    {
        return makeAiModel();
    }
}

if (! function_exists('createTelegramDeployablePlanVersion')) {
    /**
     * 创建一个可部署到渠道的接待方案版本，并保证全局存在可用的接待对话模型。
     */
    function createTelegramDeployablePlanVersion(GeneralSettings $app): ReceptionPlanVersion
    {
        createTelegramTestModel($app);

        $plan = ReceptionPlan::factory()->create([
            'name' => 'TG 接待方案-'.Str::lower(Str::random(6)),
        ]);

        return ReceptionPlanVersion::factory()->for($plan, 'plan')->create();
    }
}
