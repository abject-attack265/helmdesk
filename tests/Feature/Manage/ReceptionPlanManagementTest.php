<?php

use App\Enums\ConversationStatus;
use App\Enums\ReceptionPlanVersionStatus;
use App\Enums\ReceptionRoutingMode;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Integration;
use App\Models\KnowledgeBase;
use App\Models\ReceptionPlan;
use App\Models\ReceptionPlanVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = $this->createUserWithInstance();
});

/**
 * 创建接待方案测试使用的模型服务。
 */
function createReceptionTestProvider(array $attributes = []): AiProvider
{
    return makeUsableAiProvider($attributes);
}

/**
 * 创建接待方案测试使用的对话模型。
 */
function createReceptionTestModel(AiProvider $provider, array $attributes = []): AiModel
{
    $isActive = $attributes['is_active'] ?? true;

    return makeAiModel(provider: $provider, isActive: $isActive);
}

/**
 * 返回接待方案测试使用的完整策略配置。
 */
function receptionPlanStrategyPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'reception_mode' => ReceptionRoutingMode::AiFirst->value,
        'unassigned_ai_takeover_enabled' => false,
        'unassigned_ai_takeover_timeout_seconds' => 120,
        'teammate_no_response_ai_takeover_enabled' => true,
        'teammate_no_response_ai_takeover_timeout_seconds' => 300,
        'auto_close_enabled' => true,
        'auto_close_idle_minutes' => 10,
        'important_contact_ai_careful_reply_enabled' => true,
        'important_contact_ai_handoff_hint_enabled' => true,
        'important_contact_human_first_when_online_enabled' => false,
        'quote_visitor_message_enabled' => false,
        'handoff_available_notice' => '已为您转接人工客服，请稍等。',
        'handoff_no_teammate_notice' => '当前暂无法转接人工，我会继续为您处理。',
        'ai_unavailable_notice' => '很抱歉，AI 助手暂时无法为您服务，正在为您转接人工客服，请稍候。',
        'business_hours' => null,
    ], $overrides);
}

test('所有者可以查看接待方案列表页', function () {
    // 模型由系统统一配置：方案运行时按用途取用，列表只展示方案本身配置。
    createReceptionTestModel(createReceptionTestProvider());

    $plan = ReceptionPlan::factory()->create([
        'name' => '默认接待方案',
        'description' => '默认描述',
        'persona_config' => [
            'display_name' => '接待助手',
            'tone' => 'professional',
        ],
    ]);

    $kbA = KnowledgeBase::factory()->create([]);
    $kbB = KnowledgeBase::factory()->create([]);
    $plan->knowledgeBases()->sync([$kbA->id, $kbB->id]);

    $integration = Integration::factory()->create();
    $plan->integrationGrants()->create([
        'integration_id' => $integration->id,
        'tool_whitelist' => null,
    ]);

    $this->actingAs($this->user)
        ->get(route('app.manage.reception.plans.index', []))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reception/plans/List')
            ->has('plan_list', 1)
            ->where('plan_list.0.name', '默认接待方案')
            ->where('plan_list.0.knowledge_bases_count', 2)
            ->where('plan_list.0.integration_grants_count', 1)
            ->where('plan_list.0.persona_config.tone_label', __('reception.persona_tones.professional'))
            ->where('plan_list.0.strategy_config.quote_visitor_message_enabled', false)
        );
});

test('创建接待方案时语气风格非法值会被校验拒绝', function () {
    $provider = createReceptionTestProvider();
    $model = createReceptionTestModel($provider);

    $this->actingAs($this->user)
        ->post(route('app.manage.reception.plans.store', []), [
            'name' => '非法语气方案',
            'description' => null,
            'persona_display_name' => '接待助手',
            'persona_tone' => 'sarcastic',
            'global_instructions' => null,
            'reception_ai_model_id' => $model->id,
            'task_ai_model_id' => $model->id,
            'strategy_config' => receptionPlanStrategyPayload(),
        ])
        ->assertSessionHasErrors(['persona_tone']);
});

