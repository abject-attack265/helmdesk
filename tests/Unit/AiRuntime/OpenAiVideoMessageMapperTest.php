<?php

use App\Services\AiRuntime\OpenAiVideoMessageMapper;
use NeuronAI\Chat\Enums\SourceType;
use NeuronAI\Chat\Messages\ContentBlocks\ImageContent;
use NeuronAI\Chat\Messages\ContentBlocks\TextContent;
use NeuronAI\Chat\Messages\ContentBlocks\VideoContent;
use NeuronAI\Chat\Messages\UserMessage;

test('把图文视频混排消息映射成 OpenAI 兼容内容块（视频走 video_url）', function () {
    $message = new UserMessage([
        new TextContent('这是什么？'),
        new ImageContent('https://cdn.example.com/a.jpg', SourceType::URL, 'image/jpeg'),
        new VideoContent('https://cdn.example.com/b.mp4', SourceType::URL, 'video/mp4'),
    ]);

    $mapped = (new OpenAiVideoMessageMapper)->map([$message]);

    expect($mapped)->toHaveCount(1)
        ->and($mapped[0]['role'])->toBe('user')
        ->and($mapped[0]['content'])->toBe([
            ['type' => 'text', 'text' => '这是什么？'],
            ['type' => 'image_url', 'image_url' => ['url' => 'https://cdn.example.com/a.jpg']],
            ['type' => 'video_url', 'video_url' => ['url' => 'https://cdn.example.com/b.mp4', 'fps' => 1.0]],
        ]);
});
