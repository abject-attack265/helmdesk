<?php

declare(strict_types=1);

return [
    'types' => [
        'visitor' => 'Visitor',
        'contact' => 'Contact',
    ],
    'list_types' => [
        'all' => 'All',
        'contacts' => 'Contacts',
        'visitors' => 'Visitors',
    ],
    'sources' => [
        'web' => 'Web chat',
        'email' => 'Email',
        'api' => 'External system',
        'manual' => 'Added manually',
        'telegram' => 'Telegram',
        'wechat_official_account' => 'WeChat Official Account',
    ],
    'tag_match_modes' => [
        'any' => 'Any',
        'all' => 'All',
    ],
    'identity_types' => [
        'session' => 'Visitor identifier',
        'email' => 'Email',
        'phone' => 'Phone',
        'external_id' => 'External system ID',
        'channel_account' => 'Channel user ID',
    ],
    'anonymous_visitor' => 'Anonymous visitor',
    'anonymous_visitor_with_suffix' => 'Anonymous visitor #:suffix',
    'identity_already_exists' => 'This :type is already used by contact ":name"',
    'at_least_one_identity' => 'Enter an email address or phone number',
    'invalid_phone' => 'Please enter a valid phone number',
    'invalid_email' => 'Please enter a valid email address',
    'invalid_ai_context' => 'The customer summary format is invalid',
    'ai_context_too_large' => 'The customer summary is too long. Shorten it before saving',
    'merge_open_conversation_conflict' => 'Both contacts have an ongoing conversation in the same channel. End one before merging',
    'identity_manual_management_not_supported' => 'This information is managed by the system or channel and cannot be edited or deleted manually',
    'restore_conflict' => 'Cannot restore because :type ":value" is already used by contact ":name"',
    'namespace_required_for_external_id' => 'A channel is required for channel accounts',
];
