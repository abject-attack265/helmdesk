<?php

use App\Actions\Attachment\AttachUploadedAttachmentsAction;
use App\Enums\AttachmentStatus;
use App\Models\Attachment;
use App\Models\Channel;
use App\Services\Storage\AttachmentUrlResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->user = $this->createUserWithInstance();
    fakeAttachmentStorage();
});

/**
 * 模拟浏览器直传：把内容写到附件绑定的存储配置。
 */
function putUploadedObject(string $attachmentId, string $contents): Attachment
{
    $attachment = Attachment::query()->findOrFail($attachmentId);
    $attachment->filesystem()->put($attachment->object_key, $contents);

    return $attachment;
}

/**
 * 生成真实 PNG 字节，供 finalize 的内容嗅探识别为 image/png。
 */
function fakePngBytes(int $width = 64, int $height = 64): string
{
    // 用变量持有 UploadedFile，避免临时文件在读取前被析构清理。
    $file = UploadedFile::fake()->image('photo.png', $width, $height);

    return (string) file_get_contents($file->getRealPath());
}

test('直传 presign 到 finalize 完成并返回本地文件访问地址', function () {
    $contents = 'hello attachment';

    $presign = $this->actingAs($this->user)
        ->postJson('/api/attachments/presign', [
            'purpose' => 'conversation_file',
            'file_name' => 'note.txt',
            'mime_type' => 'text/plain',
            'byte_size' => strlen($contents),
            'context' => [],
        ])
        ->assertOk();

    $attachmentId = $presign->json('attachment_id');
    expect($presign->json('upload_url'))
        ->toContain('/attachments/pending/')
        ->toContain('expiration=');

    $pendingAttachment = putUploadedObject($attachmentId, $contents);
    $pendingObjectKey = $pendingAttachment->object_key;

    $this->actingAs($this->user)
        ->postJson('/api/attachments/'.$attachmentId.'/finalize')
        ->assertOk()
        ->assertJsonPath('attachment.status', 'uploaded')
        ->assertJsonPath('attachment.id', $attachmentId);

    $attachment = Attachment::query()->findOrFail($attachmentId);
    expect($attachment->status)->toBe(AttachmentStatus::Uploaded)
        ->and((string) $attachment->uploaded_by_user_id)->toBe((string) $this->user->id)
        ->and($attachment->object_key)->toContain('/conversation_file/')
        ->and($attachment->object_key)->not->toBe($pendingObjectKey);

    $attachment->filesystem()
        ->assertExists($attachment->object_key)
        ->assertMissing($pendingObjectKey);

    $url = app(AttachmentUrlResolver::class)->url($attachment);
    expect($url)->toBe(route('attachments.content', ['attachment' => $attachment->id]));
});

