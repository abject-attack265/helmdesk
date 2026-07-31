<?php

return [
    'app_ai_updated' => 'Instance AI settings saved.',

    'invalid_app_model' => 'The selected model is invalid or inactive. Please choose another.',
    'app_default_model_unavailable' => 'The app default model is unavailable. Please fix it in default model settings or choose a model for this reception plan.',

    'max_concurrency_exceeds_global' => 'Max concurrency cannot exceed this app AI max concurrency setting (:max).',
    'global_max_concurrency_below_model_limit' => 'The app AI max concurrency setting cannot be lower than an existing model limit. Model ":model" is set to :max.',

    'model_in_use_app' => 'This model is the app default and cannot be disabled or deleted. Please change the app default model first.',
    'model_in_use_reception_plan' => 'This model is referenced by a reception plan (draft or published version) and cannot be disabled or deleted. Update the affected reception plan to use another model first.',
    'provider_in_use_app' => 'A model under this provider is the app default. Please change the app default model first.',
    'provider_in_use_reception_plan' => 'A model under this provider is referenced by a reception plan. Please update the affected reception plan to use another model first.',

    'model_status' => [
        'model_inactive' => 'The model is inactive',
        'provider_inactive' => 'The model provider is inactive',
        'deleted' => 'The model has been deleted',
        'missing_after_delete' => 'No usable model configured',
    ],
];
