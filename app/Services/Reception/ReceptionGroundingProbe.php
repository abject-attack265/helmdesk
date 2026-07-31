<?php

namespace App\Services\Reception;

/**
 * 单接待轮次的知识库接地探针：累计 knowledge_search 的检索次数与命中条目数，供投递前的接地观测取用。
 */
class ReceptionGroundingProbe
{
    private int $searches = 0;

    private int $hits = 0;

    /**
     * 记一次 knowledge_search 调用及其命中条目数。
     */
    public function recordSearch(int $hitCount): void
    {
        $this->searches++;
        $this->hits += $hitCount;
    }

    /**
     * 本轮是否调用过 knowledge_search。
     */
    public function searched(): bool
    {
        return $this->searches > 0;
    }

    /**
     * 本轮累计命中条目数（零表示检索无果或未检索）。
     */
    public function hitCount(): int
    {
        return $this->hits;
    }

    /**
     * 判定一段回复是否为「无据操作指引」：本轮零知识库命中、却给出了操作指引类内容。
     *
     * 模型对系统的具体产品并无内置了解，无据却给步骤极可能是凭训练记忆编造的操作路径。
     * 接待任务据此记录观测告警。
     */
    public function isUngroundedGuidance(string $reply): bool
    {
        return $this->hits === 0 && self::looksLikeOperationalGuidance($reply);
    }

    /**
     * 轻量判别回复是否为操作指引类：要求至少命中两个界面动作动词，单个动词可能是正常表述故不算。
     */
    public static function looksLikeOperationalGuidance(string $reply): bool
    {
        $verbs = ['点击', '点选', '打开', '进入', '选择', '勾选', '切换', '开启', '关闭', '设置', '找到', '前往', '菜单', '按钮', '选项', '右上角', '左上角', '下拉', '输入框'];

        $hit = 0;
        foreach ($verbs as $verb) {
            if (str_contains($reply, $verb)) {
                $hit++;
                if ($hit >= 2) {
                    return true;
                }
            }
        }

        return false;
    }
}
