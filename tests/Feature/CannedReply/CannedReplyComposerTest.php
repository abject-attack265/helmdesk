<?php

use App\Models\CannedReply;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->withoutVite();
    $this->owner = $this->createUserWithInstance();
});

test('search 返回当前用户可见的模版（个人 + 共享）', function () {
    $member = User::factory()->create();
    attachMember($member);
    $other = User::factory()->create();
    attachMember($other);

    CannedReply::factory()->create([
        'name' => '应用共享回复',
    ]);
    CannedReply::factory()->ownedBy($member)->create([
        'name' => '我的私有回复',
    ]);
    CannedReply::factory()->ownedBy($other)->create([
        'name' => '别人的私有',
    ]);

    $response = $this->actingAs($member)
        ->getJson(route('app.canned-replies.search', [
            'q' => '回复',
        ]));

    $response->assertOk();
    $names = collect($response->json('items'))->pluck('name')->all();
    expect($names)->toContain('应用共享回复');
    expect($names)->toContain('我的私有回复');
    expect($names)->not->toContain('别人的私有');
});

test('search 按短码前缀匹配优先于内容', function () {
    CannedReply::factory()->withShortcut('refund')->create([
        'name' => '退款流程',
        'content' => '退款相关内容',
    ]);
    CannedReply::factory()->create([
        'name' => '其它',
        'content' => 'refund 是关键字',
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson(route('app.canned-replies.search', [
            'q' => 'refund',
        ]));

    $response->assertOk();
    expect($response->json('items'))->toHaveCount(2);
});

test('use-and-render 渲染变量并自增 usage_count', function () {
    $contact = Contact::factory()->create([
        'name' => '王小明',
    ]);
    $channel = Channel::factory()->create();
    $conversation = Conversation::factory()
        ->for($contact)
        ->for($channel)
        ->create(['subject' => '商品咨询']);

    $reply = CannedReply::factory()->create([
        'content' => '你好 {{contact.name}}，关于 {{conversation.subject}}，我是 {{teammate.name}}。',
        'usage_count' => 3,
    ]);

    $response = $this->actingAs($this->owner)
        ->postJson(
            route('app.canned-replies.use-and-render', [
                'cannedReply' => $reply->id,
            ]),
            ['conversation_id' => $conversation->id]
        );

    $response->assertOk();
    expect($response->json('rendered_content'))
        ->toBe(sprintf('你好 王小明，关于 商品咨询，我是 %s。', $this->owner->name));
    expect($response->json('usage_count'))->toBe(4);
    expect($reply->fresh()->usage_count)->toBe(4);
    expect($reply->fresh()->last_used_at)->not->toBeNull();
});

test('use-and-render 拒绝访问别人的私有模版', function () {
    $other = User::factory()->create();
    attachMember($other);

    $reply = CannedReply::factory()->ownedBy($other)->create();

    $member = User::factory()->create();
    attachMember($member);

    $this->actingAs($member)
        ->postJson(
            route('app.canned-replies.use-and-render', [
                'cannedReply' => $reply->id,
            ]),
            ['conversation_id' => null]
        )
        ->assertStatus(422);

    expect($reply->fresh()->usage_count)->toBe(0);
});
