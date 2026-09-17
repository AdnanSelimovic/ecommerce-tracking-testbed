<?php

return [
    'ga4' => [
        // Only the public measurement ID is exposed to the browser. Never add
        // a Measurement Protocol API secret to this client-side configuration.
        'measurement_id' => env('GA4_MEASUREMENT_ID'),
        'enabled' => filled(env('GA4_MEASUREMENT_ID')),
        'server_measurement_id' => env('GA4_SERVER_MEASUREMENT_ID'),
        'server_api_secret' => env('GA4_SERVER_API_SECRET'),
        'server_enabled' => filled(env('GA4_SERVER_MEASUREMENT_ID')) && filled(env('GA4_SERVER_API_SECRET')),
        'server_endpoint' => env('GA4_SERVER_ENDPOINT', 'https://www.google-analytics.com/mp/collect'),
        'server_debug_endpoint' => env('GA4_SERVER_DEBUG_ENDPOINT', 'https://www.google-analytics.com/debug/mp/collect'),
    ],

    'meta' => [
        // Only the public Pixel ID is exposed to the browser. Access tokens
        // belong exclusively to a future server-side milestone.
        'pixel_id' => env('META_PIXEL_ID'),
        'enabled' => filled(env('META_PIXEL_ID')),
        'capi_access_token' => env('META_CAPI_ACCESS_TOKEN'),
        'capi_enabled' => filled(env('META_PIXEL_ID')) && filled(env('META_CAPI_ACCESS_TOKEN')),
        'graph_api_version' => env('META_GRAPH_API_VERSION', 'v26.0'),
        'capi_test_event_code' => env('META_CAPI_TEST_EVENT_CODE'),
    ],
];
