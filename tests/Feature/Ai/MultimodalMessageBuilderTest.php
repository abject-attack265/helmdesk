<?php

use App\Models\Attachment;
use App\Services\Ai\MultimodalMessageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use NeuronAI\Chat\Enums\SourceType;
use NeuronAI\Chat\Messages\ContentBlocks\ImageContent;
use NeuronAI\Chat\Messages\ContentBlocks\TextContent;
use NeuronAI\Chat\Messages\ContentBlocks\VideoContent;

uses(RefreshDatabase::class);

/**
 * 构造一个带公开访问地址的落库附件。
 */
function makeAttachment(string $mime, int $bytes, string $name = 'file'): Attachment
{
    return Attachment::factory()->create([
        'mime_type' => $mime,
        'byte_size' => $bytes,
        'original_name' => $name,
    ]);
}

test('图片和视频附件均使用公开 URL', function () {
    $builder = new MultimodalMessageBuilder;
    $image = makeAttachment('image/jpeg', 1024, 'pic.jpg');
    $video = makeAttachment('video/mp4', 1024);

    $blocks = $builder->attachmentBlocks([$image, $video], true, true, 'model-1');

    expect($blocks[0])->toBeInstanceOf(ImageContent::class)
        ->and($blocks[0]->sourceType)->toBe(SourceType::URL)
        ->and($blocks[0]->content)->toBe($image->full_url)
        ->and($blocks[0]->mediaType)->toBe('image/jpeg')
        ->and($blocks[1])->toBeInstanceOf(VideoContent::class)
        ->and($blocks[1]->sourceType)->toBe(SourceType::URL)
        ->and($blocks[1]->content)->toBe($video->full_url)
        ->and($blocks[1]->mediaType)->toBe('video/mp4');
});

test('超体积视频退回文字占位，不内联', function () {
    $builder = new MultimodalMessageBuilder;
    $bigVideo = makeAttachment('video/mp4', 80 * 1024 * 1024, 'huge.mp4');
    Log::spy();

    $blocks = $builder->attachmentBlocks([$bigVideo], true, true, 'model-1');

    expect($blocks[0])->toBeInstanceOf(TextContent::class)
        ->and($blocks[0]->content)->toContain('huge.mp4')
        ->and($blocks[0]->content)->toContain('视频');

    Log::shouldHaveReceived('warning')
        ->with('[ai] 媒体附件超过输入体积限制，使用文字占位', Mockery::on(
            fn (array $context): bool => $context['ai_model_id'] === 'model-1'
                && $context['fallback_reason'] === 'video_size_limit_exceeded',
        ))
        ->once();
});

test('非图片/视频文件退回文字占位', function () {
    $builder = new MultimodalMessageBuilder;
    $doc = makeAttachment('application/pdf', 2048, 'report.pdf');

    $blocks = $builder->attachmentBlocks([$doc], true, true, 'model-1');

    expect($blocks[0])->toBeInstanceOf(TextContent::class)
        ->and($blocks[0]->content)->toContain('report.pdf');
});

test('模型不支持的图片和视频均退回文字占位', function () {
    $builder = new MultimodalMessageBuilder;
    $image = makeAttachment('image/jpeg', 1024, 'pic.jpg');
    $video = makeAttachment('video/mp4', 1024, 'clip.mp4');
    Log::spy();

    $blocks = $builder->attachmentBlocks([$image, $video], false, false, 'model-1');

    expect($blocks[0])->toBeInstanceOf(TextContent::class)
        ->and($blocks[0]->content)->toBe('[访客发送了一张图片：pic.jpg]')
        ->and($blocks[1])->toBeInstanceOf(TextContent::class)
        ->and($blocks[1]->content)->toBe('[访客发送了一段视频：clip.mp4]');

    Log::shouldHaveReceived('info')
        ->with('[ai] 附件使用文字占位进入模型请求', Mockery::on(
            fn (array $context): bool => $context['ai_model_id'] === 'model-1'
                && $context['fallback_reason'] === 'image_input_not_supported',
        ))
        ->once();
});
