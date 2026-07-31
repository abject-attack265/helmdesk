<?php

declare(strict_types=1);

return [
    'wechat_unsupported_message' => 'Only text and image messages are currently supported.',
    'statuses' => [
        'open' => 'Active',
        'closed' => 'Closed',
    ],
    'inbox_statuses' => [
        'ai_handling' => 'AI handling',
        'teammate_pending' => 'Awaiting human',
        'teammate_handling' => 'Human handling',
    ],
    'visitor_reply_statuses' => [
        'waiting' => 'Awaiting visitor reply',
        'not_waiting' => 'Not awaiting visitor reply',
    ],
    'reply_assistant_modes' => [
        'reply' => 'Help me reply',
        'rewrite' => 'Rewrite',
    ],
    'reply_polish_tones' => [
        'keep' => 'Keep tone',
        'professional' => 'Professional',
        'friendly' => 'Friendly',
        'concise' => 'Concise',
    ],
    'sources' => [
        'manual' => 'Manual',
        'channel' => 'Channel',
    ],
    'entry_modes' => [
        'widget' => 'Widget',
        'standalone' => 'Standalone',
        'telegram' => 'Telegram',
        'wechat_official_account' => 'WeChat Official Account',
    ],
    'message_roles' => [
        'visitor' => 'Visitor',
        'ai' => 'AI',
        'teammate' => 'Teammate',
        'tool' => 'Tool',
    ],
    'message_kinds' => [
        'text' => 'Text',
        'image' => 'Image',
        'file' => 'File',
        'summary' => 'Summary',
        'tool_call' => 'Tool Call',
        'tool_result' => 'Tool Result',
    ],
    'message_delivery_statuses' => [
        'sending' => 'Sending',
        'sent' => 'Sent',
        'failed' => 'Failed',
    ],
    'event_types' => [
        'created' => 'Created',
        'assignment_changed' => 'Assignment Changed',
        'handoff_requested' => 'Handoff Requested',
        'status_changed' => 'Status Changed',
        'reception_turn_started' => 'Reception Turn Started',
        'reception_tool_called' => 'Reception Tool Called',
        'reception_turn_ended' => 'Reception Turn Ended',
        'feedback_received' => 'Feedback Received',
    ],
    'rating' => [
        'scores' => [
            'positive' => 'Satisfied',
            'negative' => 'Unsatisfied',
        ],
        'handled_by' => [
            'ai' => 'AI',
            'human' => 'Human',
        ],
        'errors' => [
            'not_closed' => 'This conversation has not ended yet and cannot be rated.',
        ],
        'telegram' => [
            'prompt' => 'How was your experience?',
            'satisfied' => '👍 Satisfied',
            'unsatisfied' => '👎 Unsatisfied',
            'thanks' => 'Thanks for your feedback!',
        ],
    ],
    'event_displays' => [
        'feedback_received' => [
            'positive' => 'The visitor left positive feedback',
            'negative' => 'The visitor left negative feedback',
        ],
        'actors' => [
            'system' => 'System',
        ],
        'created' => [
            'reception' => 'The visitor started this conversation from the web widget',
            'manual' => ':actor manually created this conversation',
        ],
        'handoff_requested' => [
            'user_requested' => 'Visitor requested a human',
            'ai_requested' => 'AI decided this conversation needs a human',
            'low_confidence' => 'AI was not sure how to answer, so it handed off',
            'tool_failure' => 'AI hit an issue while handling this, so it handed off',
            'policy_required' => 'Business rules require human handling',
            'ai_unavailable' => 'AI is temporarily unavailable, handed off to human',
            'default' => 'AI requested a handoff to a human',
        ],
        'assignment_changed' => [
            'claim' => ':actor started handling this conversation',
            'reply' => ':actor replied and started handling this conversation',
            'transfer_to_human' => ':actor took over the conversation AI was handling',
            'takeover' => ':actor took over this conversation from :previous_user',
            'transfer_to_teammate' => ':actor transferred this conversation to :target',
            'release_to_ai' => ':actor handed this conversation to AI',
            'release_to_queue' => ':actor returned this conversation to the queue',
        ],
        'status_changed' => [
            'closed' => ':actor closed the conversation',
            'open' => ':actor reopened the conversation',
        ],
        'reception_tool_called' => [
            'success' => 'AI used the ":tool" tool',
            'failed' => 'AI encountered an error using the ":tool" tool',
        ],
    ],
    'errors' => [
        'invalid_cursor' => 'Failed to load the chat history. Please refresh and try again.',
        'invalid_quoted_message' => 'The quoted message is unavailable or does not belong to this conversation.',
        'message_not_retryable' => 'This message cannot be retried.',
        'invalid_role_kind_combination' => 'The message role and kind combination is invalid.',
        'empty_message' => 'Message content cannot be empty.',
        'message_too_long' => 'Message is too long, please split it.',
        'ai_reply_not_allowed' => 'The conversation was handed off to a teammate; AI cannot continue to reply.',
        'transfer_to_human_required_before_reply' => 'Please take over this conversation before replying.',
        'reply_not_allowed_for_assignee' => 'This conversation is assigned to another teammate.',
        'reply_translation_stale' => 'The visitor language changed. Confirm the translated content again before sending.',
        'reply_polish_failed' => 'Failed to generate a reply. Please try again.',
        'close_not_allowed_for_assignee' => 'This conversation is assigned to another teammate and cannot be closed by you.',
        'already_ai_handling' => 'This conversation is already handled by AI.',
        'release_to_ai_not_allowed' => 'You can only release conversations assigned to you back to AI.',
        'release_to_ai_unavailable' => 'AI reception is currently unavailable; the conversation cannot be handed to AI.',
        'already_closed' => 'Conversation is closed; no further actions allowed.',
        'already_open' => 'Conversation is already open.',
        'reopen_conflicts_with_open_conversation' => 'This contact already has an open conversation in this channel.',
        'claim_failed' => 'Could not take over this conversation. Another agent may already be handling it.',
        'transfer_to_teammate_not_allowed' => 'You can only transfer conversations currently assigned to you.',
        'transfer_target_must_be_teammate' => 'Choose another teammate as the transfer target.',
        'transfer_target_not_found' => 'Choose a teammate in the current app.',
        'recall_not_assignee' => 'Only the assigned teammate can recall messages.',
        'recall_not_owner' => 'You can only recall messages you sent.',
        'recall_already_recalled' => 'This message has already been recalled.',
        'recall_kind_not_allowed' => 'This kind of message cannot be recalled.',
        'recall_window_expired' => 'Messages older than :minutes minutes cannot be recalled.',
        'message_not_found' => 'The message does not exist or has been deleted.',
    ],
    'empty_content' => 'No content',
    'message_recalled_placeholder' => '[Message recalled]',
];
