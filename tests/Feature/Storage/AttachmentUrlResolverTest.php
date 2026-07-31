<?php

use App\Enums\AttachmentStatus;
use App\Models\Attachment;
use App\Services\Storage\AttachmentUrlResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('本地附件返回稳定内容地址并保留响应头', function () {
    fakeAttachmentStorage();

    $attachment = Attachment::factory()->create([
        'object_key' => 'attachments/conversation_file/report.pdf',
        'original_name' => '报告.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'byte_size' => 100,
        'status' => AttachmentStatus::Uploaded,
    ]);
    $attachment->filesystem()->put($attachment->object_key, 'report');

    $url = app(AttachmentUrlResolver::class)->url($attachment);

    expect($url)->toBe(route('attachments.content', ['attachment' => $attachment->id]));

    $this->get($url)
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', Attachment::dispositionFor('application/pdf', '报告.pdf'));

    expect($attachment->filesystem()->get($attachment->object_key))->toBe('report');
});

it('内联图片用 inline，其余类型按原始文件名 attachment 下载', function () {
    expect(Attachment::dispositionFor('image/png', 'photo.png'))->toStartWith('inline')
        ->and(Attachment::dispositionFor('application/pdf', '报告.pdf'))->toStartWith('attachment')
        ->and(Attachment::dispositionFor('application/pdf', '报告.pdf'))->toContain(rawurlencode('报告.pdf'))
        ->and(Attachment::dispositionFor('image/svg+xml', 'x.svg'))->toStartWith('attachment');
});
