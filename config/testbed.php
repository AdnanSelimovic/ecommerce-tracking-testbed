<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Test Currency
    |--------------------------------------------------------------------------
    |
    | All monetary values in this testbed are stored as integer minor units
    | (cents). This is the ISO-4217 currency those minor units belong to.
    |
    */

    'currency' => env('TESTBED_CURRENCY', 'EUR'),

    /*
    |--------------------------------------------------------------------------
    | Canonical Ground-Truth Event Names
    |--------------------------------------------------------------------------
    |
    | These are the backend-recorded events treated as the source of truth in
    | this research. Measurement systems (GA4, Meta) are compared *against*
    | these records; they never define them.
    |
    */

    'ground_truth_events' => [
        'view_item',
        'add_to_cart',
        'begin_checkout',
        'purchase',
    ],

];
