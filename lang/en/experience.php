<?php

return [
    'extraction_statuses' => [
        'running' => 'Running',
        'completed' => 'Completed',
        'failed' => 'Failed',
    ],
    'candidate_statuses' => [
        'pending' => 'Pending',
        'adopted' => 'Adopted',
        'discarded' => 'Discarded',
    ],
    'errors' => [
        'extraction_already_running' => 'An extraction is already running. Please wait for it to finish.',
        'cannot_delete_running' => 'A running extraction task cannot be deleted. Please wait for it to finish.',
        'no_background_model' => 'No background task model is available. Please configure one in AI model management first.',
        'candidate_already_handled' => 'This candidate has already been handled.',
        'invalid_contact_selection' => 'The selection contains contacts that do not exist, or that have no human-handled conversation within the selected period. Please refresh and select again.',
        'too_many_conversations' => 'The selected contacts have :count conversations in this period, exceeding the per-run limit of :max. Please narrow the period or select fewer contacts.',
        'qa_knowledge_base_not_found' => 'The target Q&A knowledge base does not exist or has been deleted.',
    ],
];