test('超时转 AI 时长不小于空闲自动结束时长时被校验拒绝', function () {
    createReceptionTestModel(createReceptionTestProvider());

    // 两个计时起点相同：接管超时 >= 关单超时意味着会话总会先被关掉，接管永不生效。
    $this->actingAs($this->user)
        ->post(route('app.manage.reception.plans.store', []), [
            'name' => '配置矛盾方案',
            'persona_display_name' => '小 A',
            'persona_tone' => 'friendly',
            'strategy_config' => receptionPlanStrategyPayload([
                'reception_mode' => ReceptionRoutingMode::TeammateFirst->value,
                'unassigned_ai_takeover_enabled' => true,
                'unassigned_ai_takeover_timeout_seconds' => 900,
                'teammate_no_response_ai_takeover_enabled' => true,
                'teammate_no_response_ai_takeover_timeout_seconds' => 600,
                'auto_close_enabled' => true,
                'auto_close_idle_minutes' => 10,
            ]),
        ])
        ->assertSessionHasErrors([
            'strategy_config.unassigned_ai_takeover_timeout_seconds',
            'strategy_config.teammate_no_response_ai_takeover_timeout_seconds',
        ]);
});

test('创建接待方案时语气风格必填', function () {
    $provider = createReceptionTestProvider();
    $model = createReceptionTestModel($provider);

    $this->actingAs($this->user)
        ->post(route('app.manage.reception.plans.store', []), [
            'name' => '缺语气风格方案',
            'description' => null,
            'persona_display_name' => '接待助手',
            'persona_tone' => '',
            'global_instructions' => null,
            'reception_ai_model_id' => $model->id,
            'task_ai_model_id' => $model->id,
            'strategy_config' => receptionPlanStrategyPayload(),
        ])
        ->assertSessionHasErrors(['persona_tone']);
});

test('创建接待方案时对外昵称必填', function () {
    $provider = createReceptionTestProvider();
    $model = createReceptionTestModel($provider);

    $this->actingAs($this->user)
        ->post(route('app.manage.reception.plans.store', []), [
            'name' => '缺对外昵称方案',
            'description' => null,
            'persona_display_name' => '',
            'persona_tone' => 'concise',
            'global_instructions' => null,
            'reception_ai_model_id' => $model->id,
            'task_ai_model_id' => $model->id,
            'strategy_config' => receptionPlanStrategyPayload(),
        ])
        ->assertSessionHasErrors(['persona_display_name']);
});

test('创建接待方案时接待要求必填', function () {
    $this->actingAs($this->user)
        ->post(route('app.manage.reception.plans.store'), [
            'name' => '缺接待要求方案',
            'persona_display_name' => '接待助手',
            'persona_tone' => 'concise',
            'global_instructions' => null,
            'strategy_config' => receptionPlanStrategyPayload(),
        ])
        ->assertSessionHasErrors(['global_instructions']);
});

test('创建接待方案即生成初始版本快照', function () {
    createReceptionTestModel(createReceptionTestProvider());

    $this->actingAs($this->user)
        ->post(route('app.manage.reception.plans.store', []), [
            'name' => '初始版本方案',
            'persona_display_name' => '小 A',
            'persona_tone' => 'friendly',
            'global_instructions' => '保持友好简洁',
            'strategy_config' => receptionPlanStrategyPayload(),
        ])
        ->assertRedirect();

    $plan = ReceptionPlan::query()->firstOrFail();
    $version = ReceptionPlanVersion::query()->where('reception_plan_id', $plan->id)->firstOrFail();

    expect($version->version_number)->toBe(1)
        ->and($version->status)->toBe(ReceptionPlanVersionStatus::Published)
        ->and($version->compiled_config['reception_instruction'])->toContain('小 A');
});

