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
                    'webhook_url' => env('DEJONG_YELLOWBEARED_WEBHOOK_URL', null),
                    'api_key' => env('DEJONG_YELLOWBEARED_API_KEY', null),
                ],
                'yellowrock' => [
                    'webhook_url' => env('DEJONG_YELLOWROCK_WEBHOOK_URL', null),
                    'api_key' => env('DEJONG_YELLOWROCK_API_KEY', null),
                ],
            ],
        ],

        'wmf' => [
            'options' => [
                'mode' => 'webhook',
                'rate_limit'       => '30,1',
                'subscription_name' => 'wmf-sub',
                'allowed_ips'      => [''],
            ],
            'tenants' => [
                'yellowbeared' => [
                    'webhook_url' => env('WMF_YELLOWBEARED_WEBHOOK_URL', null),
                    'api_key' => env('WMF_YELLOWBEARED_API_KEY', null),
                    'base_url' => 'https://events.wmf.com',
                ],
                'yellowrock' => [
                    'webhook_url' => env('WMF_YELLOWROCK_WEBHOOK_URL', null),
                    'api_key' => env('WMF_YELLOWROCK_API_KEY', null),
                    'base_url' => 'https://events.wmf.com',
                ],
                'hermelin' => [
                    'webhook_url' => env('WMF_HERMELIN_WEBHOOK_URL', null),
                    'api_key' => env('WMF_HERMELIN_API_KEY', null),
                    'base_url' => 'https://events.wmf.com',
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
                    'webhook_url' => env('FRANKE_YELLOWBEARED_WEBHOOK_URL', null),
                    'api_key' => env('FRANKE_YELLOWBEARED_API_KEY', null),
                    'base_url' => 'https://events.franke.com',
                ],
                'yellowrock' => [
                    'webhook_url' => env('FRANKE_YELLOWROCK_WEBHOOK_URL', null),
                    'api_key' => env('FRANKE_YELLOWROCK_API_KEY', null),
                    'base_url' => 'https://events.franke.com',
                ],
            ],
        ],
    ],

    'webhook_url' => env('MACHINEHUB_WEBHOOK_URL'),


];
