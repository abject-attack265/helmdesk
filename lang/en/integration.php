<?php

return [
    'page' => [
        'title' => 'Integrations',
        'description' => 'Connect external systems so AI and teammates can use their tools and data.',
        'empty' => 'No integrations have been added yet.',
        'empty_action' => 'Add your first integration',
    ],
    'providers' => [
        'mcp' => 'MCP',
        'business_system' => 'Business System',
        'mock_business_system' => 'Business System (mock)',
    ],
    'transports' => [
        'streamable_http' => 'Streamable HTTP',
        'http' => 'HTTP',
    ],
    'auth_presets' => [
        'none' => 'No verification',
        'bearer' => 'Access token',
        'header' => 'Custom header',
    ],
    'sync_statuses' => [
        'pending' => 'Not updated yet',
        'syncing' => 'Updating',
        'success' => 'Updated',
        'failed' => 'Update failed',
    ],
    'fields' => [
        'name' => 'Integration name',
        'transport' => 'Transport',
        'endpoint_url' => 'Service URL',
        'auth_preset' => 'Verification',
        'bearer_token' => 'Access token',
        'auth_header_name' => 'Request header name',
        'auth_header_value' => 'Request header value',
        'custom_headers' => 'Custom headers',
        'timeout_seconds' => 'Maximum wait (seconds)',
        'tools_count' => 'Tools',
        'last_synced_at' => 'Last updated',
    ],
    'actions' => [
        'add' => 'Add integration',
        'save' => 'Save',
        'test_connection' => 'Test connection',
        'sync_tools' => 'Update tools',
        'delete' => 'Delete',
    ],
    'placeholders' => [
        'keep_credential' => 'Keep current value (leave blank)',
    ],
    'messages' => [
        'created' => 'Integration created.',
        'check_succeeded' => 'Connection is healthy.',
        'sync_succeeded' => 'Updated :total tools (:added added, :removed removed).',
        'sync_all_queued' => 'Started updating tools for :count integrations.',
        'tool_disabled_due_to_removal' => 'This tool has been removed and cannot be enabled.',
    ],
    'tool' => [
        'removed_badge' => 'Removed',
        'description_empty' => 'No description.',
        'schema_label' => 'Input schema',
        'annotations_label' => 'Annotations',
    ],
    'delete' => [
        'title' => 'Delete integration ":name"?',
        'description' => 'This also removes the integration’s :count tools.',
    ],
    'runtime' => [
        'check' => [
            'succeeded' => 'Connection is healthy.',
            'failed' => 'Connection failed: :error',
            'timeout' => 'Connection timed out. Please try again later.',
            'unauthorized' => 'Verification failed. Check the access token or verification details.',
            'protocol_error' => 'Could not connect to the MCP service: :error',
        ],
        'validate' => [
            'succeeded' => 'Connection details are valid.',
            'missing_endpoint' => 'Enter a service URL.',
            'unsupported_transport' => 'This connection method is not supported.',
            'unsafe_endpoint' => 'This service URL cannot be used. Enter a publicly accessible URL.',
        ],
        'list_tools' => [
            'succeeded' => 'Tool list updated.',
            'failed' => 'Failed to update tools: :error',
            'invalid_response' => 'The business system returned an invalid tool list.',
        ],
        'bridge' => [
            'not_configured' => 'The MCP service is temporarily unavailable. Please try again later.',
            'unavailable' => 'The MCP service is temporarily unavailable. Please try again later.',
            'invalid_response' => 'The MCP service returned invalid data. Check the service configuration.',
            'request_failed' => 'Could not connect to the MCP service. Check the URL and try again.',
        ],
        'request' => [
            'invalid_payload' => 'The submitted information is invalid: :error',
        ],
    ],
    'mock' => [
        'tools' => [
            'query_order' => [
                'description' => 'Look up order status, shipment, and amount by order number or email.',
                'order_no' => 'Order number',
                'email' => 'Order email',
            ],
            'query_customer' => [
                'description' => 'Look up customer tier and lifetime order count by email.',
                'email' => 'Customer email',
            ],
            'unknown' => 'Unknown tool ":name"; no data is available.',
        ],
        'panel' => [
            'customer_overview' => 'Customer overview',
            'customer_tier' => 'Customer tier',
            'lifetime_orders' => 'Lifetime orders',
            'member_since' => 'Member since',
            'member_profile' => 'Member profile',
            'recent_orders' => 'Recent orders',
            'noise_cancelling_headphones' => 'Wireless noise-cancelling headphones ×1',
            'mechanical_keyboard' => 'Mechanical keyboard ×1',
            'usb_c_cable' => 'USB-C cable ×2',
            'amount_299' => '$299.00',
            'amount_459' => '$459.00',
            'amount_58' => '$58.00',
            'shipped' => 'Shipped',
            'completed' => 'Completed',
            'refunded' => 'Refunded',
        ],
        'order' => [
            'status' => 'Shipped',
            'amount' => '$299.00',
            'content' => 'Order :order_no: :status, tracking number :tracking_no, total :amount, placed at :placed_at.',
        ],
        'customer' => [
            'name' => 'Olivia Bennett',
            'level' => 'Gold member',
            'content' => ':name (:email) is a :level with :order_count lifetime orders.',
        ],
    ],
];
