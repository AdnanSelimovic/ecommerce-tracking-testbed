<?php

return [
    'ga4' => [
        // Only the public measurement ID is exposed to the browser. Never add
        // a Measurement Protocol API secret to this client-side configuration.
        'measurement_id' => env('GA4_MEASUREMENT_ID'),
        'enabled' => filled(env('GA4_MEASUREMENT_ID')),
    ],

    'meta' => [
        // Only the public Pixel ID is exposed to the browser. Access tokens
        // belong exclusively to a future server-side milestone.
        'pixel_id' => env('META_PIXEL_ID'),
        'enabled' => filled(env('META_PIXEL_ID')),
    ],
];
