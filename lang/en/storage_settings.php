<?php

return [
    'check_success' => 'Connection test succeeded.',
    'validation_failed' => 'Validation failed. Please check the storage settings and try again.',
    'secret_required' => 'Secret Key is required.',
    'current_profile_required' => 'Object storage is enabled, but no storage profile is selected.',
    'current_profile_missing' => 'The selected storage profile does not exist or is disabled.',
    'storage_not_found' => 'The selected storage profile does not exist.',
    'storage_key_secret_required' => 'The storage profile requires an access key and secret key.',
    'connection_check_success' => 'Connection test succeeded.',
    'connection_check_failed' => 'Connection test failed. Check the configuration and network connectivity.',
    'cors_check_failed' => 'The connection succeeded, but the Bucket CORS configuration could not be read. Check the permission for reading CORS settings.',
    'cors_direct_upload_required' => 'The connection succeeded, but Bucket CORS does not allow browser uploads from :origin. Allow POST and PUT, include Content-Type in Allowed Headers, and expose ETag.',
    'profile_is_active_cannot_delete' => 'The active profile cannot be deleted.',
    'profile_is_referenced_cannot_delete' => 'This profile is referenced by attachments and cannot be deleted.',
    'profile_credentials_pair_required' => 'Updating credentials requires both the access key and secret key.',
    'drivers' => [
        'local' => 'Local storage',
        's3' => 'S3-compatible storage',
    ],
    'status' => [
        'active' => 'Active',
        'disabled' => 'Disabled',
    ],
    'providers' => [
        'generic' => 'S3-compatible service',
        'aws' => 'Amazon S3',
        'r2' => 'Cloudflare R2',
        'aliyun' => 'Alibaba Cloud',
        'tencent' => 'Tencent Cloud',
        'baidu' => 'Baidu Cloud',
        'qiniu' => 'Qiniu Cloud',
        'huawei' => 'Huawei Cloud',
        'ucloud' => 'UCloud',
        'rustfs' => 'RustFS',
    ],
];
