<?php

use App\Actions\Experience\ExtractExperienceCandidatesAction;
use App\Data\Experience\ExperienceExtractionWindowData;
use App\Enums\AiModelPurpose;
use App\Enums\ExperienceCandidateStatus;
use App\Enums\ExperienceExtractionStatus;
use App\Enums\MessageRole;
use App\Enums\ReceptionLanguage;
use App\Jobs\Experience\ExtractInstanceExperienceJob;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ExperienceCandidate;
use App\Models\ExperienceExtraction;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeQaEntry;
use App\Models\User;
use App\Services\Ai\NeuronStructuredGenerator;
use App\Services\Ai\Schemas\ExperienceCandidateSchema;
use App\Services\Ai\Schemas\ExperienceExtractionSchema;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->withoutVite();
    $this->owner = $this->createUserWithInstance();
    // 经验提炼绑定问答知识库：页面与运行都在该库作用域下。
    $this->qaKb = KnowledgeBase::factory()->qa()->create([
        'name' => '客服问答库',
    ]);
});

/**
 * 造一条指定时间关闭的会话，并按 role 追加文本消息。
 *
 * 提炼以联系人为单位，故会话必须挂在联系人下；不传 $contact 时新建一个（即「各自独立的联系人」）。
 *
 * @param  list<array{role: MessageRole, content: string}>  $messages
 */
function seedClosedConversationWithMessages(GeneralSettings $app, Carbon $closedAt, array $messages, ?Contact $contact = null): Conversation
{
    $contact ??= Contact::factory()->create();

    $conversation = Conversation::factory()
        ->forContact($contact)
        ->create(['closed_at' => $closedAt]);

    foreach ($messages as $message) {
        ConversationMessage::factory()
            ->forConversation($conversation)
            ->create([
                'role' => $message['role'],
                'content' => $message['content'],
            ]);
    }

    return $conversation;
}

/**
 * 造一条有人工文本、指定时间关闭的会话（最常用形态的简写）。
 */
function seedHumanHandledConversation(GeneralSettings $app, Carbon $closedAt, string $teammateText = '人工处理答复。', ?Contact $contact = null): Conversation
{
    return seedClosedConversationWithMessages($app, $closedAt, [
        ['role' => MessageRole::Visitor, 'content' => '访客问题。'],
        ['role' => MessageRole::Teammate, 'content' => $teammateText],
    ], $contact);
}

/**
 * 触发提炼的请求体：勾选的联系人 + 时间窗口。
 *
 * @param  list<Contact>  $contacts
 * @return array{contact_ids: list<string>, from: string, to: string}
 */
function startExtractionPayload(array $contacts, ?Carbon $from = null, ?Carbon $to = null): array
{
    return [
        'contact_ids' => array_map(static fn (Contact $contact): string => (string) $contact->id, $contacts),
        'from' => ($from ?? now()->subDays(30))->toDateString(),
        'to' => ($to ?? now())->toDateString(),
    ];
}

/**
 * 构造一份提炼 LLM 返回的结构化结果。
 *
 * @param  list<array{question: string, similar_questions?: list<string>, answer: string, conversation_ids: list<string>}>  $candidates
 */
function makeExtractionSchemaResult(array $candidates): ExperienceExtractionSchema
{
    $schema = new ExperienceExtractionSchema;
    foreach ($candidates as $item) {
        $candidate = new ExperienceCandidateSchema;
        $candidate->question = $item['question'];
        $candidate->similar_questions = $item['similar_questions'] ?? [];
        $candidate->answer = $item['answer'];
        $candidate->conversation_ids = $item['conversation_ids'];
        $schema->candidates[] = $candidate;
    }

    return $schema;
}

