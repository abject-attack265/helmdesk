<?php

declare(strict_types=1);

return [
    'visibilities' => [
        'app' => 'Shared with team',
        'personal' => 'Only me',
    ],
    'token_kinds' => [
        'contact' => 'Contact',
        'conversation' => 'Conversation',
        'teammate' => 'Teammate',
        'app' => 'Instance',
    ],
    'tokens' => [
        'contact_name' => 'Contact name',
        'contact_email' => 'Contact email',
        'contact_primary_phone' => 'Contact phone',
        'conversation_id' => 'Conversation ID',
        'conversation_subject' => 'Conversation subject',
        'teammate_name' => 'Current teammate',
        'app_name' => 'Instance name',
    ],
    'warnings' => [
        'missing_value' => 'Token :token has no value in this conversation; kept as-is.',
    ],
    'errors' => [
        'forbidden' => 'You do not have permission to use or manage this quick reply.',
        'shortcut_exists' => 'A quick reply with the same shortcut already exists for this availability.',
    ],
];