test('presign 强制校验用途对应的大小上限', function () {
    $this->actingAs($this->user)
        ->postJson('/api/attachments/presign', [
            'purpose' => 'avatar',
            'file_name' => 'avatar.png',
            'mime_type' => 'image/png',
            'byte_size' => (2 * 1024 * 1024) + 1,
            'context' => [],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('byte_size');
});

test('presign 拒绝危险与白名单外的 MIME', function () {
    foreach (['text/html', 'image/svg+xml', 'application/octet-stream'] as $mime) {
        $this->actingAs($this->user)
            ->postJson('/api/attachments/presign', [
                'purpose' => 'conversation_file',
                'file_name' => 'x',
                'mime_type' => $mime,
                'byte_size' => 1024,
                'context' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mime_type');
    }
});

test('知识库文档直传使用 20MB 文档规则', function () {
    $contents = '# Hello';

    $presign = $this->actingAs($this->user)
        ->postJson('/api/attachments/presign', [
            'purpose' => 'knowledge_document',
            'file_name' => 'guide.md',
            'mime_type' => 'text/markdown',
            'byte_size' => strlen($contents),
            'context' => [],
        ])
        ->assertOk();

    $attachment = Attachment::query()->findOrFail($presign->json('attachment_id'));
    expect($attachment->purpose->value)->toBe('knowledge_document');

    $this->actingAs($this->user)
        ->postJson('/api/attachments/presign', [
            'purpose' => 'knowledge_document',
            'file_name' => 'oversized.pdf',
            'mime_type' => 'application/pdf',
            'byte_size' => (20 * 1024 * 1024) + 1,
            'context' => [],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('byte_size');

    $this->actingAs($this->user)
        ->postJson('/api/attachments/presign', [
            'purpose' => 'knowledge_document',
            'file_name' => 'page.html',
            'mime_type' => 'text/html',
            'byte_size' => strlen('<h1>Hello</h1>'),
            'context' => [],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('mime_type');
});

test('finalize 按真实内容拦截伪装成图片的 SVG', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

    $presign = $this->actingAs($this->user)
        ->postJson('/api/attachments/presign', [
            'purpose' => 'conversation_image',
            'file_name' => 'fake.png',
            'mime_type' => 'image/png',
            'byte_size' => strlen($svg),
            'context' => [],
        ])
        ->assertOk();

    $attachmentId = $presign->json('attachment_id');
    $attachment = putUploadedObject($attachmentId, $svg);

    $this->actingAs($this->user)
        ->postJson('/api/attachments/'.$attachmentId.'/finalize')
        ->assertUnprocessable();

    expect(Attachment::query()->findOrFail($attachmentId)->status)->toBe(AttachmentStatus::Failed);
    $attachment->filesystem()->assertMissing($attachment->object_key);
});

test('finalize 按真实字节大小拦截超限图片', function () {
    $contents = fakePngBytes().str_repeat("\0", (2 * 1024 * 1024) + 1);

    $presign = $this->actingAs($this->user)
        ->postJson('/api/attachments/presign', [
            'purpose' => 'avatar',
            'file_name' => 'avatar.png',
            'mime_type' => 'image/png',
            'byte_size' => 1024,
            'context' => [],
        ])
        ->assertOk();

    $attachmentId = $presign->json('attachment_id');
    putUploadedObject($attachmentId, $contents);

    $this->actingAs($this->user)
        ->postJson('/api/attachments/'.$attachmentId.'/finalize')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('byte_size');

    expect(Attachment::query()->findOrFail($attachmentId)->status)->toBe(AttachmentStatus::Failed);
});

test('finalize 后旧直传地址只能覆盖已隔离的临时对象', function () {
    $contents = 'original';
    $presign = $this->actingAs($this->user)
        ->postJson('/api/attachments/presign', [
            'purpose' => 'conversation_file',
            'file_name' => 'note.txt',
            'mime_type' => 'text/plain',
            'byte_size' => strlen($contents),
            'context' => [],
        ])
        ->assertOk();

    $attachmentId = $presign->json('attachment_id');
    $pending = putUploadedObject($attachmentId, $contents);
    $pendingObjectKey = $pending->object_key;

    $this->actingAs($this->user)
        ->postJson('/api/attachments/'.$attachmentId.'/finalize')
        ->assertOk();

    $attachment = Attachment::query()->findOrFail($attachmentId);
    $attachment->filesystem()->put($pendingObjectKey, 'overwritten');

    expect($attachment->filesystem()->get($attachment->object_key))->toBe($contents)
        ->and($attachment->filesystem()->get($pendingObjectKey))->toBe('overwritten');

    $this->travel(16)->minutes();
    $this->artisan('attachments:cleanup')->assertSuccessful();

    $attachment->filesystem()->assertMissing($pendingObjectKey);
    expect($attachment->fresh()->upload_object_key)->toBeNull();
});

test('已认证 presign 路由需要已登录用户', function () {
    $this->postJson('/api/attachments/presign', [
        'purpose' => 'conversation_file',
        'file_name' => 'note.txt',
        'mime_type' => 'text/plain',
        'byte_size' => 5,
        'context' => [],
    ])->assertUnauthorized();
});

test('访客直传用访客会话Cookie', function () {
    $token = str_repeat('a', 32);
    $channel = Channel::factory()->create([]);
    $png = fakePngBytes();

    $presign = $this->withCredentials()
        ->withUnencryptedCookie('helmdesk_visitor_'.$channel->code, $token)
        ->postJson('/api/visitor/attachments/presign', [
            'purpose' => 'conversation_image',
            'file_name' => 'photo.png',
            'mime_type' => 'image/png',
            'byte_size' => strlen($png),
            'context' => [
                'channel_code' => $channel->code,
            ],
        ])
        ->assertOk();

    $attachmentId = $presign->json('attachment_id');
    $attachment = Attachment::query()->findOrFail($attachmentId);
    expect($attachment->uploaded_by_user_id)->toBeNull()
        ->and($attachment->session_token_hash)->toBe(hash('sha256', $token));

    putUploadedObject($attachmentId, $png);

    $this->withUnencryptedCookie('helmdesk_visitor_'.$channel->code, $token)
        ->postJson('/api/visitor/attachments/'.$attachmentId.'/finalize')
        ->assertOk()
        ->assertJsonPath('attachment.status', 'uploaded');
});

test('访客直传接受请求头会话 token', function () {
    $token = str_repeat('c', 32);
    $channel = Channel::factory()->create([]);
    $png = fakePngBytes();

    $presign = $this->withHeader('X-Helmdesk-Visitor-Token', $token)
        ->postJson('/api/visitor/attachments/presign', [
            'purpose' => 'conversation_image',
            'file_name' => 'photo.png',
            'mime_type' => 'image/png',
            'byte_size' => strlen($png),
            'context' => [
                'channel_code' => $channel->code,
            ],
        ])
        ->assertOk();

    $attachmentId = $presign->json('attachment_id');
    putUploadedObject($attachmentId, $png);

    $this->withHeader('X-Helmdesk-Visitor-Token', $token)
        ->postJson('/api/visitor/attachments/'.$attachmentId.'/finalize')
        ->assertOk()
        ->assertJsonPath('attachment.status', 'uploaded');

    expect(Attachment::query()->findOrFail($attachmentId)->session_token_hash)->toBe(hash('sha256', $token));
});

test('访客 presign 限制同一会话令牌未完成附件数量', function () {
    $token = str_repeat('f', 32);
    $channel = Channel::factory()->create();

    Attachment::factory()->count(20)->create([
        'uploaded_by_user_id' => null,
        'session_token_hash' => hash('sha256', $token),
        'status' => AttachmentStatus::Pending,
        'attachable_id' => null,
        'attachable_type' => null,
    ]);

    $this->withHeader('X-Helmdesk-Visitor-Token', $token)
        ->postJson('/api/visitor/attachments/presign', [
            'purpose' => 'conversation_file',
            'file_name' => 'note.txt',
            'mime_type' => 'text/plain',
            'byte_size' => 4,
            'context' => [
                'channel_code' => $channel->code,
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('attachment');
});

test('已认证用户可以完成系统级图片上传', function () {
    $png = fakePngBytes(120, 60);

    $this->actingAs($this->user, 'web');

    $presign = $this->postJson('/api/attachments/presign', [
        'purpose' => 'channel_icon',
        'file_name' => 'logo.png',
        'mime_type' => 'image/png',
        'byte_size' => strlen($png),
        'context' => [],
    ])->assertOk();

    $attachmentId = $presign->json('attachment_id');
    putUploadedObject($attachmentId, $png);

    $this->postJson('/api/attachments/'.$attachmentId.'/finalize')
        ->assertOk()
        ->assertJsonPath('attachment.status', 'uploaded');

    $attachment = Attachment::query()->findOrFail($attachmentId);
    $attachment->filesystem()->assertExists($attachment->object_key);
});

test('后台附件只能由原上传者绑定', function () {
    $otherUser = $this->createUserWithInstance();
    $attachment = Attachment::factory()->create([
        'uploaded_by_user_id' => $this->user->id,
        'status' => AttachmentStatus::Uploaded,
        'attachable_id' => null,
        'attachable_type' => null,
    ]);
    $channel = Channel::factory()->create();

    expect(fn () => app(AttachUploadedAttachmentsAction::class)->handle(
        $channel,
        (string) $attachment->id,
        actor: $otherUser,
    ))->toThrow(ValidationException::class);

    expect($attachment->fresh()->status)->toBe(AttachmentStatus::Uploaded)
        ->and($attachment->fresh()->attachable_id)->toBeNull();
});

test('清理删除过期未绑定附件（含放弃的 presign 占位）', function () {
    $pending = Attachment::factory()->create([
        'status' => AttachmentStatus::Pending,
        'object_key' => 'attachments/avatar/abandoned.png',
        'expires_at' => now()->subMinute(),
        'attachable_id' => null,
        'attachable_type' => null,
    ]);
    $pending->filesystem()->put($pending->object_key, 'abandoned');

    $this->artisan('attachments:cleanup')->assertSuccessful();

    $deleted = Attachment::withTrashed()->findOrFail($pending->id);
    expect($deleted->status)->toBe(AttachmentStatus::Deleted)
        ->and($deleted->trashed())->toBeTrue();
    $pending->filesystem()->assertMissing($pending->object_key);
});

test('清理删除过期的失败附件记录', function () {
    $failed = Attachment::factory()->create([
        'status' => AttachmentStatus::Failed,
        'object_key' => 'attachments/pending/failed-object',
        'expires_at' => now()->subMinute(),
        'attachable_id' => null,
        'attachable_type' => null,
    ]);

    $this->artisan('attachments:cleanup')->assertSuccessful();

    $deleted = Attachment::withTrashed()->findOrFail($failed->id);
    expect($deleted->status)->toBe(AttachmentStatus::Deleted)
        ->and($deleted->trashed())->toBeTrue();
});
