<?php

return [
    'types' => [
        'web' => 'Website',
        'telegram' => 'Telegram',
        'wechat_official_account' => 'WeChat Official Account',
    ],
    'reception_languages' => [
        'zh-CN' => 'Simplified Chinese',
        'en' => 'English',
    ],
    'statuses' => [
        'active' => 'Active',
        'disabled' => 'Inactive',
    ],
    'web_visitor_identity_modes' => [
        'actual_receptionist' => 'Show the actual receptionist',
        'unified_service' => 'Always show customer service',
    ],
    'web_widget_entry_modes' => [
        'bubble' => 'Default chat button',
        'custom' => 'Use your website button',
    ],
    'web_widget_entry_positions' => [
        'right' => 'Right side of the page',
        'left' => 'Left side of the page',
    ],
    'web_widget_entry_styles' => [
        'system' => 'Default style',
        'custom' => 'Custom style',
    ],
    'web_widget_icon_sizes' => [
        'small' => 'Small (36 × 36 px)',
        'medium' => 'Medium (48 × 48 px)',
        'large' => 'Large (52 × 52 px)',
    ],
    'defaults' => [
        'assistant_name' => 'AI Assistant',
    ],
    'messages' => [
        'created' => 'Channel added.',
        'basic_saved' => 'Basic info saved.',
        'widget_saved' => 'Website embed settings saved.',
        'standalone_saved' => 'Chat link settings saved.',
        'deleted' => 'Channel deleted.',
        'restored' => 'Channel restored.',
        'status_updated' => 'Channel status updated.',
        'active_reception_plan_required' => 'Choose a reception plan for this channel first.',
        'active_reception_plan_invalid' => 'The current reception plan is unavailable. Choose another plan or finish setting it up.',
        'invalid_reception_plan_version' => 'Choose a reception plan from the current app.',
        'invalid_reception_plan' => 'Please choose a reception plan from the current app.',
        'reception_plan_no_usable_version' => 'The selected reception plan is not ready. Finish setting it up first.',
        'reception_plan_version_archived' => 'The selected reception plan is archived. Choose another plan.',
        'reception_plan_version_model_unavailable' => 'The selected reception plan is unavailable. Check its settings first.',
        'invalid_attachment' => 'Image is not available. Please re-upload.',
        'entry_icon_pair_required' => 'Upload the closed and open icons together, or leave both empty to use the default icons.',
    ],
    'query_params' => [
        'locale' => 'Pass a locale hint to the runtime',
        'name' => 'Prefill the visitor name',
        'email' => 'Prefill the visitor email',
        'external_id' => 'Pass an external visitor ID',
        'ref' => 'Pass a reference identifier',
        'utm_source' => 'Pass the UTM source',
        'utm_medium' => 'Pass the UTM medium',
        'utm_campaign' => 'Pass the UTM campaign',
    ],
    'query_param_labels' => [
        'locale' => 'locale',
        'name' => 'name',
        'email' => 'email',
        'external_id' => 'external_id',
        'ref' => 'ref',
        'utm_source' => 'utm_source',
        'utm_medium' => 'utm_medium',
        'utm_campaign' => 'utm_campaign',
    ],
    'web' => [
        'param_targets' => [
            'contact_name' => 'Contact name',
            'contact_email' => 'Contact email',
            'contact_phone' => 'Contact phone',
            'contact_external_id' => 'Contact external ID',
            'contact_importance' => 'Important contact marker',
            'attribute' => 'Custom field',
            'tag' => 'Contact tag',
        ],
        'param_trust' => [
            'signed_only' => 'Verified visitors only',
            'always' => 'All visitors',
        ],
        'param_write_modes' => [
            'only_if_empty' => 'Fill only when empty',
            'overwrite' => 'Update every time',
        ],
    ],
    'telegram' => [
        'webhook_registered' => 'Telegram bot connected.',
        'webhook_modes' => [
            'direct' => 'Connect directly with HelmDesk',
            'gateway' => 'Forward through an external system',
        ],
        'errors' => [
            'bot_token_required' => 'Enter the bot token.',
            'bot_already_connected' => 'This bot is connected to another channel. Use the original channel, or restore it from the recycle bin if it was deleted.',
            'connection_busy' => 'This channel is connecting to Telegram. Try again in a moment.',
            'invalid_bot_token' => 'Telegram could not verify this bot token. Check it and try again.',
            'webhook_registration_failed' => 'Could not connect to Telegram. Try again later.',
            'webhook_gateway_managed' => 'Messages are currently forwarded by an external system, so Telegram cannot be connected here.',
        ],
    ],
];
