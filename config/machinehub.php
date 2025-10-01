<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supplier configurations
    |--------------------------------------------------------------------------
    | Each supplier has its own webhook + API integration settings.
    | Add new suppliers here as needed.
    */
    'suppliers' => [
        'dejong' => [
            'base_url' => env('DEJONG_API_URL', null),
            'api_key'  => env('DEJONG_API_KEY', null),
            'options' => [
                'mode' => 'api',
                'rate_limit'       => '30,1',
                'subscription_name' => 'dejong-sub',
                'allowed_ips'      => [''],
            ],
            'tenants' => [
                'yellowbeared' => [
                    'webhook_url' => env('YELLOWBEARED_WEBHOOK_URL', null),
                    'api_key' => env('YELLOWBEARED_API_KEY', null),
                ],
                'yellowrock' => [
                    'webhook_url' => env('YELLOWROCK_WEBHOOK_URL', null),
                    'api_key' => env('YELLOWROCK_API_KEY', null),
                ],
            ],
        ],

        'wmf' => [
            'options' => [
                'mode' => 'webhook',
                'rate_limit'       => '30,1',
                'subscription_name' => 'wmf-telemetry-subscription',
                'allowed_ips'      => [''],
            ],
            'tenants' => [
                'yellowbeared' => [
                    'webhook_url' => env('YELLOWBEARED_WEBHOOK_URL', null),
                    'api_key' => env('YELLOWBEARED_API_KEY', null),
                ],
                'yellowrock' => [
                    'webhook_url' => env('YELLOWROCK_WEBHOOK_URL', null),
                    'api_key' => env('YELLOWROCK_API_KEY', null),
                ],
                'hermelin' => [
                    'webhook_url' => env('HERMELIN_WEBHOOK_URL', null),
                    'api_key' => env('HERMELIN_API_KEY', null),
                ],
            ],
        ],

        'franke' => [
            'options' => [
                'mode' => 'webhook',
                'rate_limit'       => '30,1',
                'subscription_name' => 'franke-sub',
                'allowed_ips'      => ['127.0.0.1'],
            ],
            'tenants' => [
                'yellowbeared' => [
                    'webhook_url' => env('YELLOWBEARED_WEBHOOK_URL', null),
                    'api_key' => env('YELLOWBEARED_API_KEY', null),
                ],
                'yellowrock' => [
                    'webhook_url' => env('YELLOWROCK_WEBHOOK_URL', null),
                    'api_key' => env('YELLOWROCK_API_KEY', null),
                ],
            ],
        ],
    ],

    'webhook_url' => env('MACHINEHUB_WEBHOOK_URL'),


];
