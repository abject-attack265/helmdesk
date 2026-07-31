<?php

return [
    'check_success' => '检测成功',
    'validation_failed' => '验证未通过，请检查存储配置后重试。',
    'secret_required' => 'Secret Key 不能为空',
    'current_profile_required' => '对象存储已启用，但未选择存储配置',
    'current_profile_missing' => '当前存储配置不存在或已停用，请重新选择',
    'storage_not_found' => '当前存储配置不存在，请重新选择',
    'storage_key_secret_required' => '存储配置需要 Key/Secret，请先更新凭据',
    'connection_check_success' => '连接检测成功',
    'connection_check_failed' => '连接检测失败，请检查配置与网络连通性',
    'cors_check_failed' => '连接成功，但无法读取 Bucket CORS 配置；请确认密钥具备读取 CORS 配置权限。',
    'cors_direct_upload_required' => '连接成功，但 Bucket CORS 未允许当前站点（:origin）进行浏览器直传。请允许 POST、PUT 方法，Allowed Headers 包含 Content-Type，Expose Headers 包含 ETag。',
    'profile_is_active_cannot_delete' => '该配置正在被启用，无法删除',
    'profile_is_referenced_cannot_delete' => '该配置已被附件引用，无法删除',
    'profile_credentials_pair_required' => '更新凭据需要同时填写 Key 和 Secret',
    'drivers' => [
        'local' => '本地存储',
        's3' => 'S3 兼容存储',
    ],
    'status' => [
        'active' => '已启用',
        'disabled' => '已停用',
    ],
    'providers' => [
        'generic' => 'S3 兼容服务',
        'aws' => 'Amazon S3',
        'r2' => 'Cloudflare R2',
        'aliyun' => '阿里云',
        'tencent' => '腾讯云',
        'baidu' => '百度云',
        'qiniu' => '七牛云',
        'huawei' => '华为云',
        'ucloud' => 'UCloud',
        'rustfs' => 'RustFS',
    ],
];
