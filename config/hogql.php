<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Builder
    |--------------------------------------------------------------------------
    |
    | You can use a custom builder class to extend the default builder.
    | E.g. if you want to add additional query authorization scopes.
    |
    */

    'builder' => \MOIREI\HogQl\Builder::class,

    /*
    |--------------------------------------------------------------------------
    | Query Model
    |--------------------------------------------------------------------------
    |
    | An Eloquent model that can be used to represent queries. Useful in some cases
    | where you need to connect query results to other Models.
    |
    */

    'model' => \MOIREI\HogQl\Model::class,

    /*
    |--------------------------------------------------------------------------
    | Default Query Table
    |--------------------------------------------------------------------------
    |
    | Specifies the default Posthog HogQL query table.
    |
    */

    'default_table' => 'events',

    /*
    |--------------------------------------------------------------------------
    | Query Property Alias Map
    |--------------------------------------------------------------------------
    |
    | HogQL properties that should auto renamed. For example you may want to
    | reference a property as `workspace` instead of `properties.workspace`.
    |
    */

    'aliases' => [
        // 'gid' => 'properties.gid',
        // 'handle' => 'properties.handle',
        // 'object' => 'properties.object',
        // Add more aliases as needed
    ],

    /*
    |--------------------------------------------------------------------------
    | Restricted Query Access
    |--------------------------------------------------------------------------
    |
    | Posthog SQL tables that be accessed. Use to ensure only specified tables
    | can be accessed.
    |
    */

    'allowed_tables' => [
        'events',
    ],

    /*
    |--------------------------------------------------------------------------
    | API Client
    |--------------------------------------------------------------------------
    |
    | Here you define PostHog api client credentials.
    |
    */

    'api_client' => [
        'product_id' => env('POSTHOG_PRODUCT_ID'),
        'api_token' => env('POSTHOG_API_TOKEN'),
        'api_host' => env('POSTHOG_API_HOST', 'https://us.posthog.com'),
    ],
];
