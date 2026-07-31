<?php

return [
    'title' => 'Inbox',
    'subtitle' => 'Review and reply to conversations',
    'empty_list' => 'No conversations yet',
    'empty_selection' => 'Select a conversation to view its messages',
    'errors' => [
        'invalid_cursor' => 'Failed to load the conversation list. Please refresh and try again.',
        'invalid_pane' => 'Failed to load the page. Please refresh and try again.',
    ],
    'views' => [
        'pending' => 'Queued',
        'ai' => 'AI Handling',
        'mine' => 'Mine',
        'teammates' => 'Teammates',
        'closed' => 'Closed',
    ],
    'toolbar' => [
        'channel_filter' => 'Channel',
        'channel_any' => 'All channels',
        'assignee_filter' => 'Assignee',
        'assignee_any' => 'All assignees',
        'assignee_unassigned' => 'Unassigned',
        'count' => ':count conversation(s)',
    ],
    'badges' => [
        'pending' => 'Queued',
        'ai' => 'AI handling',
    ],
    'actions' => [
        'reply' => 'Send',
        'send_as_text' => 'Reply to visitor',
        'claim' => 'Start handling',
        'close' => 'Close conversation',
    ],
    'composer' => [
        'placeholder_text' => 'Type a reply. Enter to send, Shift+Enter for a new line',
        'closed_hint' => 'Conversation is closed; replies are disabled',
    ],
    'selection' => [
        'conversation_boundary' => 'Conversation #:index · started :started_at',
        'conversation_ongoing' => 'ongoing',
        'conversation_closed' => 'closed',
    ],
];
