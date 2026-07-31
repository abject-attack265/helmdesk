<?php

declare(strict_types=1);

return [
    'sources' => [
        'manual' => 'Created manually',
        'system' => 'Created by system',
        'ai' => 'Created by AI',
        'import' => 'Imported',
        'channel' => 'Channel parameter',
    ],
    'scopes' => [
        'conversation' => 'Conversation',
        'contact' => 'Contact',
    ],
    'default_groups' => [
        'channel' => 'Channel parameters',
    ],
    'errors' => [
        'name_exists' => 'A tag with this name already exists in this group',
        'locked_cannot_delete' => 'This tag is locked and cannot be deleted',
        'locked_cannot_be_merged' => 'This tag is locked and cannot be merged into another tag',
        'merge_same_tag' => 'Choose two different tags',
        'restore_name_conflict' => 'A tag with this name already exists in the original group. Rename the existing tag first.',
        'group_name_exists' => 'A tag group with this name already exists',
        'group_scope_mismatch' => 'The tags are used for different types of items. Choose tags used for the same type of item.',
        'group_not_empty' => 'Move or delete the tags in this group before deleting it',
        'group_required' => 'Choose a tag group',
    ],
    'merge_success' => 'Tags merged successfully',
    'restore_success' => 'Tag restored successfully',
];
