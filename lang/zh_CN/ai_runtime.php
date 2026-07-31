<?php

return [
    'app_ai_updated' => '应用 AI 设置已保存。',

    'invalid_app_model' => '所选模型无效或已停用，请重新选择。',
    'app_default_model_unavailable' => '应用默认模型不可用，请先在默认模型设置中修正或为接待方案单独指定模型。',

    'max_concurrency_exceeds_global' => '最大并发不能超过当前应用的 AI 最大并发配置（:max）。',
    'global_max_concurrency_below_model_limit' => '应用 AI 最大并发配置不能低于已配置的模型并发上限。模型“:model”当前设置为 :max。',

    'model_in_use_app' => '该模型正在被应用默认引用，无法停用或删除。请先更换应用默认模型。',
    'model_in_use_reception_plan' => '该模型正在被接待方案（草稿或已发布版本）引用，无法停用或删除。请先调整相关接待方案的接待 / 任务默认模型。',
    'provider_in_use_app' => '该供应商下的模型正在被应用默认引用，无法执行此操作。请先更换应用默认模型。',
    'provider_in_use_reception_plan' => '该供应商下的模型正在被接待方案引用，无法执行此操作。请先让相关接待方案切换到其他模型。',

    'model_status' => [
        'model_inactive' => '该模型已停用',
        'provider_inactive' => '该模型供应商已停用',
        'deleted' => '该模型已删除',
        'missing_after_delete' => '未配置可用模型',
    ],
];
