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
        'schaerer' => [
            'base_url'         => 'https://events.schaerer.com',
            'rate_limit'       => '60,1',
            'subscription_name' => env('SCHAERER_SUBSCRIPTION_NAME', 'schaerer-sub'),
            'allowed_ips'      => ['1.2.3.4'],
        ],

        'wmf' => [
            'base_url'         => 'https://events.wmf.com',
            'rate_limit'       => '30,1',
            'subscription_name' => 'wmf-sub',
            'allowed_ips'      => ['127.0.0.1'],
        ],
    ],

];
