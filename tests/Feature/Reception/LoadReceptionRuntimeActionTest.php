<?php

use App\Actions\Reception\AppendAiMessageAction;
use App\Actions\Reception\LoadReceptionRuntimeAction;
use App\Enums\AiModelPurpose;
use App\Enums\ConversationInboxStatus;
use App\Enums\MessageRole;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ReceptionPlan;
use App\Models\ReceptionPlanVersion;
use App\Services\Reception\ReceptionGroundingProbe;
use App\Services\Reception\ReceptionToolsetBuilder;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NeuronAI\Tools\Tool;

uses(RefreshDatabase::class);

/**
 * 造一个 app + 可用接待模型 + plan + 绑定该方案的 channel + 一个已发布版本 v1。
 *
 * @return array{0: GeneralSettings, 1: ReceptionPlan, 2: Channel, 3: ReceptionPlanVersion}
 */
function setupReceptionRuntimeFixture(): array
{
    $app = createSystemSettings();
    makeAiModel(AiModelPurpose::ReceptionChat, makeUsableAiProvider());

    $plan = ReceptionPlan::factory()->create();
    $v1 = ReceptionPlanVersion::factory()->for($plan, 'plan')->create(['version_number' => 1]);
    $channel = Channel::factory()->create([
        'reception_plan_id' => $plan->id,
    ]);

    return [$app, $plan, $channel, $v1];
}

test('运行时按渠道解析最新已发布版本，会话跨轮跟随升级到 v2', function () {
    [$app, $plan, $channel, $v1] = setupReceptionRuntimeFixture();

    // 会话出生时锁定 v1
    $conversation = Conversation::factory()->create([
        'channel_id' => $channel->id,
        'inbox_status' => ConversationInboxStatus::AiHandling,
        'reception_plan_version_id' => $v1->id,
    ]);

    // 方案再发布一版 v2（更高 version_number）
    $v2 = ReceptionPlanVersion::factory()->for($plan, 'plan')->create([
        'version_number' => 2,
        'compiled_config' => [
            'reception_instruction' => 'v2 专属指令',
            'knowledge_bases' => [],
            'integration_grants' => [],
        ],
    ]);

    $runtime = app(LoadReceptionRuntimeAction::class)->handle((string) $conversation->id);

    expect($runtime->available)->toBeTrue()
        ->and($runtime->reception_plan_version_id)->toBe((string) $v2->id)
        ->and($runtime->system_prompt)->toContain('v2 专属指令')
        // 会话级「当前驱动版本」被刷新到 v2
        ->and((string) $conversation->fresh()->reception_plan_version_id)->toBe((string) $v2->id);
});

test('方案未绑知识库时提示词不提及 knowledge_search，与未挂载的工具集一致', function () {
    [$app, $plan, $channel, $v1] = setupReceptionRuntimeFixture();

    // 工厂默认 compiled_config 的 knowledge_bases 为空 → 不挂 knowledge_search 工具
    $conversation = Conversation::factory()->create([
        'channel_id' => $channel->id,
        'inbox_status' => ConversationInboxStatus::AiHandling,
        'reception_plan_version_id' => $v1->id,
    ]);

    $runtime = app(LoadReceptionRuntimeAction::class)->handle((string) $conversation->id);

    // 提示词若命令模型调用未挂载的 knowledge_search，听话的模型会真去调用，
    // 被 NeuronAI 以「non-existing tool」拒掉导致整轮失败（线上事故根因）。
    expect($runtime->available)->toBeTrue()
        ->and($runtime->system_prompt)->not->toContain('knowledge_search');

    $toolNames = array_map(
        static fn ($tool): string => $tool->getName(),
        app(ReceptionToolsetBuilder::class)->build(
            Tool::make('respond', '模拟出口'),
            Tool::make('handoff_to_human', '模拟出口'),
            $runtime,
            new ReceptionGroundingProbe,
        )->tools,
    );
    expect($toolNames)->not->toContain('knowledge_search');
});

test('方案绑定知识库时提示词要求先检索，且 knowledge_search 工具已挂载', function () {
    [$app, $plan, $channel, $v1] = setupReceptionRuntimeFixture();

    $v2 = ReceptionPlanVersion::factory()->for($plan, 'plan')->create([
        'version_number' => 2,
        'compiled_config' => [
            'reception_instruction' => '你是一名客服助手。',
            'knowledge_bases' => [
                ['id' => '01test0000000000000000kb01', 'name' => '产品手册', 'description' => '产品操作说明', 'category' => null],
            ],
            'integration_grants' => [],
        ],
    ]);
    $conversation = Conversation::factory()->create([
        'channel_id' => $channel->id,
        'inbox_status' => ConversationInboxStatus::AiHandling,
        'reception_plan_version_id' => $v2->id,
    ]);

    $runtime = app(LoadReceptionRuntimeAction::class)->handle((string) $conversation->id);

    expect($runtime->available)->toBeTrue()
        ->and($runtime->system_prompt)->toContain('knowledge_search');

    $toolNames = array_map(
        static fn ($tool): string => $tool->getName(),
        app(ReceptionToolsetBuilder::class)->build(
            Tool::make('respond', '模拟出口'),
            Tool::make('handoff_to_human', '模拟出口'),
            $runtime,
            new ReceptionGroundingProbe,
        )->tools,
    );
    expect($toolNames)->toContain('knowledge_search');
});

test('渠道未绑接待方案时运行时不可用', function () {
    $app = createSystemSettings();
    makeAiModel(AiModelPurpose::ReceptionChat, makeUsableAiProvider());
    $channel = Channel::factory()->create([
        'reception_plan_id' => null,
    ]);
    $conversation = Conversation::factory()->create([
        'channel_id' => $channel->id,
        'inbox_status' => ConversationInboxStatus::AiHandling,
        'reception_plan_version_id' => null,
    ]);

    $runtime = app(LoadReceptionRuntimeAction::class)->handle((string) $conversation->id);

    expect($runtime->available)->toBeFalse();
});

test('AppendAiMessageAction 把版本快照盖到 AI 消息上（逐消息审计）', function () {
    [$app, $plan, $channel, $v1] = setupReceptionRuntimeFixture();
    $conversation = Conversation::factory()->create([
        'channel_id' => $channel->id,
        'inbox_status' => ConversationInboxStatus::AiHandling,
        'reception_plan_version_id' => $v1->id,
    ]);

    app(AppendAiMessageAction::class)->handle($conversation, '您好，有什么可以帮您？', null, (string) $v1->id);

    $message = ConversationMessage::query()
        ->where('conversation_id', $conversation->id)
        ->where('role', MessageRole::Ai)
        ->firstOrFail();

    expect((string) $message->reception_plan_version_id)->toBe((string) $v1->id);
});
