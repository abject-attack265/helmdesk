<?php

namespace App\Services\KnowledgeBase;

use App\Data\KnowledgeBase\KnowledgeEngineConfigData;
use App\Settings\GeneralSettings;

/**
 * 知识库引擎入口：把系统设置中的完整引擎配置包成 BoundKnowledgeEngine。
 *
 * 向量模型、维度、索引开关和分块参数来自系统设置；系统未配置时使用代码默认值。
 *
 * 向量模型须 pin：embedding 一换/维度一变历史向量索引即失效，需按应用重建；
 * 运行时只在「同 model_id + 同维度」的活跃备份间轮询做容灾/分摊。
 */
class KnowledgeEngine
{
    /**
     * 取系统当前生效的引擎配置。
     */
    public function current(): BoundKnowledgeEngine
    {
        return new BoundKnowledgeEngine(
            app(GeneralSettings::class)->knowledge_engine_config ?? KnowledgeEngineConfigData::default(),
        );
    }

    /**
     * 取代码/环境级默认引擎配置。
     */
    public function defaults(): BoundKnowledgeEngine
    {
        return new BoundKnowledgeEngine(KnowledgeEngineConfigData::default());
    }
}
