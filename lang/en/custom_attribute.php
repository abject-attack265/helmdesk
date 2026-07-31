<?php

declare(strict_types=1);

return [
    'types' => [
        'text' => 'Single-line text',
        'textarea' => 'Multi-line text',
        'number' => 'Number',
        'date' => 'Date',
        'boolean' => 'Yes / No',
        'single_select' => 'Single select',
        'multi_select' => 'Multiple select',
    ],
    'sources' => [
        'manual' => 'Manual',
        'api' => 'API',
        'import' => 'Import',
        'workflow' => 'Workflow',
        'ai' => 'AI',
        'merge' => 'Merge',
        'channel' => 'Channel parameter',
    ],
    'reserved_key' => 'The internal key ":key" is reserved by the system. Choose another one.',
    'duplicate_key' => 'The internal key ":key" is already in use. If the field is in the recycle bin, restore it or choose another key.',
    'invalid_key_format' => 'Start the internal key with a lowercase letter and use only lowercase letters, numbers, and underscores',
    'invalid_attribute_type' => 'Choose a valid input type',
    'invalid_option_config' => 'Add at least one complete option',
    'unsupported_filterable_type' => 'Only single select, yes / no, date, and number fields can be used in contact filters',
    'invalid_attribute_filter' => 'The custom field filter is not valid',
    'attribute_archived' => 'This field is in the recycle bin. Restore it before making changes.',
    'invalid_attribute_value' => 'The entry for ":name" is not valid',
    'option_code_in_use' => 'Option ":code" is used by existing contacts and cannot be changed or deleted',
    'option_code_duplicate' => 'Option keys must be unique',
    'invalid_reorder_payload' => 'The field order could not be saved. Refresh the page and try again.',
];
