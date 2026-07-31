<?php

use App\Data\AiRuntime\RuntimeModelCandidateData;
use App\Enums\AiProviderProtocol;
use App\Services\AiRuntime\MediaAwareModelCandidatePrioritizer;

/**
 * 构造带媒体能力的模型候选。
 */
function mediaPriorityCandidate(
    string $id,
    bool $supportsImage,
    bool $supportsVideo,
): RuntimeModelCandidateData {
    return new RuntimeModelCandidateData(
        protocol: AiProviderProtocol::OpenAI,
        credentials: ['key' => 'test'],
        model_id: $id,
        supports_image_input: $supportsImage,
        supports_video_input: $supportsVideo,
        ai_model_id: $id,
    );
}

/**
 * 提取候选顺序。
 *
 * @param  list<RuntimeModelCandidateData>  $candidates
 * @return list<string>
 */
function mediaPriorityIds(array $candidates): array
{
    return array_map(
        static fn (RuntimeModelCandidateData $candidate): string => $candidate->ai_model_id,
        $candidates,
    );
}

test('纯文本轮次保持模型池的加权顺序', function () {
    $candidates = [
        mediaPriorityCandidate('text', false, false),
        mediaPriorityCandidate('image', true, false),
        mediaPriorityCandidate('video', false, true),
    ];

    $prioritized = (new MediaAwareModelCandidatePrioritizer)->prioritize($candidates, false, false);

    expect(mediaPriorityIds($prioritized))->toBe(['text', 'image', 'video']);
});

test('图片轮次优先支持图片的候选并保持组内顺序', function () {
    $candidates = [
        mediaPriorityCandidate('text-1', false, false),
        mediaPriorityCandidate('image-1', true, false),
        mediaPriorityCandidate('video', false, true),
        mediaPriorityCandidate('both', true, true),
        mediaPriorityCandidate('image-2', true, false),
        mediaPriorityCandidate('text-2', false, false),
    ];

    $prioritized = (new MediaAwareModelCandidatePrioritizer)->prioritize($candidates, true, false);

    expect(mediaPriorityIds($prioritized))
        ->toBe(['image-1', 'both', 'image-2', 'text-1', 'video', 'text-2']);
});

test('视频轮次优先支持视频的候选并保持组内顺序', function () {
    $candidates = [
        mediaPriorityCandidate('image', true, false),
        mediaPriorityCandidate('video-1', false, true),
        mediaPriorityCandidate('text', false, false),
        mediaPriorityCandidate('both', true, true),
        mediaPriorityCandidate('video-2', false, true),
    ];

    $prioritized = (new MediaAwareModelCandidatePrioritizer)->prioritize($candidates, false, true);

    expect(mediaPriorityIds($prioritized))
        ->toBe(['video-1', 'both', 'video-2', 'image', 'text']);
});

test('图视频混合轮次优先同时支持两种输入的候选', function () {
    $candidates = [
        mediaPriorityCandidate('image', true, false),
        mediaPriorityCandidate('both-1', true, true),
        mediaPriorityCandidate('video', false, true),
        mediaPriorityCandidate('both-2', true, true),
        mediaPriorityCandidate('text', false, false),
    ];

    $prioritized = (new MediaAwareModelCandidatePrioritizer)->prioritize($candidates, true, true);

    expect(mediaPriorityIds($prioritized))
        ->toBe(['both-1', 'both-2', 'image', 'video', 'text']);
});