test('所有者可以创建接待方案草稿', function () {
    createReceptionTestModel(createReceptionTestProvider());
    $strategyConfig = receptionPlanStrategyPayload([
        'reception_mode' => ReceptionRoutingMode::TeammateFirst->value,
        'unassigned_ai_takeover_enabled' => true,
        'unassigned_ai_takeover_timeout_seconds' => 90,
        'handoff_available_notice' => '正在为您转接人工客服。',
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('app.manage.reception.plans.store', []), [
            'name' => '售前接待方案',
            'description' => '负责售前咨询',
            'persona_display_name' => '小 A',
            'persona_tone' => 'friendly',
            'global_instructions' => '保持友好简洁',
            'strategy_config' => $strategyConfig,
        ]);

    $plan = ReceptionPlan::query()->firstOrFail();

    $response->assertRedirect(route('app.manage.reception.plans.show', [
        'plan' => $plan->id,
    ]));

    expect($plan->name)->toBe('售前接待方案')
        ->and($plan->persona_config['display_name'])->toBe('小 A')
        ->and($plan->persona_config['tone'])->toBe('friendly')
        ->and($plan->global_instructions)->toBe('保持友好简洁')
        ->and($plan->strategy_config)->toBe($strategyConfig);
});

test('接待方案营业时间允许结束于午夜', function () {
    $provider = createReceptionTestProvider();
    $model = createReceptionTestModel($provider);
    $strategyConfig = receptionPlanStrategyPayload([
        'business_hours' => [
            'timezone' => 'Asia/Shanghai',
            'outside_hours_notice' => '当前不是人工服务时间。',
            'schedule' => [
                ['day' => 1, 'enabled' => true, 'open' => '09:00', 'close' => '00:00'],
                ['day' => 2, 'enabled' => false, 'open' => '09:00', 'close' => '18:00'],
                ['day' => 3, 'enabled' => false, 'open' => '09:00', 'close' => '18:00'],
                ['day' => 4, 'enabled' => false, 'open' => '09:00', 'close' => '18:00'],
                ['day' => 5, 'enabled' => false, 'open' => '09:00', 'close' => '18:00'],
                ['day' => 6, 'enabled' => false, 'open' => '09:00', 'close' => '18:00'],
                ['day' => 7, 'enabled' => false, 'open' => '09:00', 'close' => '18:00'],
            ],
        ],
    ]);

    $this->actingAs($this->user)
        ->post(route('app.manage.reception.plans.store', []), [
            'name' => '午夜营业方案',
            'description' => null,
            'persona_display_name' => '接待助手',
            'persona_tone' => 'concise',
            'global_instructions' => '保持友好简洁',
            'reception_ai_model_id' => $model->id,
            'task_ai_model_id' => $model->id,
            'strategy_config' => $strategyConfig,
        ])
        ->assertSessionHasNoErrors();

    expect(ReceptionPlan::query()->firstOrFail()->strategy_config)->toMatchArray($strategyConfig);
});

test('接待方案营业时间的结束时间必须晚于开始时间', function () {
    createReceptionTestModel(createReceptionTestProvider());

    $this->actingAs($this->user)
        ->post(route('app.manage.reception.plans.store'), [
            'name' => '错误营业时间方案',
            'persona_display_name' => '接待助手',
            'persona_tone' => 'concise',
            'strategy_config' => receptionPlanStrategyPayload([
                'business_hours' => [
                    'timezone' => 'Asia/Shanghai',
                    'outside_hours_notice' => '当前不是人工服务时间。',
                    'schedule' => [
                        ['day' => 1, 'enabled' => true, 'open' => '18:00', 'close' => '09:00'],
                        ['day' => 2, 'enabled' => false, 'open' => '09:00', 'close' => '18:00'],
                        ['day' => 3, 'enabled' => false, 'open' => '09:00', 'close' => '18:00'],
                        ['day' => 4, 'enabled' => false, 'open' => '09:00', 'close' => '18:00'],
                        ['day' => 5, 'enabled' => false, 'open' => '09:00', 'close' => '18:00'],
                        ['day' => 6, 'enabled' => false, 'open' => '09:00', 'close' => '18:00'],
                        ['day' => 7, 'enabled' => false, 'open' => '09:00', 'close' => '18:00'],
                    ],
                ],
            ]),
        ])
        ->assertSessionHasErrors(['strategy_config.business_hours.schedule.0.close']);
});

test('同一应用内方案名称必须唯一', function () {
    $provider = createReceptionTestProvider();
    $model = createReceptionTestModel($provider);

    ReceptionPlan::factory()->create([
        'name' => '已存在方案',
    ]);

    $this->actingAs($this->user)
        ->post(route('app.manage.reception.plans.store', []), [
            'name' => '已存在方案',
            'description' => null,
            'persona_display_name' => '接待助手',
            'persona_tone' => 'concise',
            'global_instructions' => '保持友好简洁',
            'reception_ai_model_id' => $model->id,
            'task_ai_model_id' => $model->id,
            'strategy_config' => receptionPlanStrategyPayload(),
        ])
        ->assertSessionHasErrors(['name']);
});

test('创建接待方案时回收站中的同名方案也会占用名称', function () {
    createReceptionTestModel(createReceptionTestProvider());

    $deletedPlan = ReceptionPlan::factory()->create([
        'name' => '回收站方案',
    ]);
    $deletedPlan->delete();

    $this->actingAs($this->user)
        ->post(route('app.manage.reception.plans.store'), [
            'name' => '回收站方案',
            'persona_display_name' => '接待助手',
            'persona_tone' => 'concise',
            'global_instructions' => '保持友好简洁',
            'strategy_config' => receptionPlanStrategyPayload(),
        ])
        ->assertSessionHasErrors(['name']);

    expect(ReceptionPlan::query()->where('name', '回收站方案')->doesntExist())->toBeTrue();
});

test('所有者可以更新接待方案草稿', function () {
    createReceptionTestModel(createReceptionTestProvider());
    $strategyConfig = receptionPlanStrategyPayload([
        'teammate_no_response_ai_takeover_enabled' => true,
        'teammate_no_response_ai_takeover_timeout_seconds' => 180,
        'business_hours' => [
            'timezone' => 'Asia/Shanghai',
            'outside_hours_notice' => '当前不是人工服务时间。',
            'schedule' => [
                ['day' => 1, 'enabled' => true, 'open' => '09:00', 'close' => '18:00'],
                ['day' => 2, 'enabled' => true, 'open' => '09:00', 'close' => '18:00'],
                ['day' => 3, 'enabled' => true, 'open' => '09:00', 'close' => '18:00'],
                ['day' => 4, 'enabled' => true, 'open' => '09:00', 'close' => '18:00'],
                ['day' => 5, 'enabled' => true, 'open' => '09:00', 'close' => '18:00'],
                ['day' => 6, 'enabled' => false, 'open' => '09:00', 'close' => '18:00'],
                ['day' => 7, 'enabled' => false, 'open' => '09:00', 'close' => '18:00'],
            ],
        ],
    ]);

    $plan = ReceptionPlan::factory()->create([
        'name' => '原始名称',
    ]);

    $this->actingAs($this->user)
        ->from(route('app.manage.reception.plans.index', [
            'plan' => $plan->id,
        ]))
        ->put(route('app.manage.reception.plans.update', ['plan' => $plan->id]), [
            'name' => '更新后的名称',
            'description' => '更新后说明',
            'persona_display_name' => '小 B',
            'persona_tone' => 'concise',
            'global_instructions' => '更新后的指引',
            'strategy_config' => $strategyConfig,
        ])
        ->assertRedirect(route('app.manage.reception.plans.show', [
            'plan' => $plan->id,
        ]));

    $plan->refresh();

    expect($plan->name)->toBe('更新后的名称')
        ->and($plan->description)->toBe('更新后说明')
        ->and($plan->persona_config['display_name'])->toBe('小 B')
        ->and($plan->persona_config['tone'])->toBe('concise')
        ->and($plan->global_instructions)->toBe('更新后的指引')
        ->and($plan->strategy_config)->toMatchArray($strategyConfig);
});

test('更新接待方案时接待要求必填', function () {
    $plan = ReceptionPlan::factory()->create();

    $this->actingAs($this->user)
        ->put(route('app.manage.reception.plans.update', ['plan' => $plan->id]), [
            'name' => $plan->name,
            'persona_display_name' => '接待助手',
            'persona_tone' => 'concise',
            'global_instructions' => null,
            'strategy_config' => receptionPlanStrategyPayload(),
        ])
        ->assertSessionHasErrors(['global_instructions']);
});

test('更新接待方案会同步绑定的知识库', function () {
    createReceptionTestModel(createReceptionTestProvider());
    $plan = ReceptionPlan::factory()->create(['name' => '知识库方案']);
    $previousKnowledgeBase = KnowledgeBase::factory()->create();
    $selectedKnowledgeBase = KnowledgeBase::factory()->create();
    $plan->knowledgeBases()->attach($previousKnowledgeBase);

    $this->actingAs($this->user)
        ->put(route('app.manage.reception.plans.update', ['plan' => $plan->id]), [
            'name' => $plan->name,
            'persona_display_name' => '接待助手',
            'persona_tone' => 'concise',
            'global_instructions' => '保持友好简洁',
            'strategy_config' => receptionPlanStrategyPayload(),
            'knowledge_base_ids' => [$selectedKnowledgeBase->id],
        ])
        ->assertRedirect();

    expect($plan->knowledgeBases()->pluck('knowledge_bases.id')->all())
        ->toBe([(string) $selectedKnowledgeBase->id]);
});

test('更新接待方案时不能使用回收站方案的名称', function () {
    $deletedPlan = ReceptionPlan::factory()->create(['name' => '回收站方案']);
    $deletedPlan->delete();
    $plan = ReceptionPlan::factory()->create(['name' => '当前方案']);

    $this->actingAs($this->user)
        ->put(route('app.manage.reception.plans.update', ['plan' => $plan->id]), [
            'name' => '回收站方案',
            'persona_display_name' => '接待助手',
            'persona_tone' => 'concise',
            'global_instructions' => '保持友好简洁',
            'strategy_config' => receptionPlanStrategyPayload(),
        ])
        ->assertSessionHasErrors(['name']);

    expect($plan->refresh()->name)->toBe('当前方案');
});

test('保存接待方案即生成新版本快照且配置无变化时不新增版本', function () {
    createReceptionTestModel(createReceptionTestProvider());

    $plan = ReceptionPlan::factory()->create([
        'name' => '保存即发布方案',
    ]);

    $payload = [
        'name' => '保存即发布方案',
        'description' => '说明',
        'persona_display_name' => '小 A',
        'persona_tone' => 'friendly',
        'global_instructions' => '保持简洁',
        'strategy_config' => receptionPlanStrategyPayload(),
    ];

    // 首次保存：生成 v1
    $this->actingAs($this->user)
        ->put(route('app.manage.reception.plans.update', ['plan' => $plan->id]), $payload)
        ->assertRedirect();

    expect(ReceptionPlanVersion::query()->where('reception_plan_id', $plan->id)->count())->toBe(1);

    // 配置无变化的重复保存：不新增版本
    $this->actingAs($this->user)
        ->put(route('app.manage.reception.plans.update', ['plan' => $plan->id]), $payload)
        ->assertRedirect();

    expect(ReceptionPlanVersion::query()->where('reception_plan_id', $plan->id)->count())->toBe(1);

    // 改动配置再保存：递增到 v2
    $this->actingAs($this->user)
        ->put(route('app.manage.reception.plans.update', ['plan' => $plan->id]), [
            ...$payload,
            'global_instructions' => '换一段全新的指引',
        ])
        ->assertRedirect();

    $latest = ReceptionPlanVersion::query()->where('reception_plan_id', $plan->id)->orderByDesc('version_number')->first();
    expect(ReceptionPlanVersion::query()->where('reception_plan_id', $plan->id)->count())->toBe(2)
        ->and($latest->version_number)->toBe(2)
        ->and($latest->compiled_config['reception_instruction'])->toContain('换一段全新的指引');
});

test('所有者可以删除接待方案当没有版本引用时', function () {
    $plan = ReceptionPlan::factory()->create([
    ]);

    $this->actingAs($this->user)
        ->delete(route('app.manage.reception.plans.destroy', ['plan' => $plan->id]))
        ->assertRedirect(route('app.manage.reception.plans.index', []));

    $this->assertSoftDeleted('reception_plans', ['id' => $plan->id]);
});

test('所有者可以查看接待方案回收站并恢复方案', function () {
    $plan = ReceptionPlan::factory()->create([
        'name' => '已删除接待方案',
    ]);
    ReceptionPlanVersion::factory()->create([
        'reception_plan_id' => $plan->id,
        'version_number' => 1,
    ]);
    $plan->delete();

    $this->actingAs($this->user)
        ->get(route('app.manage.reception.plans.trash', [
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reception/plans/Trash')
            ->has('trashed_plan_list', 1)
            ->where('trashed_plan_list.0.id', (string) $plan->id)
            ->where('trashed_plan_list.0.name', '已删除接待方案')
            ->where('trashed_plan_list_pagination.total', 1)
        );

    $this->actingAs($this->user)
        ->from(route('app.manage.reception.plans.trash', [
        ]))
        ->put(route('app.manage.reception.plans.restore', [
            'plan' => $plan->id,
        ]))
        ->assertRedirect(route('app.manage.reception.plans.trash', [
        ]));

    expect(ReceptionPlan::query()->whereKey($plan->id)->exists())->toBeTrue()
        ->and(ReceptionPlan::onlyTrashed()->whereKey($plan->id)->exists())->toBeFalse();
});

test('当方案仍被进行中的会话使用时阻止删除', function () {
    $plan = ReceptionPlan::factory()->create([
    ]);
    $version = ReceptionPlanVersion::factory()->create([
        'reception_plan_id' => $plan->id,
        'version_number' => 1,
    ]);
    Conversation::factory()->create([
        'reception_plan_version_id' => $version->id,
        'status' => ConversationStatus::Open,
    ]);

    $this->actingAs($this->user)
        ->from(route('app.manage.reception.plans.index', []))
        ->withHeader('X-Inertia', 'true')
        ->delete(route('app.manage.reception.plans.destroy', ['plan' => $plan->id]))
        ->assertRedirect()
        ->assertSessionHasErrors('toast');

    expect(ReceptionPlan::query()->whereKey($plan->id)->exists())->toBeTrue();
});

test('历史会话使用过方案时仍可删除方案', function () {
    $plan = ReceptionPlan::factory()->create();
    $version = ReceptionPlanVersion::factory()->create([
        'reception_plan_id' => $plan->id,
        'version_number' => 1,
    ]);
    Conversation::factory()->closed()->create([
        'reception_plan_version_id' => $version->id,
    ]);

    $this->actingAs($this->user)
        ->delete(route('app.manage.reception.plans.destroy', ['plan' => $plan->id]))
        ->assertRedirect(route('app.manage.reception.plans.index'));

    $this->assertSoftDeleted('reception_plans', ['id' => $plan->id]);
});

test('当方案仍被渠道绑定时阻止删除', function () {
    $plan = ReceptionPlan::factory()->create([
    ]);
    Channel::factory()->create([
        'reception_plan_id' => $plan->id,
    ]);

    $this->actingAs($this->user)
        ->from(route('app.manage.reception.plans.index', []))
        ->withHeader('X-Inertia', 'true')
        ->delete(route('app.manage.reception.plans.destroy', ['plan' => $plan->id]))
        ->assertRedirect()
        ->assertSessionHasErrors('toast');

    expect(ReceptionPlan::query()->whereKey($plan->id)->exists())->toBeTrue();
});

test('保存接待方案生成的版本快照含编译后的运行时配置', function () {
    createReceptionTestModel(createReceptionTestProvider());
    $strategyConfig = receptionPlanStrategyPayload([
        'reception_mode' => ReceptionRoutingMode::TeammateFirst->value,
        'unassigned_ai_takeover_enabled' => true,
        'unassigned_ai_takeover_timeout_seconds' => 60,
    ]);

    $plan = ReceptionPlan::factory()->create([
        'name' => '可发布方案',
    ]);

    $this->actingAs($this->user)
        ->put(route('app.manage.reception.plans.update', ['plan' => $plan->id]), [
            'name' => '可发布方案',
            'description' => '发布用方案说明',
            'persona_display_name' => '小 A',
            'persona_tone' => 'friendly',
            'global_instructions' => '保持简洁',
            'strategy_config' => $strategyConfig,
        ])
        ->assertRedirect();

    $version = ReceptionPlanVersion::query()->where('reception_plan_id', $plan->id)->firstOrFail();

    expect($version->version_number)->toBe(1)
        ->and($version->status)->toBe(ReceptionPlanVersionStatus::Published)
        ->and($version->published_by_user_id)->toBe($this->user->id)
        ->and($version->compiled_config['reception_instruction'])->toContain('小 A')
        ->and($version->compiled_config['reception_instruction'])->toContain('保持简洁')
        ->and($version->snapshot_config['name'])->toBe('可发布方案')
        ->and($version->snapshot_config['strategy_config'])->toBe($strategyConfig);
});
