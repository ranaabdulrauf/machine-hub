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
        ],

        'wmf' => [
            'base_url'         => 'https://events.wmf.com',
            'rate_limit'       => '30,1',
            'subscription_name' => 'wmf-sub',
            'allowed_ips'      => ['127.0.0.1'],
        ],
    ],

];
