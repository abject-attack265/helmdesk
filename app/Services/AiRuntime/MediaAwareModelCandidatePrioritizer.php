<?php

namespace App\Services\AiRuntime;

use App\Data\AiRuntime\RuntimeModelCandidateData;

/**
 * 将满足当前媒体输入要求的模型候选稳定地排在候选列表前部。
 */
class MediaAwareModelCandidatePrioritizer
{
    /**
     * 按图片和视频输入要求稳定分组，组内保留模型池的加权随机顺序。
     *
     * @param  list<RuntimeModelCandidateData>  $candidates
     * @return list<RuntimeModelCandidateData>
     */
    public function prioritize(
        array $candidates,
        bool $requiresImageInput,
        bool $requiresVideoInput,
    ): array {
        if (! $requiresImageInput && ! $requiresVideoInput) {
            return $candidates;
        }

        $preferred = [];
        $fallback = [];

        foreach ($candidates as $candidate) {
            $supportsRequiredMedia = (! $requiresImageInput || $candidate->supports_image_input)
                && (! $requiresVideoInput || $candidate->supports_video_input);

            if ($supportsRequiredMedia) {
                $preferred[] = $candidate;
            } else {
                $fallback[] = $candidate;
            }
        }

        return [...$preferred, ...$fallback];
    }
}
