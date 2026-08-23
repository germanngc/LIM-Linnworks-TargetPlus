<?php

return [

    'linnworks' => [
        'app_id'     => env('LINNWORKS_APP_ID'),
        'app_secret' => env('LINNWORKS_APP_SECRET'),
        'channel_integration' => env('LINNWORKS_CHANNEL_INTEGRATION', false),
        'order_push_path' => env('LINNWORKS_ORDER_PUSH_PATH', '/api/Orders/CreateOrders'),
        'order_source' => env('LINNWORKS_ORDER_SOURCE', 'VENDOR'),
        'order_subsource' => env('LINNWORKS_ORDER_SUBSOURCE', 'Vendor'),
    ],

    'vendor_api' => [
        'base_url' => env('VENDOR_API_BASE_URL'),
    ],

    'slack' => [
        'webhook_url' => env('SLACK_WEBHOOK_URL'),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

];
