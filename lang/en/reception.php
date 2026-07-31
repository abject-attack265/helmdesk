<?php

return [
    'sandbox' => [
        'no_model' => 'No usable reception model is available for a trial run; configure one in AI settings first.',
    ],
    'plan_version_statuses' => [
        'published' => 'Published',
        'archived' => 'Archived',
    ],
    'plan_version_unusable_reasons' => [
        'archived' => 'Archived version cannot be deployed to new channels (channels already on it can keep using it)',
        'reception_model_unavailable' => 'The reception model is unavailable; restore it in AI settings',
        'no_usable_version' => 'The plan has no usable configuration yet; configure an available reception model first',
    ],
    'persona_tones' => [
        'professional' => 'Professional',
        'friendly' => 'Friendly',
        'concise' => 'Concise',
    ],
    'human_service_unavailable_reasons' => [
        'outside_business_hours' => 'Outside business hours',
        'no_online_teammate' => 'No teammate online',
    ],
    'routing_modes' => [
        'ai_first' => 'AI handles first',
        'teammate_first' => 'Support handles first',
    ],
    'defaults' => [
        'handoff_available_notice' => 'We are connecting you to a support agent. Please wait.',
        'handoff_no_teammate_notice' => 'No support agent is available right now. I will keep helping you.',
        'outside_hours_notice' => 'Support is currently outside service hours. AI will help first, and a support agent will follow up during service hours.',
        'ai_unavailable_notice' => 'Sorry, AI cannot reply right now. We are connecting you to a support agent. Please wait.',
    ],
    'human_service_runtime' => [
        'yes' => 'Yes',
        'no' => 'No',
        'heading' => '[Human Service Status]',
        'current_local_time' => 'Current local time: :time (:timezone)',
        'business_hours' => 'Human support business hours: :summary',
        'within_business_hours' => 'Currently within business hours: :value',
        'has_online_teammate' => 'Available teammate online: :value',
        'human_available' => 'Human handoff currently allowed: :value',
        'answer_scope' => 'When visitors ask about human support hours, availability, or whether handoff is possible, answer only from this section.',
        'call_handoff_tool' => 'When visitors request a human handoff, call handoff_to_human; the tool sends the notice directly to the visitor.',
        'handoff_terminal' => 'handoff_to_human is the final visitor-facing action for this turn.',
        'next_available_at' => 'Next human support start time: :time',
        'business_hours_not_set' => 'No fixed business hours set.',
        'business_hours_empty' => 'No available business hours configured.',
        'closed' => 'Closed',
        'summary_separator' => '; ',
        'weekdays' => [
            'monday' => 'Mon',
            'tuesday' => 'Tue',
            'wednesday' => 'Wed',
            'thursday' => 'Thu',
            'friday' => 'Fri',
            'saturday' => 'Sat',
            'sunday' => 'Sun',
        ],
    ],
    'service_scenarios' => [
        'page_title' => 'Service Scenarios',
        'page_description' => 'Configure the service scenarios of this reception plan; each scenario defines a task handling recipe.',
        'empty_title' => 'No service scenarios configured',
        'empty_description' => 'Service scenarios describe the business tasks the reception AI can dispatch, such as order lookup, FAQ, after-sales, etc.',
        'create_from_scratch' => 'Create from scratch',
        'create_from_template' => 'Create from template',
        'create_from_template_short' => 'Use this template',
        'validation' => [
            'takeover_timeout_exceeds_auto_close' => 'The wait before AI takes over must be shorter than the auto-close time',
            'business_hours_end_after_start' => 'The end time must be later than the start time',
        ],
        'fields' => [
            'name' => 'Scenario name',
            'description' => 'Scenario description',
            'description_hint' => 'Helps reception AI decide when to dispatch this task.',
            'instructions' => 'Scenario instructions',
            'instructions_hint' => 'System prompt used when handling this task type. Explain role, scope and output format.',
        ],
        'plan_fields' => [
            'knowledge_bases' => 'Plan knowledge bases',
            'knowledge_bases_hint' => 'Knowledge bases available during task execution (multi-select; optional).',
            'integrations' => 'Plan integrations',
            'integrations_hint' => 'Integration tools available during task execution (granted per integration; tool whitelist can be narrowed).',
        ],
    ],
    'messages' => [
        'plan_name_exists' => 'A reception plan already uses this name. Choose another name',
        'plan_in_use_channel' => 'This plan is used by :count channel(s). Choose another reception plan for those channels first',
        'plan_in_use_conversation' => 'This plan still has :count active conversation(s). Wait until they end before deleting it',
        'knowledge_base_invalid' => 'Some knowledge bases are no longer available. Please choose again',
        'integration_invalid' => 'Some integrations are no longer available. Please choose again',
        'service_scenario_template_not_found' => 'The specified service scenario template does not exist',
        'service_scenario_name_duplicated' => 'Service scenario names must be unique within a plan (case- and whitespace-insensitive)',
    ],
    'telegram' => [
        'start_message' => 'Hello! Send us your question and we will help you as soon as possible.',
    ],
    'errors' => [
        'message_empty' => 'Message content cannot be empty',
        'message_too_long' => 'Message is too long, please send it in parts',
    ],
];