test('任务列表页只展示绑定问答库下的提炼运行历史与候选处理进度', function () {
    $conversation = seedHumanHandledConversation($this->instance, now()->subHours(4));
    $extraction = ExperienceExtraction::factory()
        ->create([
            'knowledge_base_id' => $this->qaKb->id,
            'candidate_count' => 2,
            'conversation_count' => 1,
            'triggered_by_user_id' => $this->owner->id,
        ]);
    $extraction->conversations()->attach($conversation->id, ['created_at' => now()]);
    ExperienceCandidate::factory()->create([
        'extraction_id' => $extraction->id,
        'status' => ExperienceCandidateStatus::Pending,
    ]);
    ExperienceCandidate::factory()->create([
        'extraction_id' => $extraction->id,
        'status' => ExperienceCandidateStatus::Adopted,
    ]);
    // 同应用其它问答库的任务不出现在本库列表。
    ExperienceExtraction::factory()->create();

    $this->actingAs($this->owner)
        ->get(route('app.manage.experience-extraction.index', [
            'knowledgeBase' => $this->qaKb->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('experiences/Index')
            ->where('knowledge_base.id', (string) $this->qaKb->id)
            ->where('knowledge_base.name', '客服问答库')
            // 左侧资源管理器需要完整知识库树（本库 + 其它库任务自动补建的库）。
            ->has('sidebar.knowledge_base_list', 2)
            ->has('extractions', 1)
            ->where('extractions.0.id', (string) $extraction->id)
            ->where('extractions.0.status', 'completed')
            ->where('extractions.0.conversation_count', 1)
            ->where('extractions.0.candidate_count', 2)
            ->where('extractions.0.pending_candidate_count', 1)
            ->where('extractions.0.triggered_by_name', $this->owner->name)
            ->etc()
        );
});

test('创建任务页按联系人展示可选列表与按库计算的已提炼标记', function () {
    $freshContact = Contact::factory()->create();
    $fresh = seedHumanHandledConversation($this->instance, now()->subHours(3), contact: $freshContact);

    $extractedContact = Contact::factory()->create();
    $extracted = seedHumanHandledConversation($this->instance, now()->subHours(4), contact: $extractedContact);

    // 从头到尾都没有人工参与的联系人不进入可选列表。
    seedClosedConversationWithMessages($this->instance, now()->subHours(2), [
        ['role' => MessageRole::Visitor, 'content' => '你们支持哪些支付方式？'],
        ['role' => MessageRole::Ai, 'content' => '支持微信与支付宝。'],
    ]);

    $extraction = ExperienceExtraction::factory()->create([
        'knowledge_base_id' => $this->qaKb->id,
    ]);
    $extraction->conversations()->attach($extracted->id, ['created_at' => now()]);
    // 其它问答库提炼过 fresh 会话，不影响本库的「已提炼过」标记。
    $otherKbExtraction = ExperienceExtraction::factory()->create();
    $otherKbExtraction->conversations()->attach($fresh->id, ['created_at' => now()]);

    $this->actingAs($this->owner)
        ->get(route('app.manage.experience-extraction.create', [
            'knowledgeBase' => $this->qaKb->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('experiences/Create')
            ->where('knowledge_base.id', (string) $this->qaKb->id)
            ->has('selectable_contacts', 2)
            ->where('selectable_contacts.0.id', (string) $freshContact->id)
            ->where('selectable_contacts.0.conversation_count', 1)
            ->where('selectable_contacts.0.already_extracted', false)
            ->where('selectable_contacts.0.conversations.0.id', (string) $fresh->id)
            ->where('selectable_contacts.1.id', (string) $extractedContact->id)
            ->where('selectable_contacts.1.already_extracted', true)
            ->where('selectable_pagination.total', 2)
            ->where('selectable_pagination.current_page', 1)
            ->where('selectable_pagination.last_page', 1)
            ->where('has_running_extraction', false)
            ->etc()
        );
});

test('联系人被自动关闭切开的多条会话会归到同一项，没有人工消息的提问会话也带上', function () {
    $contact = Contact::factory()->create();

    // 提问后访客沉默，会话被空闲自动关闭，整条没有人工消息。
    $question = seedClosedConversationWithMessages($this->instance, now()->subDays(3), [
        ['role' => MessageRole::Visitor, 'content' => '你们的发票怎么开？'],
        ['role' => MessageRole::Ai, 'content' => '正在为您转接人工。'],
    ], $contact);

    // 访客隔天再来，新开一条会话，人工在这条里作答。
    $answer = seedClosedConversationWithMessages($this->instance, now()->subDays(2), [
        ['role' => MessageRole::Visitor, 'content' => '上次那个问题怎么弄？'],
        ['role' => MessageRole::Teammate, 'content' => '在订单详情页点「申请发票」即可。'],
    ], $contact);

    $this->actingAs($this->owner)
        ->get(route('app.manage.experience-extraction.create', [
            'knowledgeBase' => $this->qaKb->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('selectable_contacts', 1)
            ->where('selectable_contacts.0.id', (string) $contact->id)
            // 两条都在，且按关闭时间正序——提问在前、答复在后。
            ->where('selectable_contacts.0.conversation_count', 2)
            ->where('selectable_contacts.0.conversations.0.id', (string) $question->id)
            ->where('selectable_contacts.0.conversations.1.id', (string) $answer->id)
            ->etc()
        );
});

test('可选联系人列表按页返回，超出首页的联系人可通过翻页取到', function () {
    // 12 个 > 每页 10，确保跨出首页。
    $closedAt = Carbon::parse('2026-07-10 10:00:00');
    foreach (range(1, 12) as $index) {
        seedHumanHandledConversation($this->instance, $closedAt->copy()->subMinutes($index));
    }

    $createUrl = route('app.manage.experience-extraction.create', [
        'knowledgeBase' => $this->qaKb->id,
        'from' => '2026-07-01',
        'to' => '2026-07-11',
    ]);

    $this->actingAs($this->owner)
        ->get($createUrl)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('selectable_contacts', 10)
            ->where('selectable_pagination.total', 12)
            ->where('selectable_pagination.current_page', 1)
            ->where('selectable_pagination.last_page', 2)
            ->etc()
        );

    $this->actingAs($this->owner)
        ->get($createUrl.'&page=2')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('selectable_contacts', 2)
            ->where('selectable_pagination.total', 12)
            ->where('selectable_pagination.current_page', 2)
            ->etc()
        );
});

test('时间窗口筛选会限制可选联系人列表', function () {
    $inRange = seedHumanHandledConversation($this->instance, Carbon::parse('2026-07-05 10:00:00'));
    seedHumanHandledConversation($this->instance, Carbon::parse('2026-07-01 10:00:00'));

    $this->actingAs($this->owner)
        ->get(route('app.manage.experience-extraction.create', [
            'knowledgeBase' => $this->qaKb->id,
            'from' => '2026-07-03',
            'to' => '2026-07-06',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('selectable_contacts', 1)
            ->where('selectable_contacts.0.id', (string) $inRange->contact_id)
            ->where('window.from', '2026-07-03')
            ->where('window.to', '2026-07-06')
            ->etc()
        );
});

test('未传时间窗口时缺省取最近一个月，且不受上次运行截止时间限制', function () {
    $this->travelTo(now());
    $recent = seedHumanHandledConversation($this->instance, now()->subHours(3));
    $old = seedHumanHandledConversation($this->instance, now()->subDays(10));
    // 已完成运行的截止时间晚于老会话关闭时间，但不应成为隐藏的筛选下限。
    ExperienceExtraction::factory()->create([
        'scanned_until' => now()->subDay(),
    ]);

    $tz = $this->owner->resolvedTimezone();
    $expectedWindow = ExperienceExtractionWindowData::normalize(null, null, $tz);

    $this->actingAs($this->owner)
        ->get(route('app.manage.experience-extraction.create', ['knowledgeBase' => $this->qaKb->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('selectable_contacts', 2)
            ->where('selectable_contacts.0.id', (string) $recent->contact_id)
            ->where('selectable_contacts.1.id', (string) $old->contact_id)
            ->where('window.from', $expectedWindow->from)
            ->where('window.to', $expectedWindow->to)
            ->etc()
        );
});

test('时间窗口跨度超过上限时收敛到上限并回显给前端', function () {
    $withinLimit = seedHumanHandledConversation($this->instance, Carbon::parse('2026-07-20 10:00:00'));
    // 落在用户请求的窗口内、但在收敛后的窗口之外，不应出现。
    seedHumanHandledConversation($this->instance, Carbon::parse('2026-05-10 10:00:00'));

    $this->actingAs($this->owner)
        ->get(route('app.manage.experience-extraction.create', [
            'knowledgeBase' => $this->qaKb->id,
            'from' => '2026-05-01',
            'to' => '2026-07-31',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('window.from', '2026-07-01')
            ->where('window.to', '2026-07-31')
            ->where('max_window_days', ExperienceExtractionWindowData::MAX_WINDOW_DAYS)
            ->has('selectable_contacts', 1)
            ->where('selectable_contacts.0.id', (string) $withinLimit->contact_id)
            ->etc()
        );
});

test('会话被提炼后重新打开续聊再关闭会重新变回可选', function () {
    $contact = Contact::factory()->create();
    $conversation = seedHumanHandledConversation($this->instance, now()->subDays(5), contact: $contact);

    $extraction = ExperienceExtraction::factory()->create([
        'knowledge_base_id' => $this->qaKb->id,
    ]);
    $extraction->conversations()->attach($conversation->id, ['created_at' => now()->subDays(4)]);

    $createUrl = route('app.manage.experience-extraction.create', [
        'knowledgeBase' => $this->qaKb->id,
    ]);

    $this->actingAs($this->owner)
        ->get($createUrl)
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectable_contacts.0.conversations.0.already_extracted', true)
            ->etc()
        );

    // 重开续聊后再次关闭：closed_at 晚于登记时间，说明有未提炼过的新内容。
    $conversation->update(['closed_at' => now()->subDay()]);

    $this->actingAs($this->owner)
        ->get($createUrl)
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectable_contacts.0.conversations.0.already_extracted', false)
            ->where('selectable_contacts.0.already_extracted', false)
            ->etc()
        );
});

test('会话清单页只读展示任务消费的会话', function () {
    $conversation = seedHumanHandledConversation($this->instance, now()->subHours(4));
    $extraction = ExperienceExtraction::factory()->create();
    $extraction->conversations()->attach($conversation->id, ['created_at' => now()]);

    $this->actingAs($this->owner)
        ->get(route('app.manage.experience-extraction.conversations', [
            'extraction' => $extraction->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('experiences/Conversations')
            ->where('extraction.id', (string) $extraction->id)
            ->has('conversations', 1)
            ->where('conversations.0.id', (string) $conversation->id)
            ->etc()
        );
});

test('经验结果页只展示该任务的候选并按状态筛选', function () {
    $extraction = ExperienceExtraction::factory()->create();
    $pending = ExperienceCandidate::factory()->create([
        'extraction_id' => $extraction->id,
        'question' => '如何申请退款？',
        'status' => ExperienceCandidateStatus::Pending,
    ]);
    ExperienceCandidate::factory()->create([
        'extraction_id' => $extraction->id,
        'status' => ExperienceCandidateStatus::Discarded,
    ]);
    // 其它任务的候选不出现在本任务结果页。
    ExperienceCandidate::factory()->create([
        'extraction_id' => ExperienceExtraction::factory()->create()->id,
        'status' => ExperienceCandidateStatus::Pending,
    ]);

    $this->actingAs($this->owner)
        ->get(route('app.manage.experience-extraction.results', [
            'extraction' => $extraction->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('experiences/Results')
            ->where('extraction.id', (string) $extraction->id)
            ->has('candidates', 1)
            ->where('candidates.0.id', (string) $pending->id)
            ->where('status_counts.pending', 1)
            ->where('status_counts.discarded', 1)
            ->where('active_status', 'pending')
            ->etc()
        );
});

test('坐席与关键词筛选会限制可选联系人列表', function () {
    $teammate = User::factory()->create();
    attachMember($teammate);

    $teammateContact = Contact::factory()->create();
    $byTeammate = Conversation::factory()
        ->forContact($teammateContact)
        ->create(['closed_at' => now()->subHours(2), 'subject' => '退款流程咨询']);
    ConversationMessage::factory()->forConversation($byTeammate)->create([
        'role' => MessageRole::Teammate,
        'sender_user_id' => $teammate->id,
        'content' => '请在订单页申请退款。',
    ]);

    $ownerContact = Contact::factory()->create();
    $byOwner = Conversation::factory()
        ->forContact($ownerContact)
        ->create(['closed_at' => now()->subHours(3), 'subject' => '发货时间咨询']);
    ConversationMessage::factory()->forConversation($byOwner)->create([
        'role' => MessageRole::Teammate,
        'sender_user_id' => $this->owner->id,
        'content' => '一般 48 小时内发货。',
    ]);

    // 按坐席筛选：只剩该坐席服务过的联系人。
    $this->actingAs($this->owner)
        ->get(route('app.manage.experience-extraction.create', [
            'knowledgeBase' => $this->qaKb->id,
            'teammate' => (string) $teammate->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('selectable_contacts', 1)
            ->where('selectable_contacts.0.id', (string) $teammateContact->id)
            ->where('filter_teammate_user_id', (string) $teammate->id)
            ->etc()
        );

    // 按关键词筛选会话主题，命中的会话决定其联系人入选。
    $this->actingAs($this->owner)
        ->get(route('app.manage.experience-extraction.create', [
            'knowledgeBase' => $this->qaKb->id,
            'search' => '发货',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('selectable_contacts', 1)
            ->where('selectable_contacts.0.id', (string) $ownerContact->id)
            ->where('filter_search', '发货')
            ->etc()
        );
});

test('成员可以查看经验提炼页面', function () {
    $member = User::factory()->create();
    attachMember($member);

    $this->actingAs($member)
        ->get(route('app.manage.experience-extraction.index', ['knowledgeBase' => $this->qaKb->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('experiences/Index')
            ->etc()
        );
});

test('对勾选的联系人触发提炼会展开成窗口内会话、创建 Running 运行并派发任务', function () {
    Bus::fake([ExtractInstanceExperienceJob::class]);
    makeAiModel(AiModelPurpose::BackgroundTask);

    $contact = Contact::factory()->create();
    // 同一联系人被切开的两条会话：提问那条没有人工消息，也要一并登记。
    $question = seedClosedConversationWithMessages($this->instance, now()->subDays(2)->startOfSecond(), [
        ['role' => MessageRole::Visitor, 'content' => '发票怎么开？'],
    ], $contact);
    $answer = seedHumanHandledConversation($this->instance, now()->subDay()->startOfSecond(), contact: $contact);
    // 窗口外的会话不登记。
    seedHumanHandledConversation($this->instance, now()->subDays(60), contact: $contact);

    $this->actingAs($this->owner)
        ->post(
            route('app.manage.experience-extraction.start', ['knowledgeBase' => $this->qaKb->id]),
            startExtractionPayload([$contact]),
        )
        ->assertRedirect(route('app.manage.experience-extraction.index', ['knowledgeBase' => $this->qaKb->id]));

    $running = ExperienceExtraction::query()

        ->where('status', ExperienceExtractionStatus::Running)
        ->firstOrFail();

    expect($running->conversations()->pluck('conversation_id')->sort()->values()->all())
        ->toBe(collect([(string) $question->id, (string) $answer->id])->sort()->values()->all())
        // 运行绑定发起时所在的问答库。
        ->and((string) $running->knowledge_base_id)->toBe((string) $this->qaKb->id)
        ->and($running->scanned_from?->equalTo($question->closed_at))->toBeTrue()
        ->and($running->scanned_until?->equalTo($answer->closed_at))->toBeTrue()
        // 创建时即写入展开后的会话数，供页面「正在分析 N 个会话」展示。
        ->and($running->conversation_count)->toBe(2);

    Bus::assertDispatched(ExtractInstanceExperienceJob::class, fn (ExtractInstanceExperienceJob $job) => $job->extractionId === (string) $running->id);
});

test('触发提炼会把操作者界面语言传给提炼任务', function () {
    Bus::fake([ExtractInstanceExperienceJob::class]);
    makeAiModel(AiModelPurpose::BackgroundTask);
    $this->owner->update(['locale' => 'en']);
    $conversation = seedHumanHandledConversation($this->instance, now()->subDay());

    $this->actingAs($this->owner)
        ->post(
            route('app.manage.experience-extraction.start', ['knowledgeBase' => $this->qaKb->id]),
            startExtractionPayload([$conversation->contact]),
        )
        ->assertRedirect();

    Bus::assertDispatched(
        ExtractInstanceExperienceJob::class,
        fn (ExtractInstanceExperienceJob $job) => $job->language === ReceptionLanguage::English,
    );
});

test('窗口内没有人工参与过的联系人时触发会被拒绝', function () {
    Bus::fake([ExtractInstanceExperienceJob::class]);
    makeAiModel(AiModelPurpose::BackgroundTask);

    $valid = seedHumanHandledConversation($this->instance, now()->subDay());
    // 只有 AI 回过：整个联系人都没有可提炼的人工处理。
    $aiOnly = seedClosedConversationWithMessages($this->instance, now()->subDay(), [
        ['role' => MessageRole::Ai, 'content' => 'AI 自动回复。'],
    ]);
    // 有人工消息但会话未关闭，不算数。
    $openContact = Contact::factory()->create();
    $open = Conversation::factory()->forContact($openContact)->create(['closed_at' => null]);
    ConversationMessage::factory()->forConversation($open)->create([
        'role' => MessageRole::Teammate,
        'content' => '未关闭会话的人工消息。',
    ]);
    // 有人工参与但整段落在窗口之外。
    $outOfWindow = seedHumanHandledConversation($this->instance, now()->subDays(90));

    $invalidContacts = [$aiOnly->contact, $openContact, $outOfWindow->contact];

    foreach ($invalidContacts as $invalid) {
        $this->actingAs($this->owner)
            ->post(
                route('app.manage.experience-extraction.start', ['knowledgeBase' => $this->qaKb->id]),
                startExtractionPayload([$valid->contact, $invalid]),
            )
            ->assertUnprocessable();
    }

    expect(ExperienceExtraction::query()->count())->toBe(0);
    Bus::assertNothingDispatched();
});

test('已有进行中的运行时再次触发会被拒绝（跨知识库共享应用级锁）', function () {
    Bus::fake([ExtractInstanceExperienceJob::class]);
    makeAiModel(AiModelPurpose::BackgroundTask);
    // 进行中的运行绑定在其它问答库，仍会阻断本库的新触发。
    ExperienceExtraction::factory()->running()->create();
    $conversation = seedHumanHandledConversation($this->instance, now()->subDay());

    $this->actingAs($this->owner)
        ->post(
            route('app.manage.experience-extraction.start', ['knowledgeBase' => $this->qaKb->id]),
            startExtractionPayload([$conversation->contact]),
        )
        ->assertUnprocessable();

    expect(ExperienceExtraction::query()->count())->toBe(1);
    Bus::assertNothingDispatched();
});

test('没有可用后台模型时触发会被拒绝', function () {
    Bus::fake([ExtractInstanceExperienceJob::class]);
    $conversation = seedHumanHandledConversation($this->instance, now()->subDay());

    $this->actingAs($this->owner)
        ->post(
            route('app.manage.experience-extraction.start', ['knowledgeBase' => $this->qaKb->id]),
            startExtractionPayload([$conversation->contact]),
        )
        ->assertUnprocessable();

    Bus::assertNothingDispatched();
});

test('成员可以触发提炼', function () {
    Bus::fake([ExtractInstanceExperienceJob::class]);
    makeAiModel(AiModelPurpose::BackgroundTask);
    $conversation = seedHumanHandledConversation($this->instance, now()->subDay());
    $member = User::factory()->create();
    attachMember($member);

    $this->actingAs($member)
        ->post(
            route('app.manage.experience-extraction.start', ['knowledgeBase' => $this->qaKb->id]),
            startExtractionPayload([$conversation->contact]),
        )
        ->assertRedirect();

    Bus::assertDispatched(ExtractInstanceExperienceJob::class);
});

test('提炼任务把同一联系人被切开的会话连成一段转录送进模型', function () {
    makeAiModel(AiModelPurpose::BackgroundTask);

    $contact = Contact::factory()->create();
    // 提问后访客沉默、被自动关闭，整条没有人工消息。
    $question = seedClosedConversationWithMessages($this->instance, now()->subDays(3), [
        ['role' => MessageRole::Visitor, 'content' => '我要退款怎么操作？'],
        ['role' => MessageRole::Ai, 'content' => '正在为您转接人工。'],
    ], $contact);
    // 访客隔天再来另开一条，人工在这条里作答。
    $answer = seedClosedConversationWithMessages($this->instance, now()->subDays(2), [
        ['role' => MessageRole::Visitor, 'content' => '上次那个问题怎么弄？'],
        ['role' => MessageRole::Teammate, 'content' => '您好，请在订单详情页点击「申请退款」提交即可。'],
    ], $contact);

    $capturedInput = null;
    $this->mock(NeuronStructuredGenerator::class, function ($mock) use (&$capturedInput, $answer) {
        $mock->shouldReceive('generate')
            ->once()
            ->andReturnUsing(function ($model, $instructions, $userMessage) use (&$capturedInput, $answer) {
                $capturedInput = $userMessage;

                return makeExtractionSchemaResult([[
                    'question' => '如何申请退款？',
                    'answer' => '请在订单详情页点击「申请退款」提交。',
                    'conversation_ids' => [(string) $answer->id],
                ]]);
            });
    });
    $this->mock(ReceptionRealtimeNotifier::class, function ($mock) {
        $mock->shouldReceive('appChanged')->once();
    });

    $extraction = ExperienceExtraction::factory()->running()->create();
    $extraction->conversations()->attach([
        (string) $question->id => ['created_at' => now()],
        (string) $answer->id => ['created_at' => now()],
    ]);

    app(ExtractExperienceCandidatesAction::class)->handle($extraction, ReceptionLanguage::ChineseSimplified);

    // 两条会话归到同一个联系人标题下，提问那条（无人工消息）也在，且排在答复之前。
    expect($capturedInput)->toContain('## 联系人 '.$contact->id)
        ->toContain((string) $question->id)
        ->toContain('我要退款怎么操作？')
        ->toContain((string) $answer->id);
    expect(strpos($capturedInput, (string) $question->id))
        ->toBeLessThan(strpos($capturedInput, (string) $answer->id));

    $extraction->refresh();
    expect($extraction->conversation_count)->toBe(2)
        ->and($extraction->candidate_count)->toBe(1);
});

test('访客长文本把人工答复挤出字符预算时该联系人仍会被提炼', function () {
    makeAiModel(AiModelPurpose::BackgroundTask);

    $conversation = seedClosedConversationWithMessages($this->instance, now()->subDay(), [
        // 访客先刷满单会话字符预算，人工答复会被截断挤出转录，但「这条有人工处理过」的事实不变。
        ['role' => MessageRole::Visitor, 'content' => str_repeat('问', 6200)],
        ['role' => MessageRole::Teammate, 'content' => '请在订单详情页点击「申请退款」。'],
    ]);

    $this->mock(NeuronStructuredGenerator::class, function ($mock) {
        $mock->shouldReceive('generate')->once()->andReturn(makeExtractionSchemaResult([]));
    });
    $this->mock(ReceptionRealtimeNotifier::class, function ($mock) {
        $mock->shouldReceive('appChanged')->once();
    });

    $extraction = ExperienceExtraction::factory()->running()->create();
    $extraction->conversations()->attach($conversation->id, ['created_at' => now()]);

    app(ExtractExperienceCandidatesAction::class)->handle($extraction, ReceptionLanguage::ChineseSimplified);

    expect($extraction->refresh()->conversation_count)->toBe(1);
});

test('转录分批时同一联系人的会话不会被切进两批', function () {
    makeAiModel(AiModelPurpose::BackgroundTask);

    // 每人 2 条约 6000 字符的会话（约 12000/人），6 人合计约 72000 > 单批预算 60000，必然分成两批。
    $conversationIdsByContact = [];
    foreach (range(1, 6) as $index) {
        $contact = Contact::factory()->create();
        $ids = [];
        foreach (range(1, 2) as $seq) {
            $ids[] = (string) seedClosedConversationWithMessages($this->instance, now()->subDays($index)->addMinutes($seq), [
                ['role' => MessageRole::Visitor, 'content' => str_repeat('问', 5000)],
                ['role' => MessageRole::Teammate, 'content' => str_repeat('答', 900)],
            ], $contact)->id;
        }
        $conversationIdsByContact[(string) $contact->id] = $ids;
    }

    $batches = [];
    $this->mock(NeuronStructuredGenerator::class, function ($mock) use (&$batches) {
        $mock->shouldReceive('generate')->andReturnUsing(function ($model, $instructions, $userMessage) use (&$batches) {
            $batches[] = $userMessage;

            return makeExtractionSchemaResult([]);
        });
    });
    $this->mock(ReceptionRealtimeNotifier::class, function ($mock) {
        $mock->shouldReceive('appChanged')->once();
    });

    $extraction = ExperienceExtraction::factory()->running()->create();
    $extraction->conversations()->attach(array_fill_keys(
        array_merge(...array_values($conversationIdsByContact)),
        ['created_at' => now()],
    ));

    app(ExtractExperienceCandidatesAction::class)->handle($extraction, ReceptionLanguage::ChineseSimplified);

    expect(count($batches))->toBeGreaterThan(1);

    // 每个联系人只出现在一批里，且其全部会话都在同一批——被批次边界切开就等于白改。
    foreach ($conversationIdsByContact as $contactId => $ids) {
        $containing = array_values(array_filter(
            $batches,
            static fn (string $batch): bool => str_contains($batch, '## 联系人 '.$contactId),
        ));

        expect($containing)->toHaveCount(1);
        foreach ($ids as $id) {
            expect($containing[0])->toContain($id);
        }
    }
});

test('单个联系人转录超出单批预算时丢弃其较早的会话', function () {
    makeAiModel(AiModelPurpose::BackgroundTask);

    $contact = Contact::factory()->create();
    // 同一人 11 条约 6000 字符的会话合计约 66000 > 单批预算 60000，最早的会被丢掉。
    $conversations = [];
    foreach (range(1, 11) as $index) {
        $conversations[] = seedClosedConversationWithMessages($this->instance, now()->subDays(12 - $index), [
            ['role' => MessageRole::Visitor, 'content' => str_repeat('问', 5000)],
            ['role' => MessageRole::Teammate, 'content' => str_repeat('答', 900)],
        ], $contact);
    }

    $capturedInput = null;
    $this->mock(NeuronStructuredGenerator::class, function ($mock) use (&$capturedInput) {
        $mock->shouldReceive('generate')->once()->andReturnUsing(function ($model, $instructions, $userMessage) use (&$capturedInput) {
            $capturedInput = $userMessage;

            return makeExtractionSchemaResult([]);
        });
    });
    $this->mock(ReceptionRealtimeNotifier::class, function ($mock) {
        $mock->shouldReceive('appChanged')->once();
    });

    $extraction = ExperienceExtraction::factory()->running()->create();
    $extraction->conversations()->attach(array_fill_keys(
        array_map(static fn (Conversation $c): string => (string) $c->id, $conversations),
        ['created_at' => now()],
    ));

    app(ExtractExperienceCandidatesAction::class)->handle($extraction, ReceptionLanguage::ChineseSimplified);

    // 答复通常在最近的会话里，故保留最新、丢弃最早。
    expect($capturedInput)
        ->not->toContain((string) array_first($conversations)->id)
        ->toContain((string) array_last($conversations)->id);
    expect($extraction->refresh()->conversation_count)->toBeLessThan(count($conversations));
});

test('提炼任务只分析运行登记的会话并落库清洗后的候选', function () {
    makeAiModel(AiModelPurpose::BackgroundTask);

    $selected = seedClosedConversationWithMessages($this->instance, now()->subHours(5), [
        ['role' => MessageRole::Visitor, 'content' => '我要退款怎么操作？'],
        ['role' => MessageRole::Teammate, 'content' => '您好，请在订单详情页点击「申请退款」提交即可。'],
    ]);
    // 未勾选的会话不进入转录。
    $unselected = seedHumanHandledConversation($this->instance, now()->subHours(4), '未勾选会话的人工答复。');

    $capturedInput = null;
    $this->mock(NeuronStructuredGenerator::class, function ($mock) use (&$capturedInput, $selected, $unselected) {
        $mock->shouldReceive('generate')
            ->once()
            ->andReturnUsing(function ($model, $instructions, $userMessage) use (&$capturedInput, $selected, $unselected) {
                $capturedInput = $userMessage;

                return makeExtractionSchemaResult([
                    [
                        'question' => '如何申请退款？',
                        'similar_questions' => ['怎么退款？', '如何申请退款？'],
                        'answer' => '请在订单详情页点击「申请退款」提交。',
                        // 混入幻觉 ID 和未勾选会话 ID，验证清洗只保留本次运行实际分析的会话。
                        'conversation_ids' => [(string) $selected->id, 'bogus-id', (string) $unselected->id],
                    ],
                    [
                        'question' => '空答案候选应被剔除',
                        'answer' => '   ',
                        'conversation_ids' => [],
                    ],
                ]);
            });
    });
    $this->mock(ReceptionRealtimeNotifier::class, function ($mock) {
        $mock->shouldReceive('appChanged')->once()
            ->with('experience_extraction_finished');
    });

    $extraction = ExperienceExtraction::factory()
        ->running()
        ->create();
    $extraction->conversations()->attach($selected->id, ['created_at' => now()]);

    app(ExtractExperienceCandidatesAction::class)->handle($extraction, ReceptionLanguage::ChineseSimplified);

    $extraction->refresh();
    expect($extraction->status)->toBe(ExperienceExtractionStatus::Completed)
        ->and($extraction->conversation_count)->toBe(1)
        ->and($extraction->candidate_count)->toBe(1);

    // 转录只含登记的会话。
    expect($capturedInput)->toContain((string) $selected->id)
        ->toContain('请在订单详情页点击「申请退款」提交即可')
        ->not->toContain((string) $unselected->id);

    $candidate = ExperienceCandidate::query()->where('extraction_id', $extraction->id)->sole();
    expect($candidate->status)->toBe(ExperienceCandidateStatus::Pending)
        ->and($candidate->question)->toBe('如何申请退款？')
        // 与主问题重复的相似问法被剔除。
        ->and($candidate->similar_questions)->toBe(['怎么退款？'])
        ->and($candidate->source_conversation_ids)->toBe([(string) $selected->id])
        ->and($candidate->conversation_count)->toBe(1);
});

test('登记的会话都没有有效人工文本时运行直接完成且不调用模型', function () {
    makeAiModel(AiModelPurpose::BackgroundTask);

    $aiOnly = seedClosedConversationWithMessages($this->instance, now()->subHours(2), [
        ['role' => MessageRole::Visitor, 'content' => '在吗？'],
        ['role' => MessageRole::Ai, 'content' => '您好，请问有什么可以帮您？'],
    ]);

    $this->mock(NeuronStructuredGenerator::class, function ($mock) {
        $mock->shouldNotReceive('generate');
    });
    $this->mock(ReceptionRealtimeNotifier::class, function ($mock) {
        $mock->shouldReceive('appChanged')->once();
    });

    $extraction = ExperienceExtraction::factory()
        ->running()
        ->create();
    $extraction->conversations()->attach($aiOnly->id, ['created_at' => now()]);

    app(ExtractExperienceCandidatesAction::class)->handle($extraction, ReceptionLanguage::ChineseSimplified);

    $extraction->refresh();
    expect($extraction->status)->toBe(ExperienceExtractionStatus::Completed)
        ->and($extraction->conversation_count)->toBe(0)
        ->and($extraction->candidate_count)->toBe(0);
});

test('采纳候选会在任务绑定的问答库创建问答对并回写候选状态', function () {
    bootTntSearch();
    setKnowledgeEngine(vectorIndexEnabled: false, raptorIndexEnabled: false);

    $candidate = ExperienceCandidate::factory()
        ->create([
            'extraction_id' => ExperienceExtraction::factory()->create([
                'knowledge_base_id' => $this->qaKb->id,
            ])->id,
        ]);

    $this->actingAs($this->owner)
        ->put(route('app.manage.experience-extraction.candidates.adopt', [
            'candidate' => $candidate->id,
        ]), [
            'question' => '润色后的主问题？',
            'similar_questions' => ['另一种问法？'],
            'answer' => '润色后的标准答复。',
        ])
        // 无 referer 时回落到该任务的经验结果页。
        ->assertRedirect(route('app.manage.experience-extraction.results', [
            'extraction' => (string) $candidate->extraction_id,
        ]));

    $candidate->refresh();
    expect($candidate->status)->toBe(ExperienceCandidateStatus::Adopted)
        ->and((string) $candidate->handled_by_user_id)->toBe((string) $this->owner->id)
        ->and($candidate->adopted_qa_entry_id)->not->toBeNull();

    // 候选结果写入任务绑定的问答库。
    $entry = KnowledgeQaEntry::query()->findOrFail($candidate->adopted_qa_entry_id);
    expect($entry->question)->toBe('润色后的主问题？')
        ->and((string) $entry->knowledge_base_id)->toBe((string) $this->qaKb->id)
        ->and($entry->similarQuestions()->pluck('question')->all())->toBe(['另一种问法？'])
        ->and($entry->answers()->pluck('answer')->all())->toBe(['润色后的标准答复。']);

    flushTntSearch();
});

test('经验提炼页面在非问答库下打开会 404', function () {
    $standardKb = KnowledgeBase::factory()->create([
        'name' => '普通文档库',
    ]);

    $this->actingAs($this->owner)
        ->get(route('app.manage.experience-extraction.index', [
            'knowledgeBase' => $standardKb->id,
        ]))
        ->assertNotFound();

    $this->actingAs($this->owner)
        ->post(route('app.manage.experience-extraction.start', [
            'knowledgeBase' => $standardKb->id,
        ]), startExtractionPayload([seedHumanHandledConversation($this->instance, now()->subDay())->contact]))
        ->assertNotFound();

    expect(ExperienceExtraction::query()->count())->toBe(0);
});

test('采纳未绑定问答库的存量候选会被拒绝', function () {
    $candidate = ExperienceCandidate::factory()
        ->create([
            'extraction_id' => ExperienceExtraction::factory()->create([
                'knowledge_base_id' => null,
            ])->id,
        ]);

    $this->actingAs($this->owner)
        ->put(route('app.manage.experience-extraction.candidates.adopt', [
            'candidate' => $candidate->id,
        ]), [
            'question' => '问题？',
            'similar_questions' => [],
            'answer' => '答案。',
        ])
        ->assertUnprocessable();

    expect($candidate->refresh()->status)->toBe(ExperienceCandidateStatus::Pending);
});

test('删除任务会连同候选与会话登记一并移除，进行中的任务被拒绝', function () {
    $conversation = seedHumanHandledConversation($this->instance, now()->subHours(4));
    $extraction = ExperienceExtraction::factory()->create([
        'knowledge_base_id' => $this->qaKb->id,
    ]);
    $extraction->conversations()->attach($conversation->id, ['created_at' => now()]);
    ExperienceCandidate::factory()->create([
        'extraction_id' => $extraction->id,
    ]);

    $this->actingAs($this->owner)
        ->delete(route('app.manage.experience-extraction.destroy', [
            'extraction' => $extraction->id,
        ]))
        ->assertRedirect(route('app.manage.experience-extraction.index', ['knowledgeBase' => $this->qaKb->id]));

    expect(ExperienceExtraction::query()->find($extraction->id))->toBeNull()
        ->and(ExperienceCandidate::query()->where('extraction_id', $extraction->id)->count())->toBe(0)
        ->and(DB::table('experience_extraction_conversations')->where('extraction_id', $extraction->id)->count())->toBe(0);

    // 进行中的任务不能删除。
    $running = ExperienceExtraction::factory()->running()->create();
    $this->actingAs($this->owner)
        ->delete(route('app.manage.experience-extraction.destroy', [
            'extraction' => $running->id,
        ]))
        ->assertUnprocessable();

    expect(ExperienceExtraction::query()->find($running->id))->not->toBeNull();
});

test('丢弃候选仅改状态且已处理过的候选不能再次处理', function () {
    $candidate = ExperienceCandidate::factory()
        ->create([
            'extraction_id' => ExperienceExtraction::factory()->create()->id,
        ]);

    $this->actingAs($this->owner)
        ->put(route('app.manage.experience-extraction.candidates.discard', [
            'candidate' => $candidate->id,
        ]))
        ->assertRedirect();

    $candidate->refresh();
    expect($candidate->status)->toBe(ExperienceCandidateStatus::Discarded)
        ->and((string) $candidate->handled_by_user_id)->toBe((string) $this->owner->id)
        ->and(KnowledgeQaEntry::query()->count())->toBe(0);

    // 已处理过的候选再次丢弃会被拒绝。
    $this->actingAs($this->owner)
        ->put(route('app.manage.experience-extraction.candidates.discard', [
            'candidate' => $candidate->id,
        ]))
        ->assertUnprocessable();
});
