<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'Must be accepted.',
    'accepted_if' => 'Must be accepted when :other is :value.',
    'active_url' => 'Must be a valid URL.',
    'after' => 'Must be a date after :date.',
    'after_or_equal' => 'Must be a date after or equal to :date.',
    'alpha' => 'Must only contain letters.',
    'alpha_dash' => 'Must only contain letters, numbers, dashes, and underscores.',
    'alpha_num' => 'Must only contain letters and numbers.',
    'any_of' => 'Invalid.',
    'array' => 'Must be an array.',
    'ascii' => 'Must only contain single-byte alphanumeric characters and symbols.',
    'before' => 'Must be a date before :date.',
    'before_or_equal' => 'Must be a date before or equal to :date.',
    'between' => [
        'array' => 'Must have between :min and :max items.',
        'file' => 'Must be between :min and :max kilobytes.',
        'numeric' => 'Must be between :min and :max.',
        'string' => 'Must be between :min and :max characters.',
    ],
    'boolean' => 'Must be true or false.',
    'can' => 'Contains an unauthorized value.',
    'confirmed' => 'Confirmation does not match.',
    'contains' => 'Missing a required value.',
    'current_password' => 'The password is incorrect.',
    'date' => 'Must be a valid date.',
    'date_equals' => 'Must be a date equal to :date.',
    'date_format' => 'Must match the format :format.',
    'decimal' => 'Must have :decimal decimal places.',
    'declined' => 'Must be declined.',
    'declined_if' => 'Must be declined when :other is :value.',
    'different' => 'Must be different from :other.',
    'digits' => 'Must be :digits digits.',
    'digits_between' => 'Must be between :min and :max digits.',
    'dimensions' => 'Has invalid image dimensions.',
    'distinct' => 'Has a duplicate value.',
    'doesnt_contain' => 'Must not contain any of the following: :values.',
    'doesnt_end_with' => 'Must not end with one of the following: :values.',
    'doesnt_start_with' => 'Must not start with one of the following: :values.',
    'email' => 'Must be a valid email address.',
    'encoding' => 'Must be encoded in :encoding.',
    'ends_with' => 'Must end with one of the following: :values.',
    'enum' => 'Invalid selection.',
    'exists' => 'Invalid selection.',
    'extensions' => 'Must have one of the following extensions: :values.',
    'file' => 'Must be a file.',
    'filled' => 'Must have a value.',
    'gt' => [
        'array' => 'Must have more than :value items.',
        'file' => 'Must be greater than :value kilobytes.',
        'numeric' => 'Must be greater than :value.',
        'string' => 'Must be greater than :value characters.',
    ],
    'gte' => [
        'array' => 'Must have :value items or more.',
        'file' => 'Must be greater than or equal to :value kilobytes.',
        'numeric' => 'Must be greater than or equal to :value.',
        'string' => 'Must be greater than or equal to :value characters.',
    ],
    'hex_color' => 'Must be a valid hexadecimal color.',
    'image' => 'Must be an image.',
    'in' => 'Invalid selection.',
    'in_array' => 'Must exist in :other.',
    'in_array_keys' => 'Must contain at least one of the following keys: :values.',
    'integer' => 'Must be an integer.',
    'ip' => 'Must be a valid IP address.',
    'ipv4' => 'Must be a valid IPv4 address.',
    'ipv6' => 'Must be a valid IPv6 address.',
    'json' => 'Must be a valid JSON string.',
    'list' => 'Must be a list.',
    'lowercase' => 'Must be lowercase.',
    'lt' => [
        'array' => 'Must have less than :value items.',
        'file' => 'Must be less than :value kilobytes.',
        'numeric' => 'Must be less than :value.',
        'string' => 'Must be less than :value characters.',
    ],
    'lte' => [
        'array' => 'Must not have more than :value items.',
        'file' => 'Must be less than or equal to :value kilobytes.',
        'numeric' => 'Must be less than or equal to :value.',
        'string' => 'Must be less than or equal to :value characters.',
    ],
    'mac_address' => 'Must be a valid MAC address.',
    'max' => [
        'array' => 'Must not have more than :max items.',
        'file' => 'Must not be greater than :max kilobytes.',
        'numeric' => 'Must not be greater than :max.',
        'string' => 'Must not be greater than :max characters.',
    ],
    'max_digits' => 'Must not have more than :max digits.',
    'mimes' => 'Must be a file of type: :values.',
    'mimetypes' => 'Must be a file of type: :values.',
    'min' => [
        'array' => 'Must have at least :min items.',
        'file' => 'Must be at least :min kilobytes.',
        'numeric' => 'Must be at least :min.',
        'string' => 'Must be at least :min characters.',
    ],
    'min_digits' => 'Must have at least :min digits.',
    'missing' => 'Must be missing.',
    'missing_if' => 'Must be missing when :other is :value.',
    'missing_unless' => 'Must be missing unless :other is :value.',
    'missing_with' => 'Must be missing when :values is present.',
    'missing_with_all' => 'Must be missing when :values are present.',
    'multiple_of' => 'Must be a multiple of :value.',
    'not_in' => 'Invalid selection.',
    'not_regex' => 'Format is invalid.',
    'numeric' => 'Must be a number.',
    'password' => [
        'letters' => 'Must contain at least one letter.',
        'mixed' => 'Must contain at least one uppercase and one lowercase letter.',
        'numbers' => 'Must contain at least one number.',
        'symbols' => 'Must contain at least one symbol.',
        'uncompromised' => 'Has appeared in a data leak. Please choose a different password.',
    ],
    'present' => 'Must be present.',
    'present_if' => 'Must be present when :other is :value.',
    'present_unless' => 'Must be present unless :other is :value.',
    'present_with' => 'Must be present when :values is present.',
    'present_with_all' => 'Must be present when :values are present.',
    'prohibited' => 'Is prohibited.',
    'prohibited_if' => 'Is prohibited when :other is :value.',
    'prohibited_if_accepted' => 'Is prohibited when :other is accepted.',
    'prohibited_if_declined' => 'Is prohibited when :other is declined.',
    'prohibited_unless' => 'Is prohibited unless :other is in :values.',
    'prohibits' => 'Prohibits :other from being present.',
    'regex' => 'Format is invalid.',
    'required' => 'Is required.',
    'required_array_keys' => 'Must contain entries for: :values.',
    'required_if' => 'Is required when :other is :value.',
    'required_if_accepted' => 'Is required when :other is accepted.',
    'required_if_declined' => 'Is required when :other is declined.',
    'required_unless' => 'Is required unless :other is in :values.',
    'required_with' => 'Is required when :values is present.',
    'required_with_all' => 'Is required when :values are present.',
    'required_without' => 'Is required when :values is not present.',
    'required_without_all' => 'Is required when none of :values are present.',
    'same' => 'Must match :other.',
    'size' => [
        'array' => 'Must contain :size items.',
        'file' => 'Must be :size kilobytes.',
        'numeric' => 'Must be :size.',
        'string' => 'Must be :size characters.',
    ],
    'starts_with' => 'Must start with one of the following: :values.',
    'string' => 'Must be a string.',
    'timezone' => 'Must be a valid timezone.',
    'unique' => 'Has already been taken.',
    'uploaded' => 'Failed to upload.',
    'uppercase' => 'Must be uppercase.',
    'url' => 'Must be a valid URL.',
    'ulid' => 'Must be a valid ULID.',
    'uuid' => 'Must be a valid UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [
        'reception_plan_version_id' => 'reception plan version',
    ],

];
